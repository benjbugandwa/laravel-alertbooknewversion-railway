<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('victimes', function (Blueprint $table) {
            $table->id();
            
            $table->uuid('incident_id')->nullable();
            $table->integer('violence_id');
            $table->string('profile_victimes'); // Enum values: Résidants, Réfugiés, Déplacés, Retournés, Autres
            
            $table->integer('nbre_femme_0a4ans')->default(0)->nullable();
            $table->integer('nbre_femme_5a11ans')->default(0)->nullable();
            $table->integer('nbre_femme_12a17ans')->default(0)->nullable();
            $table->integer('nbre_femme_18a59ans')->default(0)->nullable();
            $table->integer('nbre_femme_6Oansouplus')->default(0)->nullable(); // with letter O!

            $table->integer('nbre_homme_0a4ans')->default(0)->nullable();
            $table->integer('nbre_homme_5a11ans')->default(0)->nullable();
            $table->integer('nbre_homme_12a17ans')->default(0)->nullable();
            $table->integer('nbre_homme_18a59ans')->default(0)->nullable();
            $table->integer('nbre_homme_6Oansouplus')->default(0)->nullable(); // with letter O!

            $table->text('description_faits');
            $table->date('create_at');
            $table->unsignedBigInteger('created_by');

            // Foreign Key constraints
            $table->foreign('incident_id')->references('id')->on('incidents')->onDelete('cascade');
            $table->foreign('violence_id')->references('id')->on('violences')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('victimes');
    }
};
