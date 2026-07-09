<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\FormatsWorksheetAsTable;
use App\Exports\IncidentExportFilters;
use App\Models\Mouvement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MouvementsSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
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
        return 'Mouvements';
    }

    public function headings(): array
    {
        return [
            'code_incident',
            'date_incident',
            'province_incident',
            'territoire_incident',
            'zone_sante_incident',
            'aire_sante_incident',
            'type_evenement',
            'date_mouvement',
            'type_mouvement',
            'province_provenance',
            'territoire_provenance',
            'zone_sante_provenance',
            'localite_provenance',
            'province_accueil',
            'territoire_accueil',
            'zone_sante_accueil',
            'localite_accueil',
            'nombre_menages',
            'nombre_individus',
            'type_logement',
            'source_info',
            'remarques',
            'cree_par',
            'cree_le',
            'mouvement_id',
            'incident_id',
        ];
    }

    public function collection(): Collection
    {
        return Mouvement::query()
            ->whereHas('incident', fn ($query) => $this->filters->applyToRelatedIncidentQuery($query))
            ->with([
                'incident.province',
                'incident.territoire',
                'incident.zoneSante',
                'incident.aireSante',
                'incident.evenement',
                'provinceProv',
                'territoireProv',
                'zoneSanteProv',
                'provinceAccl',
                'territoireAccl',
                'zoneSanteAccl',
                'creator',
            ])
            ->orderBy('incident_id')
            ->orderBy('date_mouvement')
            ->get()
            ->map(fn (Mouvement $mouvement): array => [
                $mouvement->incident?->code_incident ?? '-',
                optional($mouvement->incident?->date_incident)->format('Y-m-d'),
                $mouvement->incident?->province?->nom_province ?? '-',
                $mouvement->incident?->territoire?->nom_territoire ?? $mouvement->incident?->code_territoire,
                $mouvement->incident?->zoneSante?->nom_zonesante ?? $mouvement->incident?->code_zonesante,
                $mouvement->incident?->aireSante?->nom_airesante ?? $mouvement->incident?->code_airesante,
                $mouvement->incident?->evenement?->nom_evenement ?? $mouvement->incident?->code_evenement,
                optional($mouvement->date_mouvement)->format('Y-m-d'),
                $mouvement->type_mouvement,
                $mouvement->provinceProv?->nom_province ?? $mouvement->code_province_prov,
                $mouvement->territoireProv?->nom_territoire ?? $mouvement->code_territoire_prov,
                $mouvement->zoneSanteProv?->nom_zonesante ?? $mouvement->code_zonesante_prov,
                $mouvement->localite_prov,
                $mouvement->provinceAccl?->nom_province ?? $mouvement->code_province_accl,
                $mouvement->territoireAccl?->nom_territoire ?? $mouvement->code_territoire_accl,
                $mouvement->zoneSanteAccl?->nom_zonesante ?? $mouvement->code_zonesante_accl,
                $mouvement->localite_accl,
                (int) ($mouvement->estim_nbre_menages ?? 0),
                (int) ($mouvement->estim_nbre_personnes ?? 0),
                $mouvement->type_logement,
                $mouvement->source_info,
                $mouvement->remarques_mouvement,
                $mouvement->creator?->name ?? $mouvement->created_by ?? '-',
                optional($mouvement->created_at)->format('Y-m-d'),
                $mouvement->id,
                $mouvement->incident_id,
            ]);
    }
}
