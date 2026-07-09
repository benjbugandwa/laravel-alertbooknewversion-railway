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

class AnalysisReportService
{
    private const VICTIM_TOTAL_SQL = '
        COALESCE(victimes.nbre_femme_0a4ans, 0)
        + COALESCE(victimes.nbre_femme_5a11ans, 0)
        + COALESCE(victimes.nbre_femme_12a17ans, 0)
        + COALESCE(victimes.nbre_femme_18a59ans, 0)
        + COALESCE(victimes.nbre_femme_6Oansouplus, 0)
        + COALESCE(victimes.nbre_homme_0a4ans, 0)
        + COALESCE(victimes.nbre_homme_5a11ans, 0)
        + COALESCE(victimes.nbre_homme_12a17ans, 0)
        + COALESCE(victimes.nbre_homme_18a59ans, 0)
        + COALESCE(victimes.nbre_homme_6Oansouplus, 0)
    ';

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

        $hotZones = $this->hotZones($from, $to, $provinceCode, $territoireCode);
        $hotTerritories = $this->hotTerritories($from, $to, $provinceCode, $territoireCode);
        $violenceByZone = $this->violenceByZone($from, $to, $provinceCode, $territoireCode);
        $movements = $this->movements($from, $to, $provinceCode, $territoireCode);
        $eventTypes = $this->eventTypes($from, $to, $provinceCode, $territoireCode);

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
            'movements' => $movements->all(),
            'event_types' => $eventTypes->all(),
        ];
    }

    private function hotZones(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        $query = $this->baseIncidentQuery($from, $to, $provinceCode, $territoireCode)
            ->leftJoin('zonesantes', 'incidents.code_zonesante', '=', 'zonesantes.code_zonesante')
            ->selectRaw("COALESCE(zonesantes.nom_zonesante, incidents.code_zonesante, 'Non renseignee') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(20);

        return $this->withPercentages($query->get());
    }

    private function hotTerritories(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
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
        $query = DB::table('victimes')
            ->join('incidents', 'victimes.incident_id', '=', 'incidents.id')
            ->leftJoin('violences', 'victimes.violence_id', '=', 'violences.id')
            ->leftJoin('zonesantes', 'incidents.code_zonesante', '=', 'zonesantes.code_zonesante')
            ->selectRaw("COALESCE(zonesantes.nom_zonesante, incidents.code_zonesante, 'Non renseignee') as zone_label")
            ->selectRaw("COALESCE(violences.violence_name, 'Non renseignee') as violence_label")
            ->selectRaw('SUM('.self::VICTIM_TOTAL_SQL.') as total_victims');

        $this->applyIncidentScope($query, $from, $to, $provinceCode, $territoireCode);

        return $this->withPercentages(
            $query
                ->groupBy('zone_label', 'violence_label')
                ->orderByDesc('total_victims')
                ->limit(30)
                ->get()
                ->map(fn ($row) => (object) [
                    'label' => $row->zone_label.' / '.$row->violence_label,
                    'zone_label' => $row->zone_label,
                    'violence_label' => $row->violence_label,
                    'total' => (int) $row->total_victims,
                    'total_victims' => (int) $row->total_victims,
                ])
        );
    }

    private function movements(CarbonImmutable $from, CarbonImmutable $to, ?string $provinceCode, ?string $territoireCode): Collection
    {
        $query = DB::table('mouvements')
            ->join('incidents', 'mouvements.incident_id', '=', 'incidents.id')
            ->leftJoin('territoires as terr_prov', 'mouvements.code_territoire_prov', '=', 'terr_prov.code_territoire')
            ->leftJoin('zonesantes as zone_prov', 'mouvements.code_zonesante_prov', '=', 'zone_prov.code_zonesante')
            ->leftJoin('territoires as terr_accl', 'mouvements.code_territoire_accl', '=', 'terr_accl.code_territoire')
            ->leftJoin('zonesantes as zone_accl', 'mouvements.code_zonesante_accl', '=', 'zone_accl.code_zonesante')
            ->selectRaw("COALESCE(terr_prov.nom_territoire, mouvements.code_territoire_prov, '-') as territoire_prov")
            ->selectRaw("COALESCE(zone_prov.nom_zonesante, mouvements.code_zonesante_prov, '-') as zone_prov")
            ->selectRaw("COALESCE(terr_accl.nom_territoire, mouvements.code_territoire_accl, '-') as territoire_accl")
            ->selectRaw("COALESCE(zone_accl.nom_zonesante, mouvements.code_zonesante_accl, '-') as zone_accl")
            ->selectRaw("COALESCE(mouvements.type_mouvement, '-') as type_mouvement")
            ->selectRaw('SUM(COALESCE(mouvements.estim_nbre_menages, 0)) as households')
            ->selectRaw('SUM(COALESCE(mouvements.estim_nbre_personnes, 0)) as people')
            ->selectRaw('COUNT(*) as total');

        $this->applyIncidentScope($query, $from, $to, $provinceCode, $territoireCode);

        return $query
            ->groupBy('territoire_prov', 'zone_prov', 'territoire_accl', 'zone_accl', 'type_mouvement')
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
        $query = $this->baseIncidentQuery($from, $to, $provinceCode, $territoireCode)
            ->leftJoin('evenements', 'incidents.code_evenement', '=', 'evenements.code_evenement')
            ->selectRaw("COALESCE(evenements.nom_evenement, incidents.code_evenement, 'Non renseigne') as label, COUNT(*) as total")
            ->groupBy('label')
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
