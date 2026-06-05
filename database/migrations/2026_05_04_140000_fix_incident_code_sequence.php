<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // On PostgreSQL, on synchronise la séquence uniquement avec les codes numériques commençant par 'ALT-'
        // S'il n'y a pas d'incidents (base de données vide), on ne fait rien pour éviter setval(..., 0)
        DB::statement("
            DO $$
            DECLARE
                max_id INTEGER;
            BEGIN
                SELECT MAX(CAST(SUBSTRING(code_incident FROM 5) AS INTEGER))
                INTO max_id
                FROM incidents
                WHERE code_incident ~ '^ALT-[0-9]+$';

                IF max_id IS NOT NULL AND max_id > 0 THEN
                    PERFORM setval('incident_code_seq', max_id);
                END IF;
            END $$;
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Rien à faire
    }
};
