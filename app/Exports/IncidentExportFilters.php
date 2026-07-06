<?php

namespace App\Exports;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;

class IncidentExportFilters
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $province = null,
        public ?string $territoire = null,
    ) {}

    public function applyToIncidentQuery(Builder $query): Builder
    {
        return $query
            ->whereDate('incidents.date_incident', '>=', $this->from)
            ->whereDate('incidents.date_incident', '<=', $this->to)
            ->where('incidents.statut_incident', Incident::STATUS_VALIDATED)
            ->when($this->province, fn (Builder $query) => $query->where('incidents.code_province', $this->province))
            ->when($this->territoire, fn (Builder $query) => $query->where('incidents.code_territoire', $this->territoire));
    }

    public function applyToRelatedIncidentQuery(Builder $query): Builder
    {
        return $this->applyToIncidentQuery($query);
    }
}
