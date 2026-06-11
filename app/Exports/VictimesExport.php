<?php

namespace App\Exports;

use App\Models\Victime;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VictimesExport implements FromQuery, WithMapping, WithHeadings, WithStyles, ShouldAutoSize
{
    use Exportable;

    protected ?string $incidentId;

    public function __construct(?string $incidentId = null)
    {
        $this->incidentId = $incidentId;
    }

    public function query()
    {
        $query = Victime::query()
            ->with(['incident.province', 'incident.territoire', 'incident.zoneSante', 'violence', 'creator']);

        if ($this->incidentId) {
            $query->where('incident_id', $this->incidentId);
        }

        return $query->orderBy('create_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Code Incident',
            'Province',
            'Territoire',
            'Zone de Santé',
            'Localité',
            'Type de Violence',
            'Catégorie de Violence',
            'Profil de Victimes',
            'Femmes (0-4 ans)',
            'Femmes (5-11 ans)',
            'Femmes (12-17 ans)',
            'Femmes (18-59 ans)',
            'Femmes (60+ ans)',
            'Hommes (0-4 ans)',
            'Hommes (5-11 ans)',
            'Hommes (12-17 ans)',
            'Hommes (18-59 ans)',
            'Hommes (60+ ans)',
            'Description des faits',
            'Date d\'enregistrement',
            'Enregistré par',
        ];
    }

    public function map($victime): array
    {
        return [
            $victime->incident->code_incident ?? '-',
            $victime->incident->province?->nom_province ?? '-',
            $victime->incident->territoire?->nom_territoire ?? '-',
            $victime->incident->zoneSante?->nom_zonesante ?? '-',
            $victime->incident->localite ?? '-',
            $victime->violence?->violence_name ?? '-',
            $victime->violence?->categorie_name ?? '-',
            $victime->profile_victimes,
            $victime->nbre_femme_0a4ans ?? 0,
            $victime->nbre_femme_5a11ans ?? 0,
            $victime->nbre_femme_12a17ans ?? 0,
            $victime->nbre_femme_18a59ans ?? 0,
            $victime->nbre_femme_6Oansouplus ?? 0, // letter O!
            $victime->nbre_homme_0a4ans ?? 0,
            $victime->nbre_homme_5a11ans ?? 0,
            $victime->nbre_homme_12a17ans ?? 0,
            $victime->nbre_homme_18a59ans ?? 0,
            $victime->nbre_homme_6Oansouplus ?? 0, // letter O!
            $victime->description_faits,
            optional($victime->create_at)->format('d/m/Y') ?? '-',
            $victime->creator?->name ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F0FA']
                ]
            ],
        ];
    }
}
