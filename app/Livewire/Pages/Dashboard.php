<?php

namespace App\Livewire\Pages;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Query\Builder;
use Livewire\Component;
use App\Models\Incident;
use App\Models\Province;
use App\Services\IncidentSlaService;

class Dashboard extends Component
{
    private const INCIDENT_CACHE_VERSION = 'validated_v1';

    public int $days = 30; // période pour l’évolution (30 derniers jours)

    public string $selectedProvince = '';
    public string $selectedTerritoire = '';
    public array $provinces = [];
    public array $territoires = [];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user->hasRole('superadmin')) {
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

        // Scope: superadmin voit tout; sinon limité à la province du user
        $isSuper = $user->hasRole('superadmin');
        
        $provinceScope = $isSuper ? ($this->selectedProvince ?: null) : $user->code_province;
        $territoireScope = $isSuper ? ($this->selectedTerritoire ?: null) : null;

        if ($provinceScope) {
            $provinceName = Province::withoutGlobalScope('active')
                ->where('code_province', $provinceScope)
                ->value('nom_province');
        }

        // --------- KPI Users (Cache 15 min) ----------
        $cacheKeyUsers = "dashboard_users_" . ($provinceScope ?: 'all');
        list($usersActive, $usersPending) = Cache::remember($cacheKeyUsers, now()->addMinutes(15), function () use ($provinceScope) {
            $usersActiveQuery = DB::table('users')->where('is_active', true);
            $usersPendingQuery = DB::table('users')->where('is_active', false);

            if ($provinceScope) {
                $usersActiveQuery->where('code_province', $provinceScope);
                $usersPendingQuery->where('code_province', $provinceScope);
            }

            return [
                (int) $usersActiveQuery->count(),
                (int) $usersPendingQuery->count()
            ];
        });

        // --------- Incidents par province (Cache 15 min) ----------
        $cacheKeyProvince = self::INCIDENT_CACHE_VERSION . "_dashboard_inc_prov_" . ($provinceScope ?: 'all') . "_terr_" . ($territoireScope ?: 'all');
        $byProvince = Cache::remember($cacheKeyProvince, now()->addMinutes(15), function () use ($provinceScope, $territoireScope) {
            $q = DB::table('incidents')
                ->leftJoin('provinces', 'incidents.code_province', '=', 'provinces.code_province')
                ->selectRaw("COALESCE(provinces.nom_province, 'N/A') as label, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            return $q->groupBy('label')->orderByDesc('total')->limit(15)->get();
        });

        $byProvinceTotal = (int) $byProvince->sum('total');
        $byProvinceTable = $byProvince->map(function ($row) use ($byProvinceTotal) {
            $pct = $byProvinceTotal > 0 ? round(($row->total / $byProvinceTotal) * 100, 1) : 0;
            return [
                'label' => $row->label,
                'total' => (int) $row->total,
                'pct'   => $pct,
            ];
        })->values();

        // --------- Incidents par statut (Cache 15 min) ----------
        $cacheKeyStatus = self::INCIDENT_CACHE_VERSION . "_dashboard_inc_stat_" . ($provinceScope ?: 'all') . "_terr_" . ($territoireScope ?: 'all');
        $byStatus = Cache::remember($cacheKeyStatus, now()->addMinutes(15), function () use ($provinceScope, $territoireScope) {
            $q = DB::table('incidents')
                ->selectRaw("COALESCE(incidents.statut_incident, 'N/A') as label, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            return $q->groupBy('label')->orderByDesc('total')->get();
        });

        // --------- Incidents par type d'événement (Cache 15 min) ----------
        $cacheKeyEventType = self::INCIDENT_CACHE_VERSION . "_dashboard_inc_evt_" . ($provinceScope ?: 'all') . "_terr_" . ($territoireScope ?: 'all');
        $byEventType = Cache::remember($cacheKeyEventType, now()->addMinutes(15), function () use ($provinceScope, $territoireScope) {
            $q = DB::table('incidents')
                ->leftJoin('evenements', 'incidents.code_evenement', '=', 'evenements.code_evenement')
                ->selectRaw("COALESCE(evenements.nom_evenement, incidents.code_evenement, 'N/A') as label, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            return $q->groupBy('label')->orderByDesc('total')->limit(15)->get();
        });

        // --------- Evolution incidents (X jours) (Cache 15 min) ----------
        $days = $this->days;
        $cacheKeyEvo = self::INCIDENT_CACHE_VERSION . "_dashboard_inc_evo_" . ($provinceScope ?: 'all') . "_terr_" . ($territoireScope ?: 'all') . "_days_" . $days;
        $evolution = Cache::remember($cacheKeyEvo, now()->addMinutes(15), function () use ($provinceScope, $territoireScope, $days) {
            $q = DB::table('incidents')
                ->whereNotNull('incidents.date_incident')
                ->where('incidents.date_incident', '>=', now()->subDays($days)->startOfDay())
                ->selectRaw("DATE(incidents.date_incident) as d, COUNT(*) as total");
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            return $q->groupBy('d')->orderBy('d')->get();
        });

        // --------- Incidents par chefferie pour la carte (Cache 15 min) ----------
        $cacheKeyChefferie = self::INCIDENT_CACHE_VERSION . "_dashboard_inc_chef_" . ($provinceScope ?: 'all') . "_terr_" . ($territoireScope ?: 'all');
        $byChefferie = Cache::remember($cacheKeyChefferie, now()->addMinutes(15), function () use ($provinceScope, $territoireScope) {
            $q = DB::table('incidents')
                ->leftJoin('chefferies', 'incidents.code_chefferie', '=', 'chefferies.code_chefferie')
                ->selectRaw("chefferies.nom_chefferie as label, COUNT(*) as total")
                ->whereNotNull('chefferies.nom_chefferie');
            self::applyValidatedIncidentScope($q, $provinceScope, $territoireScope);
            return $q->groupBy('label')->get();
        });

        // Préparer datasets pour Chart.js
        $chart = [
            'users' => [
                'active' => $usersActive,
                'pending' => $usersPending,
            ],
            'byProvince' => [
                'labels' => $byProvince->pluck('label')->values(),
                'data' => $byProvince->pluck('total')->map(fn ($total) => (int) $total)->values(),
                'table' => $byProvinceTable,
                'sum'   => $byProvinceTotal,
            ],
            'byStatus' => [
                'labels' => $byStatus->pluck('label')->values(),
                'data' => $byStatus->pluck('total')->map(fn ($total) => (int) $total)->values(),
            ],
            'byEventType' => [
                'labels' => $byEventType->pluck('label')->values(),
                'data' => $byEventType->pluck('total')->map(fn ($total) => (int) $total)->values(),
            ],
            'evolution' => [
                'labels' => $evolution->pluck('d')->values(),
                'data' => $evolution->pluck('total')->map(fn ($total) => (int) $total)->values(),
            ],
            'byChefferie' => $byChefferie->mapWithKeys(function ($item) {
                return [strtolower(trim($item->label)) => (int) $item->total];
            })->toArray(),
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
            'slaOverdue' => $slaService->overdueIncidents($provinceScope, $territoireScope, 8),
        ]);
    }

    private static function applyValidatedIncidentScope(
        Builder $query,
        ?string $provinceScope,
        ?string $territoireScope
    ): void {
        $query->where('incidents.statut_incident', Incident::STATUS_VALIDATED);

        if ($provinceScope) {
            $query->where('incidents.code_province', $provinceScope);
        }

        if ($territoireScope) {
            $query->where('incidents.code_territoire', $territoireScope);
        }
    }
}
