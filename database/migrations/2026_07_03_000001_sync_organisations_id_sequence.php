<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            SELECT setval(
                pg_get_serial_sequence('organisations', 'id'),
                GREATEST(COALESCE(MAX(id), 0), 1),
                COALESCE(MAX(id), 0) > 0
            )
            FROM organisations
        SQL);
    }

    public function down(): void
    {
        // Réparation de données non réversible.
    }
};
