<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IncidentsWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $province,
        public bool $includeSurvivantName = false,
        public bool $includeNotes = false,
        public bool $includeReferencements = false,
        public bool $includeViolences = true,
        public ?string $territoire = null,
        public bool $includeVictimes = true,
        public bool $includeReponses = true,
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new Sheets\IncidentsSheet(
            from: $this->from,
            to: $this->to,
            province: $this->province,
            includeSurvivantName: $this->includeSurvivantName,
            includeNotes: $this->includeNotes,
            includeViolences: $this->includeViolences,
            territoire: $this->territoire,
        );

        if ($this->includeViolences) {
            $sheets[] = new Sheets\ViolencesSheet(
                from: $this->from,
                to: $this->to,
                province: $this->province,
                territoire: $this->territoire,
            );
        }

        if ($this->includeVictimes) {
            $sheets[] = new Sheets\VictimesSheet(
                from: $this->from,
                to: $this->to,
                province: $this->province,
                territoire: $this->territoire,
            );
        }

        $sheets[] = new Sheets\MouvementsSheet(
            from: $this->from,
            to: $this->to,
            province: $this->province,
            territoire: $this->territoire,
        );

        if ($this->includeReponses) {
            $sheets[] = new Sheets\ReponsesSheet(
                from: $this->from,
                to: $this->to,
                province: $this->province,
                territoire: $this->territoire,
            );
        }

        if ($this->includeReferencements) {
            $sheets[] = new Sheets\ReferencementsSheet(
                from: $this->from,
                to: $this->to,
                province: $this->province,
                territoire: $this->territoire,
            );
        }

        return $sheets;
    }
}
