<?php

namespace Tests\Feature;

use App\Livewire\Pages\Analyses\Index as AnalysesIndex;
use App\Models\Incident;
use App\Models\Role;
use App\Models\User;
use App\Services\AnalysisReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AnalysisReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_service_only_uses_validated_alerts_in_scope(): void
    {
        $this->seedAnalysisData();

        $report = app(AnalysisReportService::class)->build([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'province' => 'P01',
            'territoire' => 'T01',
        ]);

        $this->assertSame(1, $report['summary']['alerts']);
        $this->assertSame(1, $report['summary']['health_zones']);
        $this->assertSame(5, $report['summary']['victims']);
        $this->assertSame(12, $report['summary']['movement_people']);
        $this->assertSame('Zone test', $report['hot_zones'][0]['label']);
        $this->assertSame('Territoire test', $report['hot_territories'][0]['name']);
    }

    public function test_analysis_page_generates_download_link_and_validates_dates(): void
    {
        $user = $this->seedAnalysisData();

        Livewire::actingAs($user)
            ->test(AnalysesIndex::class)
            ->set('from', '2026-07-10')
            ->set('to', '2026-07-09')
            ->call('generate')
            ->assertHasErrors(['to']);

        Livewire::actingAs($user)
            ->test(AnalysesIndex::class)
            ->set('from', '2026-07-01')
            ->set('to', '2026-07-31')
            ->set('province', 'P01')
            ->set('territoire', 'T01')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSet('generatedUrl', fn (?string $url): bool => $url !== null && str_contains($url, '/analyses/rapport'));
    }

    public function test_analysis_report_pdf_can_be_downloaded(): void
    {
        $user = $this->seedAnalysisData();

        $this->actingAs($user)
            ->get(route('analyses.report', [
                'from' => '2026-07-01',
                'to' => '2026-07-31',
                'province' => 'P01',
                'territoire' => 'T01',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function seedAnalysisData(): User
    {
        DB::table('provinces')->insert([
            'code_province' => 'P01',
            'nom_province' => 'Province test',
            'is_active' => 'YES',
        ]);

        DB::table('territoires')->insert([
            'code_territoire' => 'T01',
            'nom_territoire' => 'Territoire test',
            'code_province' => 'P01',
            'latitude' => -5.5,
            'longitude' => 28.2,
        ]);

        DB::table('zonesantes')->insert([
            'code_zonesante' => 'Z01',
            'nom_zonesante' => 'Zone test',
            'code_territoire' => 'T01',
        ]);

        DB::table('evenements')->insert([
            'code_evenement' => 'EVENT01',
            'nom_evenement' => 'Evenement test',
        ]);

        DB::table('violences')->insert([
            'id' => 1001,
            'violence_name' => 'Violence test',
            'categorie_name' => 'Categorie test',
        ]);

        $user = User::factory()->create([
            'code_province' => 'P01',
            'is_active' => true,
        ]);
        $role = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Superadmin']);
        $user->roles()->attach($role);

        $validated = $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-VALIDATED', '2026-07-15');
        $this->incidentFor($user, 'En attente', 'ALT-PENDING', '2026-07-15');
        $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-OLD', '2026-06-01');

        DB::table('victimes')->insert([
            'incident_id' => $validated,
            'violence_id' => 1001,
            'profile_victimes' => 'Residents',
            'nbre_femme_18a59ans' => 2,
            'nbre_homme_18a59ans' => 3,
            'description_faits' => 'Victimes test',
            'create_at' => '2026-07-15',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mouvements')->insert([
            'date_mouvement' => '2026-07-16',
            'type_mouvement' => 'Retour',
            'source_info' => 'Source test',
            'code_province_prov' => 'P01',
            'code_territoire_prov' => 'T01',
            'code_zonesante_prov' => 'Z01',
            'localite_prov' => 'Localite prov',
            'code_province_accl' => 'P01',
            'code_territoire_accl' => 'T01',
            'code_zonesante_accl' => 'Z01',
            'localite_accl' => 'Localite accl',
            'created_by' => $user->id,
            'estim_nbre_menages' => 4,
            'estim_nbre_personnes' => 12,
            'incident_id' => $validated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->refresh();
    }

    private function incidentFor(User $creator, string $status, string $code, string $date): string
    {
        $id = (string) Str::uuid();

        Incident::create([
            'id' => $id,
            'code_incident' => $code,
            'date_incident' => $date,
            'created_by' => $creator->id,
            'code_province' => 'P01',
            'code_territoire' => 'T01',
            'code_zonesante' => 'Z01',
            'code_evenement' => 'EVENT01',
            'statut_incident' => $status,
            'severite' => 'Faible',
            'source_info' => 'Population locale',
            'confidentiality_level' => 'Standard',
            'created_at' => $date,
            'last_status_changed_at' => $date,
        ]);

        return $id;
    }
}
