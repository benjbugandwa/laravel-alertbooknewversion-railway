<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Exports\Sheets\IncidentsSheet;
use App\Livewire\Pages\Dashboard;
use App\Models\Incident;
use App\Models\Role;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
    }

    public function test_dashboard_filters_work_and_charts_only_include_validated_incidents(): void
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
                return $chart['byStatus']['labels']->all() === [Incident::STATUS_VALIDATED]
                    && $chart['byStatus']['data']->all() === [1]
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
    }

    public function test_unassigned_incident_is_assigned_to_supervisor_when_validated(): void
    {
        $supervisor = $this->userWithRole('superviseur');
        $incident = $this->incidentFor($supervisor, 'En attente', 'ALT-UNASSIGNED');
        $this->attachViolence($incident, 1);

        $validated = app(IncidentService::class)->validateIncident($incident, $supervisor, '127.0.0.1');

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
        $this->attachViolence($incident, 2);

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
            'statut_incident' => $status,
            'created_at' => now(),
            'last_status_changed_at' => now(),
        ], $attributes));
    }

    private function attachViolence(Incident $incident, int $id): void
    {
        DB::table('violences')->insert([
            'id' => $id,
            'violence_name' => 'Violence '.$id,
        ]);
        DB::table('violence_incidents')->insert([
            'id' => $id,
            'id_incident' => $incident->id,
            'id_violence' => $id,
            'created_by' => $incident->created_by,
            'created_at' => now(),
        ]);
    }
}
