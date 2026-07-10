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
        $this->assertSame('Violence test', $report['violence_columns'][0]['label']);
        $this->assertSame(3, $report['violence_by_zone'][0]['violences']['Violence test']['male']);
        $this->assertSame(2, $report['violence_by_zone'][0]['violences']['Violence test']['female']);
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

    public function test_analysis_report_pdf_can_be_downloaded_for_tanganyika_scope(): void
    {
        $user = $this->seedAnalysisData('CD74', 'CD7410', 'CD741001');

        $this->actingAs($user)
            ->get(route('analyses.report', [
                'from' => '2026-05-01',
                'to' => '2026-07-09',
                'province' => 'CD74',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function seedAnalysisData(string $provinceCode = 'P01', string $territoireCode = 'T01', string $zoneCode = 'Z01'): User
    {
        DB::table('provinces')->insert([
            'code_province' => $provinceCode,
            'nom_province' => 'Province test',
            'is_active' => 'YES',
        ]);

        DB::table('territoires')->insert([
            'code_territoire' => $territoireCode,
            'nom_territoire' => 'Territoire test',
            'code_province' => $provinceCode,
            'latitude' => -5.5,
            'longitude' => 28.2,
        ]);

        DB::table('zonesantes')->insert([
            'code_zonesante' => $zoneCode,
            'nom_zonesante' => 'Zone test',
            'code_territoire' => $territoireCode,
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
            'code_province' => $provinceCode,
            'is_active' => true,
        ]);
        $role = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Superadmin']);
        $user->roles()->attach($role);

        $validated = $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-VALIDATED', '2026-07-01', $provinceCode, $territoireCode, $zoneCode);
        $this->incidentFor($user, 'En attente', 'ALT-PENDING', '2026-07-01', $provinceCode, $territoireCode, $zoneCode);
        $this->incidentFor($user, Incident::STATUS_VALIDATED, 'ALT-OLD', '2026-04-01', $provinceCode, $territoireCode, $zoneCode);

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
            'code_province_prov' => $provinceCode,
            'code_territoire_prov' => $territoireCode,
            'code_zonesante_prov' => $zoneCode,
            'localite_prov' => 'Localite prov',
            'code_province_accl' => $provinceCode,
            'code_territoire_accl' => $territoireCode,
            'code_zonesante_accl' => $zoneCode,
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

    private function incidentFor(
        User $creator,
        string $status,
        string $code,
        string $date,
        string $provinceCode,
        string $territoireCode,
        string $zoneCode
    ): string
    {
        $id = (string) Str::uuid();

        Incident::create([
            'id' => $id,
            'code_incident' => $code,
            'date_incident' => $date,
            'created_by' => $creator->id,
            'code_province' => $provinceCode,
            'code_territoire' => $territoireCode,
            'code_zonesante' => $zoneCode,
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
