<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const VALUES = [
        'Fuite',
        'Retour',
        'Pendulaire',
        'Individuel',
        'De masse',
        'Préventif',
        'Autre',
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();
        $values = implode(', ', array_map(
            fn(string $value): string => DB::getPdo()->quote($value),
            self::VALUES
        ));

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE mouvements DROP CONSTRAINT IF EXISTS mouvements_type_mouvement_check');
            DB::statement("ALTER TABLE mouvements ADD CONSTRAINT mouvements_type_mouvement_check CHECK (type_mouvement IN ({$values}))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE mouvements MODIFY type_mouvement ENUM({$values}) NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE mouvements DROP CONSTRAINT IF EXISTS mouvements_type_mouvement_check');
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE mouvements MODIFY type_mouvement VARCHAR(255) NOT NULL');
        }
    }
};
