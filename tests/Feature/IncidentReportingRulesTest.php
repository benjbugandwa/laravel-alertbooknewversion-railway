<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Exports\IncidentsWorkbookExport;
use App\Exports\Sheets\IncidentsSheet;
use App\Exports\Sheets\MouvementsSheet;
use App\Exports\Sheets\ReponsesSheet;
use App\Exports\Sheets\VictimesSheet;
use App\Exports\Sheets\ViolencesSheet;
use App\Livewire\Components\IncidentEditModal;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\Reponses\Index as ReponsesIndex;
use App\Models\Incident;
use App\Models\Reponse;
use App\Models\Role;
use App\Models\User;
use App\Models\Victime;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class IncidentReportingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        DB::table('provinces')->insert([
            'code_province' => 'P01',
            'nom_province' => 'Province test',
            'is_active' => 'YES',
        ]);
        DB::table('provinces')->insert([
            'code_province' => 'P02',
            'nom_province' => 'Autre province',
            'is_active' => 'YES',
        ]);
        DB::table('evenements')->insert([
            'code_evenement' => 'EVENT01',
            'nom_evenement' => 'Événement test',
        ]);
    }

    public function test_dashboard_validation_rate_uses_all_recorded_incidents_as_denominator(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        DB::table('territoires')->insert([
            'code_territoire' => 'T01',
            'nom_territoire' => 'Territoire test',
            'code_province' => 'P01',
        ]);

        $this->incidentFor($superadmin, Incident::STATUS_VALIDATED, 'ALT-VALIDATED');
        $this->incidentFor($superadmin, 'En attente', 'ALT-PENDING');
        $this->incidentFor($superadmin, 'Archivé', 'ALT-ARCHIVED');

        Livewire::actingAs($superadmin)
            ->test(Dashboard::class)
            ->call('setDays', 90)
            ->assertSet('days', 90)
            ->set('selectedProvince', 'P01')
            ->assertSet('territoires', [[
                'code_territoire' => 'T01',
                'nom_territoire' => 'Territoire test',
            ]])
            ->assertViewHas('chart', function (array $chart): bool {
                return $chart['byStatus']['labels']->all() === ['Validées', 'Non validées']
                    && $chart['byStatus']['data']->all() === [1, 2]
                    && $chart['byStatus']['validated'] === 1
                    && $chart['byStatus']['not_validated'] === 2
                    && $chart['byStatus']['total'] === 3
                    && $chart['byStatus']['validatedPercentage'] === 33.3
                    && $chart['byProvince']['labels']->all() === ['Province test']
                    && $chart['byProvince']['sum'] === 1
                    && $chart['evolution']['data']->sum() === 1;
            });
    }

    public function test_dashboard_validation_rate_counts_archived_incidents_as_not_validated(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        $this->incidentFor($superadmin, Incident::STATUS_VALIDATED, 'ALT-VALIDATED-ONLY');
        $this->incidentFor($superadmin, Incident::STATUS_ARCHIVED, 'ALT-ARCHIVED-ONLY');

        Livewire::actingAs($superadmin)
            ->test(Dashboard::class)
            ->assertViewHas('chart', function (array $chart): bool {
                return $chart['byStatus']['validated'] === 1
                    && $chart['byStatus']['not_validated'] === 1
                    && $chart['byStatus']['total'] === 2
                    && $chart['byStatus']['validatedPercentage'] === 50.0;
            });
    }

    public function test_dashboard_territory_map_uses_coordinates_and_event_breakdown_for_selected_period(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        DB::table('territoires')->insert([
            [
                'code_territoire' => 'TMAP',
                'nom_territoire' => 'Territoire carte',
                'code_province' => 'P01',
                'latitude' => -2.123456,
                'longitude' => 28.654321,
            ],
            [
                'code_territoire' => 'TNOCOORD',
                'nom_territoire' => 'Territoire sans coordonnees',
                'code_province' => 'P01',
                'latitude' => null,
                'longitude' => null,
            ],
        ]);
        DB::table('evenements')->insert([
            'code_evenement' => 'EVENT02',
            'nom_evenement' => 'Distribution test',
        ]);

        $this->incidentFor($superadmin, Incident::STATUS_VALIDATED, 'ALT-MAP-1', [
            'code_territoire' => 'TMAP',
            'code_evenement' => 'EVENT01',
        ]);
        $this->incidentFor($superadmin, Incident::STATUS_VALIDATED, 'ALT-MAP-2', [
            'code_territoire' => 'TMAP',
            'code_evenement' => 'EVENT02',
        ]);
        $this->incidentFor($superadmin, Incident::STATUS_VALIDATED, 'ALT-MAP-OLD', [
            'code_territoire' => 'TMAP',
            'date_incident' => now()->subDays(60),
        ]);
        $this->incidentFor($superadmin, 'En attente', 'ALT-MAP-PENDING', [
            'code_territoire' => 'TMAP',
        ]);
        $this->incidentFor($superadmin, Incident::STATUS_VALIDATED, 'ALT-MAP-NOCOORD', [
            'code_territoire' => 'TNOCOORD',
        ]);

        Livewire::actingAs($superadmin)
            ->test(Dashboard::class)
            ->call('setDays', 30)
            ->set('selectedProvince', 'P01')
            ->assertViewHas('chart', function (array $chart): bool {
                $map = $chart['territoryMap'];
                $point = $map['points'][0] ?? null;
                $events = collect($point['events'] ?? [])->pluck('total', 'label');

                return $map['total'] === 2
                    && $map['max'] === 2
                    && $map['period_days'] === 30
                    && count($map['points']) === 1
                    && $point['code_territoire'] === 'TMAP'
                    && $point['nom_territoire'] === 'Territoire carte'
                    && $point['latitude'] === -2.123456
                    && $point['longitude'] === 28.654321
                    && $point['total'] === 2
                    && $events->get('Distribution test') === 1
                    && $events->sum() === 2;
            });
    }

    public function test_dashboard_operational_kpis_follow_user_scope_and_period(): void
    {
        $admin = $this->userWithRole('admin');

        $organisationId = DB::table('organisations')->insertGetId([
            'org_sigle' => 'ORG',
            'org_name' => 'Organisation test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin->forceFill(['org_id' => $organisationId])->save();

        DB::table('territoires')->insert([
            'code_territoire' => 'TKPI',
            'nom_territoire' => 'Territoire KPI',
            'code_province' => 'P01',
        ]);
        DB::table('zonesantes')->insert([
            'code_zonesante' => 'ZKPI',
            'nom_zonesante' => 'Zone KPI',
            'code_territoire' => 'TKPI',
        ]);
        DB::table('airesantes')->insert([
            'code_airesante' => 'AKPI',
            'nom_airesante' => 'Aire KPI',
            'code_zonesante' => 'ZKPI',
        ]);
        DB::table('violences')->insert([
            'id' => 9901,
            'violence_name' => 'Violence KPI',
            'categorie_name' => 'Categorie KPI',
        ]);

        $withResponse = $this->incidentFor($admin, Incident::STATUS_VALIDATED, 'ALT-KPI-1', [
            'code_territoire' => 'TKPI',
            'code_zonesante' => 'ZKPI',
            'code_airesante' => 'AKPI',
        ]);
        $withoutResponse = $this->incidentFor($admin, Incident::STATUS_VALIDATED, 'ALT-KPI-2', [
            'code_territoire' => 'TKPI',
            'code_zonesante' => 'ZKPI',
            'code_airesante' => 'AKPI',
        ]);
        $this->incidentFor($admin, 'En attente', 'ALT-KPI-PENDING', [
            'code_territoire' => 'TKPI',
            'code_zonesante' => 'ZKPI',
            'code_airesante' => 'AKPI',
        ]);
        $pendingWithVictims = $this->incidentFor($admin, 'En attente', 'ALT-KPI-PENDING-VICTIMS', [
            'code_territoire' => 'TKPI',
            'code_zonesante' => 'ZKPI',
            'code_airesante' => 'AKPI',
        ]);
        $this->incidentFor($admin, Incident::STATUS_VALIDATED, 'ALT-KPI-OLD', [
            'code_territoire' => 'TKPI',
            'date_incident' => now()->subDays(60),
        ]);

        Reponse::create([
            'num_reponse' => 'REP-KPI-1',
            'date_reponse' => now()->toDateString(),
            'fournie_par' => 'Organisation test',
            'type_reponse' => 'Humanitaire',
            'secteurs_couverts' => ['Protection'],
            'alerte_id' => $withResponse->id,
            'created_by' => $admin->id,
        ]);

        $providerId = DB::table('service_providers')->insertGetId([
            'provider_name' => 'Structure KPI',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherProvinceUser = $this->userWithRole('admin');
        $otherProvinceUser->forceFill(['code_province' => 'P02'])->save();
        DB::table('service_providers')->insert([
            'provider_name' => 'Structure localisee KPI',
            'provider_location' => json_encode(['P01']),
            'created_by' => $otherProvinceUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('referencements')->insert([
            'id' => (string) Str::uuid(),
            'code_referencement' => 'REF-KPI-1',
            'id_incident' => $withResponse->id,
            'date_referencement' => now(),
            'provider_id' => $providerId,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Victime::create([
            'incident_id' => $withResponse->id,
            'violence_id' => 9901,
            'profile_victimes' => 'Residents',
            'nbre_femme_18a59ans' => 2,
            'nbre_homme_18a59ans' => 3,
            'description_faits' => 'Victimes KPI',
            'create_at' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);
        Victime::create([
            'incident_id' => $pendingWithVictims->id,
            'violence_id' => 9901,
            'profile_victimes' => 'Residents',
            'nbre_femme_18a59ans' => 4,
            'nbre_homme_18a59ans' => 6,
            'description_faits' => 'Victimes KPI en attente',
            'create_at' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        DB::table('mouvements')->insert([
            'date_mouvement' => now()->toDateString(),
            'type_mouvement' => 'Retour',
            'source_info' => 'Source KPI',
            'code_province_prov' => 'P01',
            'code_territoire_prov' => 'TKPI',
            'code_zonesante_prov' => 'ZKPI',
            'localite_prov' => 'Depart',
            'code_province_accl' => 'P01',
            'code_territoire_accl' => 'TKPI',
            'code_zonesante_accl' => 'ZKPI',
            'localite_accl' => 'Accueil',
            'type_logement' => 'Famille accueil',
            'created_by' => $admin->id,
            'estim_nbre_menages' => 4,
            'estim_nbre_personnes' => 19,
            'incident_id' => $withResponse->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('mouvements')->insert([
            'date_mouvement' => now()->toDateString(),
            'type_mouvement' => 'Fuite',
            'source_info' => 'Source KPI autonome',
            'code_province_prov' => 'P02',
            'code_territoire_prov' => 'TKPI',
            'code_zonesante_prov' => 'ZKPI',
            'localite_prov' => 'Depart autonome',
            'code_province_accl' => 'P01',
            'code_territoire_accl' => 'TKPI',
            'code_zonesante_accl' => 'ZKPI',
            'localite_accl' => 'Accueil autonome',
            'type_logement' => 'Famille accueil',
            'created_by' => $admin->id,
            'estim_nbre_menages' => 6,
            'estim_nbre_personnes' => 30,
            'incident_id' => null,
            'cause_deplacement' => 'Cause autonome',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewMissing('Utilisateurs actifs')
            ->assertViewMissing('En attente d’activation')
            ->assertViewHas('chart', function (array $chart): bool {
                $orgIndex = collect($chart['byOrganisation']['labels'])->search('ORG');

                return $chart['scope']['isSuper'] === false
                    && $chart['scope']['code_province'] === 'P01'
                    && $chart['users']['active'] === null
                    && $chart['users']['pending'] === null
                    && $chart['kpis']['validated_incidents'] === 2
                    && $chart['kpis']['responded_incidents'] === 1
                    && $chart['kpis']['response_rate'] === 50.0
                    && $chart['kpis']['referred_incidents'] === 1
                    && $chart['kpis']['referral_rate'] === 50.0
                    && $chart['kpis']['victims_total'] === 15
                    && $chart['kpis']['movement_households'] === 10
                    && $chart['kpis']['movement_people'] === 49
                    && $chart['kpis']['service_providers'] === 2
                    && $orgIndex !== false
                    && $chart['byOrganisation']['validated'][$orgIndex] === 2
                    && $chart['byOrganisation']['pending'][$orgIndex] === 2;
            });
    }

    public function test_incident_export_only_contains_validated_incidents(): void
    {
        $user = $this->userWithRole('admin');
        $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-VALIDATED');
        $this->incidentFor($user, 'En attente', 'ALT-PENDING');

        $rows = (new IncidentsSheet(
            from: now()->subDay()->toDateString(),
            to: now()->addDay()->toDateString(),
            province: 'P01',
            includeSurvivantName: false,
            includeNotes: false,
            includeViolences: false,
        ))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('ALT-VALIDATED', $rows->first()[0]);
        $this->assertSame('Province test', $rows->first()[2]);
    }

    public function test_incident_export_detail_sheets_keep_validated_incident_scope(): void
    {
        $user = $this->userWithRole('admin');

        DB::table('territoires')->insert([
            'code_territoire' => 'TEXP',
            'nom_territoire' => 'Territoire export',
            'code_province' => 'P01',
        ]);
        DB::table('zonesantes')->insert([
            'code_zonesante' => 'ZEXP',
            'nom_zonesante' => 'Zone export',
            'code_territoire' => 'TEXP',
        ]);
        DB::table('airesantes')->insert([
            'code_airesante' => 'AEXP',
            'nom_airesante' => 'Aire export',
            'code_zonesante' => 'ZEXP',
        ]);
        DB::table('violences')->insert([
            ['id' => 1001, 'violence_name' => 'Violence A', 'categorie_name' => 'Categorie A'],
            ['id' => 1002, 'violence_name' => 'Violence B', 'categorie_name' => 'Categorie B'],
        ]);

        $validated = $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-EXPORT', [
            'code_territoire' => 'TEXP',
            'code_zonesante' => 'ZEXP',
            'code_airesante' => 'AEXP',
        ]);
        $pending = $this->incidentFor($user, 'En attente', 'ALT-PENDING-EXPORT', [
            'code_territoire' => 'TEXP',
            'code_zonesante' => 'ZEXP',
            'code_airesante' => 'AEXP',
        ]);

        DB::table('violence_incidents')->insert([
            [
                'id' => 9101,
                'id_incident' => $validated->id,
                'id_violence' => 1001,
                'description_violence' => 'Detail A',
                'created_by' => $user->id,
                'created_at' => now(),
            ],
            [
                'id' => 9102,
                'id_incident' => $validated->id,
                'id_violence' => 1002,
                'description_violence' => 'Detail B',
                'created_by' => $user->id,
                'created_at' => now(),
            ],
            [
                'id' => 9103,
                'id_incident' => $pending->id,
                'id_violence' => 1001,
                'description_violence' => 'Pending detail',
                'created_by' => $user->id,
                'created_at' => now(),
            ],
        ]);

        Victime::create([
            'incident_id' => $validated->id,
            'violence_id' => 1001,
            'profile_victimes' => 'Residents',
            'nbre_femme_18a59ans' => 2,
            'nbre_homme_18a59ans' => 3,
            'description_faits' => 'Victimes validees',
            'create_at' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        Victime::create([
            'incident_id' => $pending->id,
            'violence_id' => 1001,
            'profile_victimes' => 'Residents',
            'description_faits' => 'Victimes en attente',
            'create_at' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        Reponse::create([
            'num_reponse' => 'REP-EXPORT-1',
            'date_reponse' => now()->toDateString(),
            'fournie_par' => 'Organisation',
            'type_reponse' => 'Humanitaire',
            'secteurs_couverts' => ['Sante', 'Protection'],
            'nbre_menages_couverts' => 4,
            'nbre_individus_couverts' => 12,
            'alerte_id' => $validated->id,
            'create_at' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        Reponse::create([
            'num_reponse' => 'REP-EXPORT-2',
            'date_reponse' => now()->toDateString(),
            'fournie_par' => 'Organisation',
            'type_reponse' => 'Humanitaire',
            'secteurs_couverts' => ['Sante'],
            'alerte_id' => $pending->id,
            'create_at' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        DB::table('mouvements')->insert([
            [
                'date_mouvement' => now()->toDateString(),
                'type_mouvement' => 'Retour',
                'source_info' => 'Source mouvement valide',
                'code_province_prov' => 'P01',
                'code_territoire_prov' => 'TEXP',
                'code_zonesante_prov' => 'ZEXP',
                'localite_prov' => 'Localite provenance',
                'code_province_accl' => 'P01',
                'code_territoire_accl' => 'TEXP',
                'code_zonesante_accl' => 'ZEXP',
                'localite_accl' => 'Localite accueil',
                'type_logement' => 'Famille accueil',
                'created_by' => $user->id,
                'estim_nbre_menages' => 7,
                'estim_nbre_personnes' => 28,
                'remarques_mouvement' => 'Mouvement valide',
                'incident_id' => $validated->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'date_mouvement' => now()->toDateString(),
                'type_mouvement' => 'Fuite',
                'source_info' => 'Source mouvement attente',
                'code_province_prov' => 'P01',
                'code_territoire_prov' => 'TEXP',
                'code_zonesante_prov' => 'ZEXP',
                'localite_prov' => 'Localite provenance attente',
                'code_province_accl' => 'P01',
                'code_territoire_accl' => 'TEXP',
                'code_zonesante_accl' => 'ZEXP',
                'localite_accl' => 'Localite accueil attente',
                'type_logement' => null,
                'created_by' => $user->id,
                'estim_nbre_menages' => null,
                'estim_nbre_personnes' => null,
                'remarques_mouvement' => null,
                'incident_id' => $pending->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $violenceRows = (new ViolencesSheet($from, $to, 'P01', 'TEXP'))->collection();
        $victimeRows = (new VictimesSheet($from, $to, 'P01', 'TEXP'))->collection();
        $mouvementRows = (new MouvementsSheet($from, $to, 'P01', 'TEXP'))->collection();
        $reponseRows = (new ReponsesSheet($from, $to, 'P01', 'TEXP'))->collection();

        $this->assertCount(2, $violenceRows);
        $this->assertSame(['ALT-EXPORT'], $violenceRows->pluck(0)->unique()->values()->all());
        $this->assertCount(1, $victimeRows);
        $this->assertSame('Zone export', $victimeRows->first()[4]);
        $this->assertSame('Aire export', $victimeRows->first()[5]);
        $this->assertSame(5, $victimeRows->first()[20]);
        $this->assertCount(1, $mouvementRows);
        $this->assertSame('ALT-EXPORT', $mouvementRows->first()[0]);
        $this->assertSame(28, $mouvementRows->first()[18]);
        $this->assertCount(1, $reponseRows);
        $this->assertSame('REP-EXPORT-1', $reponseRows->first()[4]);

        $xlsx = Excel::raw(new IncidentsWorkbookExport(
            from: $from,
            to: $to,
            province: 'P01',
            includeViolences: true,
            territoire: 'TEXP',
            includeVictimes: true,
            includeReponses: true,
        ), ExcelFormat::XLSX);

        $this->assertNotEmpty($xlsx);
    }

    public function test_incident_export_rejects_equal_start_and_end_dates(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->get(route('exports.incidents', [
                'from' => '2026-07-01',
                'to' => '2026-07-01',
            ]))
            ->assertSessionHasErrors('to');
    }

    public function test_response_can_be_created_without_incident(): void
    {
        $admin = $this->userWithRole('admin');

        Livewire::actingAs($admin)
            ->test(ReponsesIndex::class)
            ->set('standaloneMode', true)
            ->set('incident', null)
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->set('form.date_reponse', now()->toDateString())
            ->set('form.fournie_par', 'Organisation autonome')
            ->set('form.type_reponse', 'Humanitaire')
            ->set('form.secteurs_couverts', ['Protection'])
            ->set('form.nbre_menages_couverts', 5)
            ->set('form.nbre_individus_couverts', 20)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('reponses', [
            'fournie_par' => 'Organisation autonome',
            'alerte_id' => null,
            'nbre_menages_couverts' => 5,
            'nbre_individus_couverts' => 20,
        ]);
    }

    public function test_response_incident_selector_displays_the_selected_incident_without_redirecting(): void
    {
        $admin = $this->userWithRole('admin');
        $selectedIncident = $this->incidentFor($admin, Incident::STATUS_VALIDATED, 'ALT-RESPONSES');

        DB::table('reponses')->insert([
            'num_reponse' => 'REP-SELECTOR',
            'date_reponse' => now()->toDateString(),
            'fournie_par' => 'Organisation sélectionnée',
            'type_reponse' => 'Humanitaire',
            'secteurs_couverts' => json_encode(['Protection']),
            'alerte_id' => $selectedIncident->id,
            'create_at' => now()->toDateString(),
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ReponsesIndex::class)
            ->set('selectedIncidentId', $selectedIncident->id)
            ->assertNoRedirect()
            ->assertSet('incident.id', $selectedIncident->id)
            ->assertSee('Organisation sélectionnée');
    }

    public function test_unassigned_incident_without_violence_is_assigned_to_supervisor_when_validated(): void
    {
        $supervisor = $this->userWithRole('superviseur');
        $incident = $this->incidentFor($supervisor, 'En attente', 'ALT-UNASSIGNED');

        $validated = app(IncidentService::class)->validateIncident($incident, $supervisor, '127.0.0.1');

        $this->assertSame(0, $validated->violences()->count());
        $this->assertSame(Incident::STATUS_VALIDATED, $validated->statut_incident);
        $this->assertSame($supervisor->id, $validated->assigned_to);
        $this->assertSame($supervisor->id, $validated->assigned_by);
        $this->assertNotNull($validated->assigned_at);
        $this->assertDatabaseHas('audit_logs', [
            'model_id' => $incident->id,
            'user_action' => 'incident_assigned',
        ]);
    }

    public function test_supervisor_cannot_validate_incident_assigned_to_another_supervisor(): void
    {
        $assignedSupervisor = $this->userWithRole('superviseur');
        $otherSupervisor = $this->userWithRole('superviseur');
        $incident = $this->incidentFor($assignedSupervisor, 'En attente', 'ALT-ASSIGNED', [
            'assigned_to' => $assignedSupervisor->id,
            'assigned_by' => $assignedSupervisor->id,
            'assigned_at' => now(),
        ]);
        try {
            app(IncidentService::class)->validateIncident($incident, $otherSupervisor, '127.0.0.1');
            $this->fail('La validation aurait dû être refusée.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(
                'Seul le superviseur assigné peut valider cet incident.',
                $exception->getMessage()
            );
        }

        $incident->refresh();
        $this->assertSame('En attente', $incident->statut_incident);
        $this->assertSame($assignedSupervisor->id, $incident->assigned_to);
    }

    public function test_standard_edit_cannot_change_incident_status(): void
    {
        $admin = $this->userWithRole('admin');
        $incident = $this->incidentFor($admin, 'En attente', 'ALT-EDIT-STATUS');

        app(IncidentService::class)->update(
            incident: $incident,
            payload: [
                'statut_incident' => Incident::STATUS_VALIDATED,
                'localite' => 'Localité modifiée',
            ],
            photo: null,
            actor: $admin,
            ipAddress: '127.0.0.1'
        );

        $incident->refresh();
        $this->assertSame('En attente', $incident->statut_incident);
        $this->assertSame('Localité modifiée', $incident->localite);
    }

    public function test_pending_incident_can_be_edited_from_detail_modal_but_validated_incident_cannot(): void
    {
        $admin = $this->userWithRole('admin');
        $pending = $this->incidentFor($admin, 'En attente', 'ALT-EDIT-PENDING');

        Livewire::actingAs($admin)
            ->test(IncidentEditModal::class)
            ->dispatch('openIncidentEdit', incidentId: $pending->id)
            ->assertSet('open', true)
            ->set('form.localite', 'Nouvelle localité')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false);

        $this->assertSame('Nouvelle localité', $pending->fresh()->localite);

        $validated = $this->incidentFor($admin, Incident::STATUS_VALIDATED, 'ALT-EDIT-VALIDATED');

        Livewire::actingAs($admin)
            ->test(IncidentEditModal::class)
            ->dispatch('openIncidentEdit', incidentId: $validated->id)
            ->assertSet('open', false);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create([
            'code_province' => 'P01',
            'is_active' => true,
        ]);
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
        $user->roles()->attach($role);

        return $user->refresh();
    }

    private function incidentFor(User $creator, string $status, string $code, array $attributes = []): Incident
    {
        return Incident::create(array_merge([
            'id' => (string) Str::uuid(),
            'code_incident' => $code,
            'date_incident' => now(),
            'created_by' => $creator->id,
            'code_province' => 'P01',
            'code_evenement' => 'EVENT01',
            'statut_incident' => $status,
            'severite' => 'Faible',
            'source_info' => 'Population locale',
            'confidentiality_level' => 'Standard',
            'created_at' => now(),
            'last_status_changed_at' => now(),
        ], $attributes));
    }
}
