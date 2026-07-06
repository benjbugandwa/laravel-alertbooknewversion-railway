<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\FormatsWorksheetAsTable;
use App\Exports\IncidentExportFilters;
use App\Models\Victime;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class VictimesSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
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
        return 'Victimes';
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
            'profil_victimes',
            'femmes_0_4_ans',
            'femmes_5_11_ans',
            'femmes_12_17_ans',
            'femmes_18_59_ans',
            'femmes_60_plus',
            'hommes_0_4_ans',
            'hommes_5_11_ans',
            'hommes_12_17_ans',
            'hommes_18_59_ans',
            'hommes_60_plus',
            'total_victimes',
            'description_faits',
            'cree_par',
            'cree_le',
            'victime_id',
            'incident_id',
        ];
    }

    public function collection(): Collection
    {
        return Victime::query()
            ->whereHas('incident', fn ($query) => $this->filters->applyToRelatedIncidentQuery($query))
            ->with(['incident.province', 'incident.territoire', 'violence', 'creator'])
            ->orderBy('incident_id')
            ->orderBy('violence_id')
            ->get()
            ->map(function (Victime $victime): array {
                $counts = [
                    (int) ($victime->nbre_femme_0a4ans ?? 0),
                    (int) ($victime->nbre_femme_5a11ans ?? 0),
                    (int) ($victime->nbre_femme_12a17ans ?? 0),
                    (int) ($victime->nbre_femme_18a59ans ?? 0),
                    (int) ($victime->nbre_femme_6Oansouplus ?? 0),
                    (int) ($victime->nbre_homme_0a4ans ?? 0),
                    (int) ($victime->nbre_homme_5a11ans ?? 0),
                    (int) ($victime->nbre_homme_12a17ans ?? 0),
                    (int) ($victime->nbre_homme_18a59ans ?? 0),
                    (int) ($victime->nbre_homme_6Oansouplus ?? 0),
                ];

                return [
                    $victime->incident?->code_incident ?? '-',
                    optional($victime->incident?->date_incident)->format('Y-m-d'),
                    $victime->incident?->province?->nom_province ?? '-',
                    $victime->incident?->territoire?->nom_territoire ?? $victime->incident?->code_territoire,
                    $victime->violence_id,
                    $victime->violence?->violence_name ?? '-',
                    $victime->violence?->categorie_name ?? '-',
                    $victime->profile_victimes,
                    ...$counts,
                    array_sum($counts),
                    $victime->description_faits,
                    $victime->creator?->name ?? $victime->created_by ?? '-',
                    optional($victime->create_at)->format('Y-m-d'),
                    $victime->id,
                    $victime->incident_id,
                ];
            });
    }
}
