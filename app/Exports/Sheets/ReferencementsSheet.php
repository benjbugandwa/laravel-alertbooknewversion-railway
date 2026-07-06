<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\FormatsWorksheetAsTable;
use App\Exports\IncidentExportFilters;
use App\Models\Referencement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReferencementsSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    use FormatsWorksheetAsTable;

    private IncidentExportFilters $filters;

    public function __construct(
        public string $from,
        public string $to,
        public ?string $province,
        public ?string $territoire = null,
    ) {
        $this->filters = new IncidentExportFilters($from, $to, $province, $territoire);
    }

    public function title(): string
    {
        return 'Referencements';
    }

    public function headings(): array
    {
        return [
            'code_incident',
            'date_incident',
            'province',
            'territoire',
            'code_referencement',
            'date_referencement',
            'type_reponse',
            'statut_reponse',
            'provider_name',
            'provider_location',
            'focalpoint_name',
            'focalpoint_number',
            'resultat',
            'observations',
            'referencement_id',
            'incident_id',
        ];
    }

    public function collection(): Collection
    {
        return Referencement::query()
            ->whereHas('incident', fn ($query) => $this->filters->applyToRelatedIncidentQuery($query))
            ->with(['incident.province', 'incident.territoire', 'provider'])
            ->orderBy('id_incident')
            ->orderBy('date_referencement')
            ->get()
            ->map(function (Referencement $referencement): array {
                $provider = $referencement->provider;

                return [
                    $referencement->incident?->code_incident ?? '-',
                    optional($referencement->incident?->date_incident)->format('Y-m-d'),
                    $referencement->incident?->province?->nom_province ?? '-',
                    $referencement->incident?->territoire?->nom_territoire ?? $referencement->incident?->code_territoire,
                    $referencement->code_referencement,
                    optional($referencement->date_referencement)->format('Y-m-d'),
                    $referencement->type_reponse,
                    $referencement->statut_reponse,
                    $provider?->provider_name ?? '-',
                    $provider?->provider_location ?? '-',
                    $provider?->focalpoint_name ?? '-',
                    $provider?->focalpoint_number ?? '-',
                    $referencement->resultat,
                    $referencement->observations,
                    $referencement->id,
                    $referencement->id_incident,
                ];
            });
    }
}
