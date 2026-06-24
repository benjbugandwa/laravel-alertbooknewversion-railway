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

        DB::statement('CREATE SEQUENCE IF NOT EXISTS incident_code_seq START WITH 1 INCREMENT BY 1');

        DB::statement("
            SELECT setval(
                'incident_code_seq',
                GREATEST(
                    COALESCE((
                        SELECT MAX(CAST(SUBSTRING(code_incident FROM 5) AS INTEGER))
                        FROM incidents
                        WHERE code_incident ~ '^ALT-[0-9]+$'
                    ), 0),
                    1
                ),
                COALESCE((
                    SELECT MAX(CAST(SUBSTRING(code_incident FROM 5) AS INTEGER))
                    FROM incidents
                    WHERE code_incident ~ '^ALT-[0-9]+$'
                ), 0) > 0
            )
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP SEQUENCE IF EXISTS incident_code_seq');
    }
};
