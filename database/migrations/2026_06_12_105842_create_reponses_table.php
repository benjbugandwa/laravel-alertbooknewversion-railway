<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("CREATE SEQUENCE IF NOT EXISTS reponse_code_seq START 1 INCREMENT 1");
        }

        Schema::create('reponses', function (Blueprint $table) {
            $table->id();
            $table->string('num_reponse')->unique();
            $table->date('date_reponse');
            $table->string('fournie_par');
            $table->string('type_reponse'); // Enum: ["Humanitaire","Militaire","Mixte","Autre"]
            $table->text('secteurs_couverts'); // Will store array of sectors (cast as array in model)
            $table->integer('nbre_menages_couverts')->nullable();
            $table->integer('nbre_individus_couverts')->nullable();
            $table->longText('impact_reponse')->nullable();
            $table->longText('observation_gap')->nullable();
            $table->string('rapport')->nullable(); // word, pdf, image file path
            $table->uuid('alerte_id');
            $table->date('create_at');
            $table->unsignedBigInteger('created_by');

            // Foreign Key constraints
            $table->foreign('alerte_id')->references('id')->on('incidents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reponses');

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("DROP SEQUENCE IF EXISTS reponse_code_seq");
        }
    }
};
