<?php

namespace Tests\Feature;

use App\Livewire\Pages\Users\Profile;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountActivatedNotification;
use App\Notifications\NewAccountPendingActivationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UserProvinceDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_displays_province_name_and_role_instead_of_province_code(): void
    {
        $user = $this->userInProvinceWithRole();

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->assertSee('Province : Sud-Kivu')
            ->assertSee('Rôle : Moniteur')
            ->assertDontSee('Province : CD74');
    }

    public function test_pending_account_email_displays_province_name_instead_of_province_code(): void
    {
        $newUser = $this->userInProvinceWithRole();
        $admin = User::factory()->create(['name' => 'Administrateur']);

        $html = (new NewAccountPendingActivationNotification($newUser))
            ->toMail($admin)
            ->render();

        $this->assertStringContainsString('Sud-Kivu', $html);
        $this->assertStringNotContainsString('CD74', $html);
    }

    public function test_account_activated_email_displays_province_name_instead_of_province_code(): void
    {
        $user = $this->userInProvinceWithRole();
        $organisation = Organisation::create([
            'org_sigle' => 'ORG',
            'org_name' => 'Organisation test',
            'org_secteur_activite' => [],
        ]);

        $html = (new AccountActivatedNotification(
            $organisation,
            $user->roles->firstOrFail(),
            $user->code_province,
        ))->toMail($user)->render();

        $this->assertStringContainsString('Sud-Kivu', $html);
        $this->assertStringNotContainsString('CD74', $html);
    }

    private function userInProvinceWithRole(): User
    {
        DB::table('provinces')->insert([
            'code_province' => 'CD74',
            'nom_province' => 'Sud-Kivu',
            'is_active' => 'NO',
        ]);

        $role = Role::create([
            'slug' => 'moniteur',
            'name' => 'Moniteur',
        ]);

        $user = User::factory()->create([
            'code_province' => 'CD74',
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        return $user->refresh();
    }
}
