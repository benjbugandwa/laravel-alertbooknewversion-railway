<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Incident;
use App\Models\Province;
use App\Models\Territoire;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalysisReportService
{
    private const FEMALE_VICTIM_COLUMNS = [
        'nbre_femme_0a4ans',
        'nbre_femme_5a11ans',
        'nbre_femme_12a17ans',
        'nbre_femme_18a59ans',
        'nbre_femme_6Oansouplus',
        'nbre_femme_60ansouplus',
    ];

    private const MALE_VICTIM_COLUMNS = [
        'nbre_homme_0a4ans',
        'nbre_homme_5a11ans',
        'nbre_homme_12a17ans',
        'nbre_homme_18a59ans',
        'nbre_homme_6Oansouplus',
        'nbre_homme_60ansouplus',
    ];

    public function build(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $provinceCode = $filters['province'] ?? null;
        $territoireCode = $filters['territoire'] ?? null;

        $province = $provinceCode
            ? Province::withoutGlobalScope('active')->where('code_province', $provinceCode)->first()
            : null;
        $territoire = $territoireCode
            ? Territoire::query()->where('code_territoire', $territoireCode)->first()
            : null;

        $warnings = [];
        $context = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'province' => $provinceCode,
            'territoire' => $territoireCode,
        ];

        $hotZones = $this->section('zones chaudes', fn () => $this->hotZones($from, $to, $provinceCode, $territoireCode), $context, $warnings);
        $hotTerritories = $this->section('carte des territoires', fn () => $this->hotTerritories($from, $to, $provinceCode, $territoireCode), $context, $warnings);
        $violenceByZone = $this->section('violences et victimes', fn () => $this->violenceByZone($from, $to, $provinceCode, $territoireCode), $context, $warnings);
        $movements = $this->section('mouvements des populations', fn () => $this->movements($from, $to, $provinceCode, $territoireCode), $context, $warnings);
        $eventTypes = $this->section('types evenements', fn () => $this->eventTypes($from, $to, $provinceCode, $territoireCode), $context, $warnings);

        return [
            'filters' => [
                'from' => $from,
                'to' => $to,
                'province_code' => $provinceCode,
                'province_name' => $province?->nom_province,
                'territoire_code' => $territoireCode,
                'territoire_name' => $territoire?->nom_territoire,
            ],
            'summary' => [
                'alerts' => (int) $hotZones->sum('total'),
                'health_zones' => (int) $hotZones->count(),
                'territories_on_map' => (int) $hotTerritories->count(),
                'victims' => (int) $violenceByZone->sum('total_victims'),
                'movement_households' => (int) $movements->sum('households'),
                'movement_people' => (int) $movements->sum('people'),
            ],
            'hot_zones' => $hotZones->all(),
            'hot_territories' => $this->withMapPositions($hotTerritories)->all(),
            'violence_by_zone' => $violenceByZone->all(),
            'violence_columns' => $this->violenceColumns($violenceByZone)->all(),
            'movements' => $movements->all(),
            'event_types' => $eventTypes->all(),
            'warnings' => $warnings,
        ];
    }

    private function hotZones(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        $zoneLabel = "COALESCE(zonesantes.nom_zonesante, incidents.code_zonesante, 'Non renseignee')";

        $query = $this->baseIncidentQuery($from, $to, $provinceCode, $territoireCode)
            ->leftJoin('zonesantes', 'incidents.code_zonesante', '=', 'zonesantes.code_zonesante')
            ->selectRaw($zoneLabel.' as label, COUNT(*) as total')
            ->groupByRaw($zoneLabel)
            ->orderByDesc('total')
            ->limit(20);

        return $this->withPercentages($query->get());
    }

    private function hotTerritories(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        if (! Schema::hasColumns('territoires', ['latitude', 'longitude'])) {
            return collect();
        }

        return $this->baseIncidentQuery($from, $to, $provinceCode, $territoireCode)
            ->join('territoires', 'incidents.code_territoire', '=', 'territoires.code_territoire')
            ->whereNotNull('territoires.latitude')
            ->whereNotNull('territoires.longitude')
            ->select([
                'territoires.code_territoire',
                'territoires.nom_territoire',
                'territoires.latitude',
                'territoires.longitude',
            ])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('territoires.code_territoire', 'territoires.nom_territoire', 'territoires.latitude', 'territoires.longitude')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'code' => (string) $row->code_territoire,
                'name' => (string) $row->nom_territoire,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'total' => (int) $row->total,
            ]);
    }

    private function violenceByZone(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        if (! Schema::hasTable('victimes')) {
            return collect();
        }

        $incidentColumn = $this->firstExistingColumn('victimes', ['incident_id', 'id_incident', 'alerte_id']);

        if ($incidentColumn === null) {
            return collect();
        }

        $zoneLabel = "COALESCE(zonesantes.nom_zonesante, incidents.code_zonesante, 'Non renseignee')";
        $violenceColumn = $this->firstExistingColumn('victimes', ['violence_id', 'id_violence']);
        $canJoinViolences = $violenceColumn !== null
            && Schema::hasTable('violences')
            && Schema::hasColumn('violences', 'id')
            && Schema::hasColumn('violences', 'violence_name');
        $violenceLabel = $canJoinViolences
            ? "COALESCE(violences.violence_name, {$this->textExpression('victimes.'.$violenceColumn)}, 'Non renseignee')"
            : ($violenceColumn ? "COALESCE({$this->textExpression('victimes.'.$violenceColumn)}, 'Non renseignee')" : "'Non renseignee'");
        $maleTotalSql = $this->victimTotalExpression(self::MALE_VICTIM_COLUMNS);
        $femaleTotalSql = $this->victimTotalExpression(self::FEMALE_VICTIM_COLUMNS);
        $victimTotalSql = "({$maleTotalSql}) + ({$femaleTotalSql})";

        $query = DB::table('victimes')
            ->join(
                'incidents',
                DB::raw($this->textExpression('victimes.'.$incidentColumn)),
                '=',
                DB::raw($this->textExpression('incidents.id'))
            )
            ->leftJoin('zonesantes', 'incidents.code_zonesante', '=', 'zonesantes.code_zonesante')
            ->selectRaw($zoneLabel.' as zone_label')
            ->selectRaw($violenceLabel.' as violence_label')
            ->selectRaw('SUM('.$maleTotalSql.') as male_victims')
            ->selectRaw('SUM('.$femaleTotalSql.') as female_victims')
            ->selectRaw('SUM('.$victimTotalSql.') as total_victims');

        if ($canJoinViolences) {
            $query->leftJoin(
                'violences',
                DB::raw($this->textExpression('victimes.'.$violenceColumn)),
                '=',
                DB::raw($this->textExpression('violences.id'))
            );
        }

        $this->applyIncidentScope($query, $from, $to, $provinceCode, $territoireCode);

        $rows = $query
            ->groupByRaw($zoneLabel)
            ->groupByRaw($violenceLabel)
            ->orderByDesc('total_victims')
            ->get();

        return $rows
            ->groupBy('zone_label')
            ->map(function (Collection $zoneRows, string $zoneLabel): array {
                $violences = $zoneRows
                    ->mapWithKeys(fn ($row): array => [
                        (string) $row->violence_label => [
                            'male' => (int) $row->male_victims,
                            'female' => (int) $row->female_victims,
                            'total' => (int) $row->total_victims,
                        ],
                    ])
                    ->all();

                return [
                    'zone_label' => $zoneLabel,
                    'violences' => $violences,
                    'total_male' => (int) $zoneRows->sum('male_victims'),
                    'total_female' => (int) $zoneRows->sum('female_victims'),
                    'total' => (int) $zoneRows->sum('total_victims'),
                    'total_victims' => (int) $zoneRows->sum('total_victims'),
                ];
            })
            ->sortByDesc('total_victims')
            ->take(20)
            ->values();
    }

    private function movements(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        $territoireProv = "COALESCE(terr_prov.nom_territoire, mouvements.code_territoire_prov, '-')";
        $zoneProv = "COALESCE(zone_prov.nom_zonesante, mouvements.code_zonesante_prov, '-')";
        $territoireAccl = "COALESCE(terr_accl.nom_territoire, mouvements.code_territoire_accl, '-')";
        $zoneAccl = "COALESCE(zone_accl.nom_zonesante, mouvements.code_zonesante_accl, '-')";
        $typeMouvement = "COALESCE({$this->textExpression('mouvements.type_mouvement')}, '-')";

        $query = DB::table('mouvements')
            ->join('incidents', 'mouvements.incident_id', '=', 'incidents.id')
            ->leftJoin('territoires as terr_prov', 'mouvements.code_territoire_prov', '=', 'terr_prov.code_territoire')
            ->leftJoin('zonesantes as zone_prov', 'mouvements.code_zonesante_prov', '=', 'zone_prov.code_zonesante')
            ->leftJoin('territoires as terr_accl', 'mouvements.code_territoire_accl', '=', 'terr_accl.code_territoire')
            ->leftJoin('zonesantes as zone_accl', 'mouvements.code_zonesante_accl', '=', 'zone_accl.code_zonesante')
            ->selectRaw($territoireProv.' as territoire_prov')
            ->selectRaw($zoneProv.' as zone_prov')
            ->selectRaw($territoireAccl.' as territoire_accl')
            ->selectRaw($zoneAccl.' as zone_accl')
            ->selectRaw($typeMouvement.' as type_mouvement')
            ->selectRaw('SUM(COALESCE(mouvements.estim_nbre_menages, 0)) as households')
            ->selectRaw('SUM(COALESCE(mouvements.estim_nbre_personnes, 0)) as people')
            ->selectRaw('COUNT(*) as total');

        $this->applyIncidentScope($query, $from, $to, $provinceCode, $territoireCode);

        return $query
            ->groupByRaw($territoireProv)
            ->groupByRaw($zoneProv)
            ->groupByRaw($territoireAccl)
            ->groupByRaw($zoneAccl)
            ->groupByRaw($typeMouvement)
            ->orderByDesc('people')
            ->limit(30)
            ->get()
            ->map(fn ($row): array => [
                'origin' => $row->territoire_prov.' / '.$row->zone_prov,
                'destination' => $row->territoire_accl.' / '.$row->zone_accl,
                'type' => (string) $row->type_mouvement,
                'households' => (int) $row->households,
                'people' => (int) $row->people,
                'total' => (int) $row->total,
            ]);
    }

    private function eventTypes(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        $eventLabel = "COALESCE(evenements.nom_evenement, incidents.code_evenement, 'Non renseigne')";

        $query = $this->baseIncidentQuery($from, $to, $provinceCode, $territoireCode)
            ->leftJoin('evenements', 'incidents.code_evenement', '=', 'evenements.code_evenement')
            ->selectRaw($eventLabel.' as label, COUNT(*) as total')
            ->groupByRaw($eventLabel)
            ->orderByDesc('total')
            ->limit(15);

        return $this->withPercentages($query->get());
    }

    private function baseIncidentQuery(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Builder
    {
        $query = DB::table('incidents');
        $this->applyIncidentScope($query, $from, $to, $provinceCode, $territoireCode);

        return $query;
    }

    private function section(string $label, callable $callback, array $context, array &$warnings): Collection
    {
        try {
            $result = $callback();

            return $result instanceof Collection ? $result : collect($result);
        } catch (Throwable $exception) {
            $warnings[] = $label;

            Log::error('Analysis report section failed', [
                'section' => $label,
                'filters' => $context,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    private function textExpression(string $column): string
    {
        return DB::getDriverName() === 'pgsql' ? $column.'::text' : $column;
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function victimTotalExpression(array $candidateColumns): string
    {
        $columns = collect($candidateColumns);

        $availableColumns = collect($candidateColumns)
            ->unique()
            ->filter(function (string $column) use ($columns): bool {
                if (
                    str_contains($column, '_60ansouplus')
                    && $columns->contains(str_replace('_60ansouplus', '_6Oansouplus', $column))
                    && Schema::hasColumn('victimes', str_replace('_60ansouplus', '_6Oansouplus', $column))
                ) {
                    return false;
                }

                return Schema::hasColumn('victimes', $column);
            })
            ->map(fn (string $column): string => 'COALESCE(victimes.'.$column.', 0)')
            ->values();

        if ($availableColumns->isEmpty()) {
            return '0';
        }

        return $availableColumns->implode(' + ');
    }

    private function violenceColumns(Collection $zoneRows): Collection
    {
        return $zoneRows
            ->flatMap(fn (array $zone): array => collect($zone['violences'])
                ->map(fn (array $counts, string $label): array => [
                    'label' => $label,
                    'male' => $counts['male'],
                    'female' => $counts['female'],
                    'total' => $counts['total'],
                ])
                ->all())
            ->groupBy('label')
            ->map(fn (Collection $rows, string $label): array => [
                'label' => $label,
                'male' => (int) $rows->sum('male'),
                'female' => (int) $rows->sum('female'),
                'total' => (int) $rows->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function applyIncidentScope(Builder $query, CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): void
    {
        $query
            ->where('incidents.statut_incident', Incident::STATUS_VALIDATED)
            ->whereBetween('incidents.date_incident', [$from, $to]);

        if ($provinceCode) {
            $query->where('incidents.code_province', $provinceCode);
        }

        if ($territoireCode) {
            $query->where('incidents.code_territoire', $territoireCode);
        }
    }

    private function withPercentages(Collection $rows): Collection
    {
        $total = (int) $rows->sum('total');

        return $rows
            ->map(fn ($row): array => array_merge((array) $row, [
                'total' => (int) $row->total,
                'percentage' => $total > 0 ? round(((int) $row->total / $total) * 100, 1) : 0.0,
            ]))
            ->values();
    }

    private function withMapPositions(Collection $territories): Collection
    {
        if ($territories->isEmpty()) {
            return $territories;
        }

        $minLat = (float) $territories->min('latitude');
        $maxLat = (float) $territories->max('latitude');
        $minLon = (float) $territories->min('longitude');
        $maxLon = (float) $territories->max('longitude');
        $maxTotal = max(1, (int) $territories->max('total'));

        return $territories->map(function (array $territory) use ($minLat, $maxLat, $minLon, $maxLon, $maxTotal): array {
            $x = $maxLon === $minLon ? 50 : 8 + ((($territory['longitude'] - $minLon) / ($maxLon - $minLon)) * 84);
            $y = $maxLat === $minLat ? 50 : 8 + (((($maxLat - $territory['latitude']) / ($maxLat - $minLat))) * 84);
            $radius = 4 + (sqrt($territory['total'] / $maxTotal) * 10);

            return array_merge($territory, [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'radius' => round($radius, 2),
            ]);
        });
    }
}
