<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\FormatsWorksheetAsTable;
use App\Exports\IncidentExportFilters;
use App\Models\Reponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReponsesSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    use FormatsWorksheetAsTable;

    private IncidentExportFilters $filters;

    public function __construct(
        public string $from,
        public string $to,
        public ?string $province = null,
        public ?string $territoire = null,
    ) {
        $this->filters = new IncidentExportFilters($from, $to, $province, $territoire);
    }

    public function title(): string
    {
        return 'Reponses';
    }

    public function headings(): array
    {
        return [
            'code_incident',
            'date_incident',
            'province',
            'territoire',
            'num_reponse',
            'date_reponse',
            'fournie_par',
            'type_reponse',
            'secteurs_couverts',
            'nbre_menages_couverts',
            'nbre_individus_couverts',
            'impact_reponse',
            'observation_gap',
            'rapport',
            'cree_par',
            'cree_le',
            'reponse_id',
            'incident_id',
        ];
    }

    public function collection(): Collection
    {
        return Reponse::query()
            ->whereHas('incident', fn ($query) => $this->filters->applyToRelatedIncidentQuery($query))
            ->with(['incident.province', 'incident.territoire', 'creator'])
            ->orderBy('alerte_id')
            ->orderBy('date_reponse')
            ->get()
            ->map(function (Reponse $reponse): array {
                $secteurs = is_array($reponse->secteurs_couverts)
                    ? implode(', ', $reponse->secteurs_couverts)
                    : $reponse->secteurs_couverts;

                return [
                    $reponse->incident?->code_incident ?? '-',
                    optional($reponse->incident?->date_incident)->format('Y-m-d'),
                    $reponse->incident?->province?->nom_province ?? '-',
                    $reponse->incident?->territoire?->nom_territoire ?? $reponse->incident?->code_territoire,
                    $reponse->num_reponse,
                    optional($reponse->date_reponse)->format('Y-m-d'),
                    $reponse->fournie_par,
                    $reponse->type_reponse,
                    $secteurs,
                    (int) ($reponse->nbre_menages_couverts ?? 0),
                    (int) ($reponse->nbre_individus_couverts ?? 0),
                    $reponse->impact_reponse,
                    $reponse->observation_gap,
                    $reponse->rapport,
                    $reponse->creator?->name ?? $reponse->created_by ?? '-',
                    optional($reponse->create_at)->format('Y-m-d'),
                    $reponse->id,
                    $reponse->alerte_id,
                ];
            });
    }
}
