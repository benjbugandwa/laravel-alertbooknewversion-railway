<?php

namespace Tests\Feature;

use Database\Seeders\TanganyikaTerritoryCoordinatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TanganyikaTerritoryCoordinatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_tanganyika_territory_coordinates_from_csv(): void
    {
        DB::table('provinces')->insert([
            'code_province' => 'CD74',
            'nom_province' => 'Tanganyika',
            'is_active' => 'YES',
        ]);

        DB::table('territoires')->insert([
            [
                'code_territoire' => 'CD7410',
                'nom_territoire' => 'Territoire 7410',
                'code_province' => 'CD74',
            ],
            [
                'code_territoire' => 'CD7402',
                'nom_territoire' => 'Territoire 7402',
                'code_province' => 'CD74',
            ],
            [
                'code_territoire' => 'CD7404',
                'nom_territoire' => 'Territoire 7404',
                'code_province' => 'CD74',
            ],
            [
                'code_territoire' => 'CD7406',
                'nom_territoire' => 'Territoire 7406',
                'code_province' => 'CD74',
            ],
            [
                'code_territoire' => 'CD7409',
                'nom_territoire' => 'Territoire 7409',
                'code_province' => 'CD74',
            ],
        ]);

        $this->seed(TanganyikaTerritoryCoordinatesSeeder::class);

        $territoire = DB::table('territoires')
            ->where('code_territoire', 'CD7410')
            ->first(['latitude', 'longitude']);

        $this->assertNotNull($territoire);
        $this->assertEqualsWithDelta(-5.968990, (float) $territoire->latitude, 0.000001);
        $this->assertEqualsWithDelta(28.011959, (float) $territoire->longitude, 0.000001);

        $this->assertSame(5, DB::table('territoires')->whereNotNull('latitude')->whereNotNull('longitude')->count());
    }
}
