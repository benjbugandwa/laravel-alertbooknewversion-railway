<?php

namespace Tests\Feature;

use App\Livewire\Pages\Organisations\Index as OrganisationsIndex;
use App\Livewire\Pages\Users\Index as UsersIndex;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganisationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_organisation_from_organisations_page(): void
    {
        $admin = $this->userWithRole('admin');

        Livewire::actingAs($admin)
            ->test(OrganisationsIndex::class)
            ->call('openCreate')
            ->set('form.org_sigle', ' acme ')
            ->set('form.org_name', 'Organisation ACME')
            ->set('form.org_secteur_activite', ['Protection', 'Santé'])
            ->set('form.org_categorie', 'ONG Nationale')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $organisation = Organisation::where('org_sigle', 'ACME')->firstOrFail();
        $this->assertSame(['Protection', 'Santé'], $organisation->org_secteur_activite);
        $this->assertTrue($organisation->is_active);
    }

    public function test_superadmin_can_create_organisation_from_users_page(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        Livewire::actingAs($superadmin)
            ->test(UsersIndex::class)
            ->call('openCreateOrg')
            ->set('org_sigle', 'HCR')
            ->set('org_name', 'Haut Commissariat')
            ->set('org_secteur_activite', 'Protection')
            ->set('org_categorie', 'Agence des Nations Unies')
            ->call('createOrganisation')
            ->assertHasNoErrors()
            ->assertSet('showCreateOrgModal', false);

        $this->assertDatabaseHas('organisations', [
            'org_sigle' => 'HCR',
            'org_name' => 'Haut Commissariat',
        ]);
        $this->assertSame(['Protection'], Organisation::where('org_sigle', 'HCR')->firstOrFail()->org_secteur_activite);
    }

    public function test_duplicate_acronym_is_returned_as_validation_error(): void
    {
        Organisation::create([
            'org_sigle' => 'HCR',
            'org_name' => 'Organisation existante',
            'org_secteur_activite' => [],
        ]);

        $superadmin = $this->userWithRole('superadmin');

        Livewire::actingAs($superadmin)
            ->test(UsersIndex::class)
            ->call('openCreateOrg')
            ->set('org_sigle', 'HCR')
            ->set('org_name', 'Doublon')
            ->call('createOrganisation')
            ->assertHasErrors(['org_sigle' => 'unique'])
            ->assertSet('showCreateOrgModal', true);

        $this->assertSame(1, Organisation::where('org_sigle', 'HCR')->count());
    }

    public function test_legacy_plain_text_sector_is_exposed_as_array(): void
    {
        $id = \Illuminate\Support\Facades\DB::table('organisations')->insertGetId([
            'org_name' => 'Organisation historique',
            'org_secteur_activite' => 'Protection',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(['Protection'], Organisation::findOrFail($id)->org_secteur_activite);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
        $user->roles()->attach($role);

        return $user->refresh();
    }
}
