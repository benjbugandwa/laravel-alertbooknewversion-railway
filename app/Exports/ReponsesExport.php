<?php

namespace App\Exports;

use App\Models\Reponse;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReponsesExport implements FromQuery, WithMapping, WithHeadings, WithStyles, ShouldAutoSize
{
    use Exportable;

    protected ?string $startDate;
    protected ?string $endDate;
    protected ?string $incidentId;

    public function __construct(?string $startDate = null, ?string $endDate = null, ?string $incidentId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->incidentId = $incidentId;
    }

    public function query()
    {
        $query = Reponse::query()->with(['incident', 'creator']);

        if ($this->incidentId) {
            $query->where('alerte_id', $this->incidentId);
        }

        if ($this->startDate) {
            $query->whereDate('date_reponse', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('date_reponse', '<=', $this->endDate);
        }

        return $query->orderBy('date_reponse', 'desc');
    }

    public function headings(): array
    {
        return [
            'Numéro Réponse',
            'Numéro Incident (Alerte)',
            'Date Réponse',
            'Fournie Par',
            'Type Réponse',
            'Secteurs Couverts',
            'Nbre Ménages Couverts',
            'Nbre Individus Couverts',
            'Impact Réponse',
            'Observation / Gaps',
            'Créé le',
            'Créé par',
        ];
    }

    public function map($reponse): array
    {
        $secteurs = is_array($reponse->secteurs_couverts) 
            ? implode(', ', $reponse->secteurs_couverts) 
            : $reponse->secteurs_couverts;

        return [
            $reponse->num_reponse,
            $reponse->incident->code_incident ?? '-',
            optional($reponse->date_reponse)->format('d/m/Y') ?? '-',
            $reponse->fournie_par,
            $reponse->type_reponse,
            $secteurs ?? '-',
            $reponse->nbre_menages_couverts ?? 0,
            $reponse->nbre_individus_couverts ?? 0,
            $reponse->impact_reponse ?? '-',
            $reponse->observation_gap ?? '-',
            optional($reponse->create_at)->format('d/m/Y') ?? '-',
            $reponse->creator?->name ?? '-',
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
