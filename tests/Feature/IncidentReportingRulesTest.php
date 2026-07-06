<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Exports\IncidentsWorkbookExport;
use App\Exports\Sheets\IncidentsSheet;
use App\Exports\Sheets\ReponsesSheet;
use App\Exports\Sheets\VictimesSheet;
use App\Exports\Sheets\ViolencesSheet;
use App\Livewire\Components\IncidentEditModal;
use App\Livewire\Pages\Dashboard;
use App\Models\Incident;
use App\Models\Reponse;
use App\Models\Role;
use App\Models\User;
use App\Models\Victime;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        DB::table('provinces')->insert([
            'code_province' => 'P01',
            'nom_province' => 'Province test',
            'is_active' => 'YES',
        ]);
        DB::table('evenements')->insert([
            'code_evenement' => 'EVENT01',
            'nom_evenement' => 'Événement test',
        ]);
    }

    public function test_dashboard_filters_work_and_status_chart_uses_all_incidents_as_denominator(): void
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
                    && $chart['byStatus']['total'] === 3
                    && $chart['byStatus']['validatedPercentage'] === 33.3
                    && $chart['byProvince']['labels']->all() === ['Province test']
                    && $chart['byProvince']['sum'] === 1
                    && $chart['evolution']['data']->sum() === 1;
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
        DB::table('violences')->insert([
            ['id' => 1001, 'violence_name' => 'Violence A', 'categorie_name' => 'Categorie A'],
            ['id' => 1002, 'violence_name' => 'Violence B', 'categorie_name' => 'Categorie B'],
        ]);

        $validated = $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-EXPORT', [
            'code_territoire' => 'TEXP',
        ]);
        $pending = $this->incidentFor($user, 'En attente', 'ALT-PENDING-EXPORT', [
            'code_territoire' => 'TEXP',
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

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $violenceRows = (new ViolencesSheet($from, $to, 'P01', 'TEXP'))->collection();
        $victimeRows = (new VictimesSheet($from, $to, 'P01', 'TEXP'))->collection();
        $reponseRows = (new ReponsesSheet($from, $to, 'P01', 'TEXP'))->collection();

        $this->assertCount(2, $violenceRows);
        $this->assertSame(['ALT-EXPORT'], $violenceRows->pluck(0)->unique()->values()->all());
        $this->assertCount(1, $victimeRows);
        $this->assertSame(5, $victimeRows->first()[18]);
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
