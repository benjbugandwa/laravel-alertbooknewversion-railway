<?php

namespace App\Livewire\Pages;

use App\Models\Incident;
use App\Models\Province;
use App\Services\IncidentSlaService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    private const INCIDENT_CACHE_VERSION = 'validated_v4';

    public int $days = 30;

    public string $selectedProvince = '';

    public string $selectedTerritoire = '';

    public array $provinces = [];

    public array $territoires = [];

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasEffectiveRole('superadmin')) {
            $this->provinces = Province::query()
                ->orderBy('nom_province')
                ->get(['code_province', 'nom_province'])
                ->map(fn (Province $province) => [
                    'code_province' => (string) $province->code_province,
                    'nom_province' => (string) $province->nom_province,
                ])
                ->all();
        }
    }

    public function updatedSelectedProvince(string $value): void
    {
        if ($value) {
            $this->territoires = DB::table('territoires')
                ->where('code_province', $value)
                ->orderBy('nom_territoire')
                ->get(['code_territoire', 'nom_territoire'])
                ->map(fn ($territoire) => [
                    'code_territoire' => (string) $territoire->code_territoire,
                    'nom_territoire' => (string) $territoire->nom_territoire,
                ])
                ->all();
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
        $isSuper = $user->hasEffectiveRole('superadmin');
        $days = $this->days;

        $provinceScope = $isSuper ? ($this->selectedProvince ?: null) : $user->code_province;
        $territoireScope = $isSuper ? ($this->selectedTerritoire ?: null) : null;
        $scopeSuffix = self::scopeCacheSuffix($provinceScope, $territoireScope, $days);

        if ($provinceScope) {
            $provinceName = Province::withoutGlobalScope('active')
                ->where('code_province', $provinceScope)
                ->value('nom_province');
        }

        [$usersActive, $usersPending] = [null, null];
        if ($isSuper) {
            $cacheKeyUsers = 'dashboard_users_'.($provinceScope ?: 'all');
            [$usersActive, $usersPending] = Cache::remember($cacheKeyUsers, now()->addMinutes(15), function () use ($provinceScope) {
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
            });
        }

        $cacheKeyProvince = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_prov_'.$scopeSuffix;
        $byProvince = Cache::remember($cacheKeyProvince, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->leftJoin('provinces', 'incidents.code_province', '=', 'provinces.code_province')
                ->selectRaw("COALESCE(provinces.nom_province, 'N/A') as label, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q->groupBy('label')->orderByDesc('total')->limit(15)->get();
        });

        $byProvinceTotal = (int) $byProvince->sum('total');
        $byProvinceTable = $byProvince->map(function ($row) use ($byProvinceTotal) {
            return [
                'label' => $row->label,
                'total' => (int) $row->total,
                'pct' => $byProvinceTotal > 0 ? round(($row->total / $byProvinceTotal) * 100, 1) : 0,
            ];
        })->values();

        $cacheKeyStatus = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_status_rate_'.$scopeSuffix;
        $statusSummary = Cache::remember($cacheKeyStatus, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->selectRaw(
                    'SUM(CASE WHEN incidents.statut_incident = ? THEN 1 ELSE 0 END) as validated,
                     SUM(CASE WHEN incidents.statut_incident = ? THEN 1 ELSE 0 END) as pending',
                    [Incident::STATUS_VALIDATED, 'En attente']
                );
            self::applyIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);
            $row = $q->first();
            $validated = (int) ($row->validated ?? 0);
            $pending = (int) ($row->pending ?? 0);

            return [
                'total' => $validated + $pending,
                'validated' => $validated,
                'pending' => $pending,
            ];
        });
        $validatedPercentage = $statusSummary['pending'] === 0
            ? 100.0
            : ($statusSummary['total'] > 0 ? round(($statusSummary['validated'] / $statusSummary['total']) * 100, 1) : 0.0);

        $cacheKeyEventType = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_evt_'.$scopeSuffix;
        $byEventType = Cache::remember($cacheKeyEventType, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->leftJoin('evenements', 'incidents.code_evenement', '=', 'evenements.code_evenement')
                ->selectRaw("COALESCE(evenements.nom_evenement, incidents.code_evenement, 'N/A') as label, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q->groupBy('label')->orderByDesc('total')->limit(15)->get();
        });

        $cacheKeyEvo = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_evo_'.$scopeSuffix;
        $evolution = Cache::remember($cacheKeyEvo, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->selectRaw('DATE(incidents.date_incident) as d, COUNT(*) as total');
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);

            return $q->groupBy('d')->orderBy('d')->get();
        });

        $cacheKeyTerritoryMap = self::INCIDENT_CACHE_VERSION.'_dashboard_inc_territory_map_'.$scopeSuffix;
        $territoryMapRows = Cache::remember($cacheKeyTerritoryMap, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
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
        });

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

        $operationalKpis = Cache::remember('dashboard_operational_kpis_'.$scopeSuffix, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $validatedIncidents = DB::table('incidents');
            self::applyValidatedIncidentScope($validatedIncidents, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($validatedIncidents, $days);
            $validatedIncidentCount = (int) $validatedIncidents->count();

            $withResponses = DB::table('incidents');
            self::applyValidatedIncidentScope($withResponses, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($withResponses, $days);
            $withResponses->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('reponses')
                    ->whereColumn('reponses.alerte_id', 'incidents.id');
            });
            $respondedIncidentCount = (int) $withResponses->count();

            $withReferrals = DB::table('incidents');
            self::applyValidatedIncidentScope($withReferrals, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($withReferrals, $days);
            $withReferrals->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('referencements')
                    ->whereColumn('referencements.id_incident', 'incidents.id');
            });
            $referredIncidentCount = (int) $withReferrals->count();

            $victims = DB::table('victimes')
                ->join('incidents', 'victimes.incident_id', '=', 'incidents.id')
                ->selectRaw('
                    COALESCE(SUM(
                        COALESCE(victimes.nbre_femme_0a4ans, 0) +
                        COALESCE(victimes.nbre_femme_5a11ans, 0) +
                        COALESCE(victimes.nbre_femme_12a17ans, 0) +
                        COALESCE(victimes.nbre_femme_18a59ans, 0) +
                        COALESCE(victimes.nbre_femme_6Oansouplus, 0) +
                        COALESCE(victimes.nbre_homme_0a4ans, 0) +
                        COALESCE(victimes.nbre_homme_5a11ans, 0) +
                        COALESCE(victimes.nbre_homme_12a17ans, 0) +
                        COALESCE(victimes.nbre_homme_18a59ans, 0) +
                        COALESCE(victimes.nbre_homme_6Oansouplus, 0)
                    ), 0) as total
                ');
            self::applyValidatedIncidentScope($victims, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($victims, $days);
            $victimCount = (int) ($victims->value('total') ?? 0);

            $movements = DB::table('mouvements')
                ->leftJoin('incidents', 'mouvements.incident_id', '=', 'incidents.id')
                ->whereNotNull('mouvements.date_mouvement')
                ->where('mouvements.date_mouvement', '>=', now()->subDays($days)->startOfDay())
                ->selectRaw('
                    COALESCE(SUM(COALESCE(mouvements.estim_nbre_menages, 0)), 0) as households,
                    COALESCE(SUM(COALESCE(mouvements.estim_nbre_personnes, 0)), 0) as people
                ');
            self::applyMovementScope($movements, $provinceScope, $territoireScope);
            $movementRow = $movements->first();

            $providers = DB::table('service_providers')
                ->leftJoin('users', 'service_providers.created_by', '=', 'users.id');
            if ($provinceScope) {
                $providers->where('users.code_province', $provinceScope);
            }

            return [
                'validated_incidents' => $validatedIncidentCount,
                'responded_incidents' => $respondedIncidentCount,
                'response_rate' => self::percentage($respondedIncidentCount, $validatedIncidentCount),
                'referred_incidents' => $referredIncidentCount,
                'referral_rate' => self::percentage($referredIncidentCount, $validatedIncidentCount),
                'victims_total' => $victimCount,
                'movement_households' => (int) ($movementRow->households ?? 0),
                'movement_people' => (int) ($movementRow->people ?? 0),
                'service_providers' => (int) $providers->count(),
            ];
        });

        $byOrganisation = Cache::remember('dashboard_inc_by_org_'.$scopeSuffix, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->leftJoin('users', 'incidents.created_by', '=', 'users.id')
                ->leftJoin('organisations', 'users.org_id', '=', 'organisations.id')
                ->selectRaw("COALESCE(organisations.org_sigle, organisations.org_name, 'Non renseignee') as label")
                ->selectRaw('SUM(CASE WHEN incidents.statut_incident = ? THEN 1 ELSE 0 END) as validated', [Incident::STATUS_VALIDATED])
                ->selectRaw("SUM(CASE WHEN incidents.statut_incident = 'En attente' THEN 1 ELSE 0 END) as pending");
            self::applyIncidentScope($q, $provinceScope, $territoireScope);
            self::applyIncidentPeriodScope($q, $days);
            $q->whereIn('incidents.statut_incident', [Incident::STATUS_VALIDATED, 'En attente']);

            return $q
                ->groupBy('label')
                ->orderByRaw('SUM(CASE WHEN incidents.statut_incident IN (?, ?) THEN 1 ELSE 0 END) DESC', [Incident::STATUS_VALIDATED, 'En attente'])
                ->limit(12)
                ->get();
        });

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
                'labels' => collect(['Validées', 'En attente']),
                'data' => collect([$statusSummary['validated'], $statusSummary['pending']]),
                'validated' => $statusSummary['validated'],
                'pending' => $statusSummary['pending'],
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

        return view('livewire.pages.dashboard', [
            'chart' => $chart,
            'slaSummary' => $slaService->summary($provinceScope, $territoireScope),
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
                    $standalone->where(function ($province) use ($provinceScope) {
                        $province
                            ->where('mouvements.code_province_prov', $provinceScope)
                            ->orWhere('mouvements.code_province_accl', $provinceScope);
                    });
                }

                if ($territoireScope) {
                    $standalone->where(function ($territoire) use ($territoireScope) {
                        $territoire
                            ->where('mouvements.code_territoire_prov', $territoireScope)
                            ->orWhere('mouvements.code_territoire_accl', $territoireScope);
                    });
                }
            });
        });
    }
}
