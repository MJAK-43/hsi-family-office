<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('profitability_score')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->unsignedTinyInteger('founder_score')->nullable();
            $table->unsignedTinyInteger('family_impact_score')->nullable();
            $table->unsignedTinyInteger('sustainability_score')->nullable();
            $table->unsignedTinyInteger('values_alignment_score')->nullable();
            $table->unsignedSmallInteger('total_score')->nullable();
            $table->string('recommendation')->nullable();
            $table->text('comment')->nullable();
            $table->dateTime('evaluated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'evaluator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_evaluations');
    }
};
