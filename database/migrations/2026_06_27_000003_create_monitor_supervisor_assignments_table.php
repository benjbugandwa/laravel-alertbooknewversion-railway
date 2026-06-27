<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monitor_id')->unique();
            $table->unsignedBigInteger('supervisor_id');
            $table->string('code_province', 20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('monitor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('supervisor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('code_province')->references('code_province')->on('provinces');
            $table->index(['code_province', 'supervisor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_supervisor_assignments');
    }
};
