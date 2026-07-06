<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\FormatsWorksheetAsTable;
use App\Exports\IncidentExportFilters;
use App\Models\Incident;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class IncidentsSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    use FormatsWorksheetAsTable;

    private IncidentExportFilters $filters;

    public function __construct(
        public string $from,
        public string $to,
        public ?string $province,
        public bool $includeSurvivantName,
        public bool $includeNotes,
        public bool $includeViolences,
        public ?string $territoire = null,
    ) {
        $this->filters = new IncidentExportFilters($from, $to, $province, $territoire);
    }

    public function title(): string
    {
        return 'Alertes';
    }

    public function headings(): array
    {
        $cols = [
            'code_incident',
            'date_incident',
            'province',
            'territoire',
            'nom_chefferie',
            'nom_groupement',
            'zone_sante',
            'nom_airesante',
            'localite',
            'code_evenement',
            'nom_evenement',
            'description_faits',
            'source_info',
            'auteur_presume',
            'severite',
            'statut_incident',
            'niveau_confidentialite',
            'cree_par',
            'cree_le',
            'incident_id',
            'nombre_violences',
            'nombre_victimes',
            'nombre_reponses',
        ];

        if ($this->includeNotes) {
            $cols[] = 'notes';
        }

        return $cols;
    }

    public function collection(): Collection
    {
        $query = Incident::query()
            ->with([
                'province',
                'territoire',
                'zoneSante',
                'chefferie',
                'groupement',
                'aireSante',
                'evenement',
                'creator',
            ])
            ->withCount(['violences', 'victimes', 'reponses'])
            ->orderBy('date_incident')
            ->orderBy('code_incident');

        if ($this->includeNotes) {
            $query->with(['caseNotes.author']);
        }

        $this->filters->applyToIncidentQuery($query);

        return $query->get()->map(function (Incident $incident): array {
            $row = [
                $incident->code_incident,
                optional($incident->date_incident)->format('Y-m-d'),
                $incident->province?->nom_province ?? '-',
                $incident->territoire?->nom_territoire ?? $incident->code_territoire,
                $incident->chefferie?->nom_chefferie ?? $incident->code_chefferie,
                $incident->groupement?->nom_groupement ?? $incident->code_groupement,
                $incident->zoneSante?->nom_zonesante ?? $incident->code_zonesante,
                $incident->aireSante?->nom_airesante ?? $incident->code_airesante,
                $incident->localite,
                $incident->evenement?->code_evenement ?? $incident->code_evenement,
                $incident->evenement?->nom_evenement ?? '-',
                $incident->description_faits,
                $incident->source_info,
                $incident->auteur_presume,
                $incident->severite,
                $incident->statut_incident,
                $incident->confidentiality_level,
                $incident->creator?->name ?? $incident->created_by ?? '-',
                optional($incident->created_at)->format('Y-m-d'),
                $incident->id,
                $incident->violences_count,
                $incident->victimes_count,
                $incident->reponses_count,
            ];

            if ($this->includeNotes) {
                $row[] = ($incident->caseNotes ?? collect())
                    ->sortBy('created_at')
                    ->map(function ($note): string {
                        $by = $note->author?->name ?? '-';
                        $date = optional($note->created_at)->format('Y-m-d');

                        return $date.' - '.$by.': '.str($note->case_note)->limit(140);
                    })
                    ->implode(' || ');
            }

            return $row;
        });
    }
}
