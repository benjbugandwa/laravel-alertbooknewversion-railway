<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\FormatsWorksheetAsTable;
use App\Exports\IncidentExportFilters;
use App\Models\ViolenceIncident;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ViolencesSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
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
        return 'Violences';
    }

    public function headings(): array
    {
        return [
            'code_incident',
            'date_incident',
            'province',
            'territoire',
            'violence_id',
            'violence',
            'categorie_violence',
            'description_violence',
            'lien_violence_incident_id',
            'cree_par',
            'cree_le',
            'incident_id',
        ];
    }

    public function collection(): Collection
    {
        return ViolenceIncident::query()
            ->whereHas('incident', fn ($query) => $this->filters->applyToRelatedIncidentQuery($query))
            ->with(['incident.province', 'incident.territoire', 'violence', 'creator'])
            ->orderBy('id_incident')
            ->orderBy('id_violence')
            ->get()
            ->map(fn (ViolenceIncident $link): array => [
                $link->incident?->code_incident ?? '-',
                optional($link->incident?->date_incident)->format('Y-m-d'),
                $link->incident?->province?->nom_province ?? '-',
                $link->incident?->territoire?->nom_territoire ?? $link->incident?->code_territoire,
                $link->id_violence,
                $link->violence?->violence_name ?? '-',
                $link->violence?->categorie_name ?? '-',
                $link->description_violence,
                $link->id,
                $link->creator?->name ?? $link->created_by ?? '-',
                optional($link->created_at)->format('Y-m-d'),
                $link->id_incident,
            ]);
    }
}
