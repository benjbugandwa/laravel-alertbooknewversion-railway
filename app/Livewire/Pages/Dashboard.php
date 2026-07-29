<?php

namespace App\Livewire\Pages;

use App\Models\Incident;
use App\Models\Province;
use App\Services\IncidentSlaService;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    private const INCIDENT_CACHE_VERSION = 'validated_v5';

    public int $days = 30;

    public string $selectedProvince = '';

    public string $selectedTerritoire = '';

    public array $provinces = [];

    public array $territoires = [];

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasRole('superadmin')) {
            $this->provinces = self::safeDashboardValue(function () {
                $query = Province::withoutGlobalScope('active')->orderBy('nom_province');

                if (self::tableHasColumn('provinces', 'is_active')) {
                    $query->where('is_active', 'YES');
                }

                return $query
                    ->get(['code_province', 'nom_province'])
                    ->map(fn (Province $province) => [
                        'code_province' => (string) $province->code_province,
                        'nom_province' => (string) $province->nom_province,
                    ])
                    ->all();
            }, []);
        }
    }

    public function updatedSelectedProvince(string $value): void
    {
        if ($value) {
            $this->territoires = self::safeDashboardValue(function () use ($value) {
                if (! self::tableHasColumns('territoires', ['code_province', 'code_territoire', 'nom_territoire'])) {
                    return [];
                }

                return DB::table('territoires')
                    ->where('code_province', $value)
                    ->orderBy('nom_territoire')
                    ->get(['code_territoire', 'nom_territoire'])
                    ->map(fn ($territoire) => [
                        'code_territoire' => (string) $territoire->code_territoire,
                        'nom_territoire' => (string) $territoire->nom_territoire,
                    ])
                    ->all();
            }, []);
        } else {
            $this->territoires = [];
        }

        $this->selectedTerritoire = '';
    }

    public function setDays(int $days): void
    {
        $this->days = max(7, min($days, 365));
    }

    public function render()
    {
        $slaService = app(IncidentSlaService::class);
        $user = Auth::user();
        $provinceName = null;
        $isSuper = $user->hasRole('superadmin');
        $days = $this->days;

        $provinceScope = $isSuper ? ($this->selectedProvince ?: null) : $user->code_province;
        $territoireScope = $isSuper ? ($this->selectedTerritoire ?: null) : null;
        $scopeSuffix = self::scopeCacheSuffix($provinceScope, $territoireScope, $days);

        if ($provinceScope) {
            $provinceName = self::safeDashboardValue(
                fn () => Province::withoutGlobalScope('active')
                    ->where('code_province', $provinceScope)
                    ->value('nom_province'),
                null
            );
        }

        [$usersActive, $usersPending] = [null, null];
        if ($isSuper) {
            $cacheKeyUsers = 'dashboard_users_'.($provinceScope ?: 'all');
            [$usersActive, $usersPending] = self::rememberDashboard($cacheKeyUsers, function () use ($provinceScope) {
                $usersActiveQuery = DB::table('users')->where('is_active', true);
                $usersPendingQuery = DB::table('users')->where('is_active', false);

                if ($provinceScope) {
                    $usersActiveQuery->where('code_province', $provinceScope);
                    $usersPendingQuery->where('code_province', $provinceScope);
                }

                return [
                    (int) $usersActiveQuery->count(),
                    (int) $usersPendingQuery->count(),
                ];
            }, [0, 0]);
        }

        $cacheKeyProvince = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_prov_'.$scopeSuffix;
        $byProvince = self::rememberDashboard($cacheKeyProvince, function () use ($provinceScope, $territoireScope, $days) {
            $provinceLabel = "COALESCE(provinces.nom_province, 'N/A')";
            $q = DB::table('incidents')
                ->leftJoin('provinces', 'incidents.code_province', '=', 'provinces.code_province')
                ->selectRaw($provinceLabel.' as label, COUNT(*) as total');
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q->groupByRaw($provinceLabel)->orderByDesc('total')->limit(15)->get();
        }, collect());

        $byProvinceTotal = (int) $byProvince->sum('total');
        $byProvinceTable = $byProvince->map(function ($row) use ($byProvinceTotal) {
            return [
                'label' => $row->label,
                'total' => (int) $row->total,
                'pct' => $byProvinceTotal > 0 ? round(($row->total / $byProvinceTotal) * 100, 1) : 0,
            ];
        })->values();

        $cacheKeyStatus = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_status_rate_'.$scopeSuffix;
        $statusSummary = self::rememberDashboard($cacheKeyStatus, function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->selectRaw(
                    'COUNT(*) as total,
                     SUM(CASE WHEN incidents.statut_incident = ? THEN 1 ELSE 0 END) as validated',
                    [Incident::STATUS_VALIDATED]
                );
            self::applyIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);
            $row = $q->first();
            $total = (int) ($row->total ?? 0);
            $validated = (int) ($row->validated ?? 0);
            $notValidated = max(0, $total - $validated);

            return [
                'total' => $total,
                'validated' => $validated,
                'not_validated' => $notValidated,
            ];
        }, [
            'total' => 0,
            'validated' => 0,
            'not_validated' => 0,
        ]);
        $validatedPercentage = $statusSummary['total'] > 0
            ? round(($statusSummary['validated'] / $statusSummary['total']) * 100, 1)
            : 0.0;

        $cacheKeyEventType = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_evt_'.$scopeSuffix;
        $byEventType = self::rememberDashboard($cacheKeyEventType, function () use ($provinceScope, $territoireScope, $days) {
            $eventLabel = "COALESCE(evenements.nom_evenement, incidents.code_evenement, 'N/A')";
            $q = DB::table('incidents')
                ->leftJoin('evenements', 'incidents.code_evenement', '=', 'evenements.code_evenement')
                ->selectRaw($eventLabel.' as label, COUNT(*) as total');
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q->groupByRaw($eventLabel)->orderByDesc('total')->limit(15)->get();
        }, collect());

        $cacheKeyEvo = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_evo_'.$scopeSuffix;
        $evolution = self::rememberDashboard($cacheKeyEvo, function () use ($provinceScope, $territoireScope, $days) {
            $incidentDay = 'DATE(incidents.date_incident)';
            $q = DB::table('incidents')
                ->selectRaw($incidentDay.' as d, COUNT(*) as total');
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q->groupByRaw($incidentDay)->orderBy('d')->get();
        }, collect());

        $cacheKeyTerritoryMap = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_territory_map_'.$scopeSuffix;
        $territoryMapRows = self::rememberDashboard($cacheKeyTerritoryMap, function () use ($provinceScope, $territoireScope, $days) {
            if (! self::tableHasColumns('territoires', ['code_territoire', 'nom_territoire', 'code_province', 'latitude', 'longitude'])) {
                return collect();
            }

            $q = DB::table('incidents')
                ->join('territoires', 'incidents.code_territoire', '=', 'territoires.code_territoire')
                ->leftJoin('provinces', 'territoires.code_province', '=', 'provinces.code_province')
                ->leftJoin('evenements', 'incidents.code_evenement', '=', 'evenements.code_evenement')
                ->whereNotNull('territoires.latitude')
                ->whereNotNull('territoires.longitude')
                ->select([
                    'territoires.code_territoire',
                    'territoires.nom_territoire',
                    'territoires.latitude',
                    'territoires.longitude',
                    'provinces.nom_province',
                ])
                ->selectRaw("COALESCE(evenements.nom_evenement, incidents.code_evenement, 'Non renseigne') as event_label, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q
                ->groupBy(
                    'territoires.code_territoire',
                    'territoires.nom_territoire',
                    'territoires.latitude',
                    'territoires.longitude',
                    'provinces.nom_province',
                    'evenements.nom_evenement',
                    'incidents.code_evenement'
                )
                ->orderBy('territoires.nom_territoire')
                ->orderByDesc('total')
                ->get();
        }, collect());

        $territoryPoints = $territoryMapRows
            ->groupBy('code_territoire')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'code_territoire' => (string) $first->code_territoire,
                    'nom_territoire' => (string) $first->nom_territoire,
                    'nom_province' => (string) ($first->nom_province ?? ''),
                    'latitude' => (float) $first->latitude,
                    'longitude' => (float) $first->longitude,
                    'total' => (int) $rows->sum('total'),
                    'events' => $rows
                        ->sortByDesc('total')
                        ->map(fn ($row) => [
                            'label' => (string) $row->event_label,
                            'total' => (int) $row->total,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $territoryMap = [
            'points' => $territoryPoints->all(),
            'total' => (int) $territoryPoints->sum('total'),
            'max' => (int) ($territoryPoints->max('total') ?? 0),
            'period_days' => $days,
        ];

        $operationalKpis = self::rememberDashboard('dashboard_operational_kpis_'.$scopeSuffix, function () use ($provinceScope, $territoireScope, $days) {
            $validatedIncidents = DB::table('incidents');
            self::applyValidatedIncidentScope($validatedIncidents, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($validatedIncidents, $days);
            $validatedIncidentCount = (int) $validatedIncidents->count();

            $respondedIncidentCount = self::countValidatedIncidentsWithRelatedRows(
                'reponses',
                'alerte_id',
                $provinceScope,
                $territoireScope,
                $days
            );

            $referredIncidentCount = self::countValidatedIncidentsWithRelatedRows(
                'referencements',
                'id_incident',
                $provinceScope,
                $territoireScope,
                $days
            );

            $victimCount = self::countVictimsForValidatedIncidents($provinceScope, $territoireScope, $days);
            $movementRow = self::movementSummaryForValidatedIncidents($provinceScope, $territoireScope, $days);
            $serviceProviders = self::countServiceProviders($provinceScope);

            return [
                'validated_incidents' => $validatedIncidentCount,
                'responded_incidents' => $respondedIncidentCount,
                'response_rate' => self::percentage($respondedIncidentCount, $validatedIncidentCount),
                'referred_incidents' => $referredIncidentCount,
                'referral_rate' => self::percentage($referredIncidentCount, $validatedIncidentCount),
                'victims_total' => $victimCount,
                'movement_households' => (int) ($movementRow->households ?? 0),
                'movement_people' => (int) ($movementRow->people ?? 0),
                'service_providers' => $serviceProviders,
            ];
        }, self::emptyOperationalKpis());

        $byOrganisation = self::rememberDashboard('dashboard_inc_by_org_'.$scopeSuffix, function () use ($provinceScope, $territoireScope, $days) {
            if (
                ! self::tableHasColumns('users', ['id', 'org_id'])
                || ! self::tableHasColumns('organisations', ['id', 'org_sigle', 'org_name'])
            ) {
                return collect();
            }

            $organisationLabel = "COALESCE(organisations.org_sigle, organisations.org_name, 'Non renseignee')";
            $q = DB::table('incidents')
                ->leftJoin('users', 'incidents.created_by', '=', 'users.id')
                ->leftJoin('organisations', 'users.org_id', '=', 'organisations.id')
                ->selectRaw($organisationLabel.' as label')
                ->selectRaw('SUM(CASE WHEN incidents.statut_incident = ? THEN 1 ELSE 0 END) as validated', [Incident::STATUS_VALIDATED])
                ->selectRaw("SUM(CASE WHEN incidents.statut_incident = 'En attente' THEN 1 ELSE 0 END) as pending");
            self::applyIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);
            $q->whereIn('incidents.statut_incident', [Incident::STATUS_VALIDATED, 'En attente']);

            return $q
                ->groupByRaw($organisationLabel)
                ->orderByRaw('SUM(CASE WHEN incidents.statut_incident IN (?, ?) THEN 1 ELSE 0 END) DESC', [Incident::STATUS_VALIDATED, 'En attente'])
                ->limit(12)
                ->get();
        }, collect());

        $chart = [
            'users' => [
                'active' => $usersActive,
                'pending' => $usersPending,
            ],
            'kpis' => $operationalKpis,
            'byProvince' => [
                'labels' => $byProvince->pluck('label')->values(),
                'data' => $byProvince->pluck('total')->map(fn ($total) => (int) $total)->values(),
                'table' => $byProvinceTable,
                'sum' => $byProvinceTotal,
            ],
            'byStatus' => [
                'labels' => collect(['Validées', 'Non validées']),
                'data' => collect([$statusSummary['validated'], $statusSummary['not_validated']]),
                'validated' => $statusSummary['validated'],
                'not_validated' => $statusSummary['not_validated'],
                'total' => $statusSummary['total'],
                'validatedPercentage' => $validatedPercentage,
            ],
            'byEventType' => [
                'labels' => $byEventType->pluck('label')->values(),
                'data' => $byEventType->pluck('total')->map(fn ($total) => (int) $total)->values(),
            ],
            'byOrganisation' => [
                'labels' => $byOrganisation->pluck('label')->values(),
                'validated' => $byOrganisation->pluck('validated')->map(fn ($total) => (int) $total)->values(),
                'pending' => $byOrganisation->pluck('pending')->map(fn ($total) => (int) $total)->values(),
            ],
            'evolution' => [
                'labels' => $evolution->pluck('d')->values(),
                'data' => $evolution->pluck('total')->map(fn ($total) => (int) $total)->values(),
            ],
            'territoryMap' => $territoryMap,
            'scope' => [
                'isSuper' => $isSuper,
                'code_province' => $provinceScope,
                'nom_province' => $provinceName,
                'code_territoire' => $territoireScope,
            ],
        ];
        $slaSummary = self::safeDashboardValue(
            fn () => $slaService->summary($provinceScope, $territoireScope),
            self::emptySlaSummary()
        );

        return view('livewire.pages.dashboard', [
            'chart' => $chart,
            'slaSummary' => $slaSummary,
        ]);
    }

    private static function scopeCacheSuffix(?string $provinceScope, ?string $territoireScope, int $days): string
    {
        return 'prov_'.($provinceScope ?: 'all').'_terr_'.($territoireScope ?: 'all').'_days_'.$days;
    }

    private static function percentage(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }

    private static function rememberDashboard(string $key, Closure $callback, mixed $fallback): mixed
    {
        try {
            return Cache::remember($key, now()->addMinutes(15), $callback);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return value($fallback);
        }
    }

    private static function safeDashboardValue(Closure $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return value($fallback);
        }
    }

    private static function emptyOperationalKpis(): array
    {
        return [
            'validated_incidents' => 0,
            'responded_incidents' => 0,
            'response_rate' => 0.0,
            'referred_incidents' => 0,
            'referral_rate' => 0.0,
            'victims_total' => 0,
            'movement_households' => 0,
            'movement_people' => 0,
            'service_providers' => 0,
        ];
    }

    private static function emptySlaSummary(): array
    {
        return [
            'validation' => 0,
            'response' => 0,
            'referral' => 0,
            'total_overdue_incidents' => 0,
        ];
    }

    private static function tableHasColumns(string $table, array $columns): bool
    {
        try {
            return self::tableExists($table) && Schema::hasColumns($table, $columns);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private static function tableHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private static function countValidatedIncidentsWithRelatedRows(
        string $table,
        string $incidentColumn,
        ?string $provinceScope,
        ?string $territoireScope,
        int $days
    ): int {
        if (! self::tableHasColumns($table, [$incidentColumn])) {
            return 0;
        }

        $query = DB::table('incidents');
        self::applyValidatedIncidentScope($query, $provinceScope, $territoireScope);
        self::applyIncidentPeriodScope($query, $days);

        $query->whereExists(function ($exists) use ($table, $incidentColumn) {
            $exists->selectRaw('1')->from($table);

            self::whereColumnMatchesIncidentId($exists, $table.'.'.$incidentColumn);
        });

        return (int) $query->count();
    }

    private static function countVictimsForValidatedIncidents(?string $provinceScope, ?string $territoireScope, int $days): int
    {
        if (! self::tableHasColumns('victimes', ['incident_id'])) {
            return 0;
        }

        $columns = collect([
            'nbre_femme_0a4ans',
            'nbre_femme_5a11ans',
            'nbre_femme_12a17ans',
            'nbre_femme_18a59ans',
            'nbre_femme_6Oansouplus',
            'nbre_homme_0a4ans',
            'nbre_homme_5a11ans',
            'nbre_homme_12a17ans',
            'nbre_homme_18a59ans',
            'nbre_homme_6Oansouplus',
        ])->filter(fn (string $column) => self::tableHasColumn('victimes', $column));

        if ($columns->isEmpty()) {
            return 0;
        }

        $sumExpression = $columns
            ->map(fn (string $column) => 'COALESCE(victimes.'.$column.', 0)')
            ->implode(' + ');

        $query = DB::table('victimes')
            ->join('incidents', function (JoinClause $join): void {
                self::joinColumnMatchesIncidentId($join, 'victimes.incident_id');
            })
            ->selectRaw('COALESCE(SUM('.$sumExpression.'), 0) as total');
        self::applyValidatedIncidentScope($query, $provinceScope, $territoireScope);
        self::applyIncidentPeriodScope($query, $days);

        $row = $query->first();

        return (int) ($row->total ?? 0);
    }

    private static function movementSummaryForValidatedIncidents(?string $provinceScope, ?string $territoireScope, int $days): object
    {
        if (! self::tableHasColumns('mouvements', ['date_mouvement', 'estim_nbre_menages', 'estim_nbre_personnes'])) {
            return (object) ['households' => 0, 'people' => 0];
        }

        $query = DB::table('mouvements')
            ->selectRaw('
                COALESCE(SUM(COALESCE(mouvements.estim_nbre_menages, 0)), 0) as households,
                COALESCE(SUM(COALESCE(mouvements.estim_nbre_personnes, 0)), 0) as people
            ')
            ->whereNotNull('mouvements.date_mouvement')
            ->where('mouvements.date_mouvement', '>=', now()->subDays($days)->startOfDay());

        if (self::tableHasColumns('mouvements', ['incident_id'])) {
            $query->leftJoin('incidents', function (JoinClause $join): void {
                self::joinColumnMatchesIncidentId($join, 'mouvements.incident_id');
            });
            self::applyMovementScope($query, $provinceScope, $territoireScope);
        } elseif ($provinceScope || $territoireScope) {
            self::applyStandaloneMovementScope($query, $provinceScope, $territoireScope);
        }

        return $query->first() ?? (object) ['households' => 0, 'people' => 0];
    }

    private static function countServiceProviders(?string $provinceScope): int
    {
        if (! self::tableExists('service_providers')) {
            return 0;
        }

        $query = DB::table('service_providers');

        if (
            $provinceScope
            && self::tableHasColumns('service_providers', ['created_by'])
            && self::tableHasColumns('users', ['id', 'code_province'])
        ) {
            $query
                ->leftJoin('users', 'service_providers.created_by', '=', 'users.id')
                ->where('users.code_province', $provinceScope);
        }

        return (int) $query->count();
    }

    private static function whereColumnMatchesIncidentId($query, string $relatedColumn): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw(self::textColumnComparison($relatedColumn, 'incidents.id'));

            return;
        }

        $query->whereColumn($relatedColumn, 'incidents.id');
    }

    private static function joinColumnMatchesIncidentId(JoinClause $join, string $relatedColumn): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $join->whereRaw(self::textColumnComparison($relatedColumn, 'incidents.id'));

            return;
        }

        $join->on($relatedColumn, '=', 'incidents.id');
    }

    private static function textColumnComparison(string $leftColumn, string $rightColumn): string
    {
        $grammar = DB::connection()->getQueryGrammar();

        return $grammar->wrap($leftColumn).'::text = '.$grammar->wrap($rightColumn).'::text';
    }

    private static function applyValidatedIncidentScope(
        Builder $query,
        ?string $provinceScope,
        ?string $territoireScope
    ): void {
        $query->where('incidents.statut_incident', Incident::STATUS_VALIDATED);

        self::applyIncidentScope($query, $provinceScope, $territoireScope);
    }

    private static function applyIncidentScope(
        Builder $query,
        ?string $provinceScope,
        ?string $territoireScope
    ): void {
        if ($provinceScope) {
            $query->where('incidents.code_province', $provinceScope);
        }

        if ($territoireScope) {
            $query->where('incidents.code_territoire', $territoireScope);
        }
    }

    private static function applyIncidentPeriodScope(Builder $query, int $days): void
    {
        $query
            ->whereNotNull('incidents.date_incident')
            ->where('incidents.date_incident', '>=', now()->subDays($days)->startOfDay());
    }

    private static function applyMovementScope(
        Builder $query,
        ?string $provinceScope,
        ?string $territoireScope
    ): void {
        $query->where(function ($query) use ($provinceScope, $territoireScope) {
            $query->where(function ($linked) use ($provinceScope, $territoireScope) {
                $linked
                    ->whereNotNull('mouvements.incident_id')
                    ->where('incidents.statut_incident', Incident::STATUS_VALIDATED);

                if ($provinceScope) {
                    $linked->where('incidents.code_province', $provinceScope);
                }

                if ($territoireScope) {
                    $linked->where('incidents.code_territoire', $territoireScope);
                }
            })->orWhere(function ($standalone) use ($provinceScope, $territoireScope) {
                $standalone->whereNull('mouvements.incident_id');

                if ($provinceScope) {
                    self::applyStandaloneProvinceMovementScope($standalone, $provinceScope);
                }

                if ($territoireScope) {
                    self::applyStandaloneTerritoryMovementScope($standalone, $territoireScope);
                }
            });
        });
    }

    private static function applyStandaloneMovementScope(
        Builder $query,
        ?string $provinceScope,
        ?string $territoireScope
    ): void {
        if ($provinceScope) {
            self::applyStandaloneProvinceMovementScope($query, $provinceScope);
        }

        if ($territoireScope) {
            self::applyStandaloneTerritoryMovementScope($query, $territoireScope);
        }
    }

    private static function applyStandaloneProvinceMovementScope(Builder $query, string $provinceScope): void
    {
        $columns = collect(['code_province_prov', 'code_province_accl'])
            ->filter(fn (string $column) => self::tableHasColumn('mouvements', $column))
            ->values();

        if ($columns->isEmpty()) {
            return;
        }

        $query->where(function ($province) use ($provinceScope, $columns) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $province->{$method}('mouvements.'.$column, $provinceScope);
            }
        });
    }

    private static function applyStandaloneTerritoryMovementScope(Builder $query, string $territoireScope): void
    {
        $columns = collect(['code_territoire_prov', 'code_territoire_accl'])
            ->filter(fn (string $column) => self::tableHasColumn('mouvements', $column))
            ->values();

        if ($columns->isEmpty()) {
            return;
        }

        $query->where(function ($territoire) use ($territoireScope, $columns) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $territoire->{$method}('mouvements.'.$column, $territoireScope);
            }
        });
    }
}
