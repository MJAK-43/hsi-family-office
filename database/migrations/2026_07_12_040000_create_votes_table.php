<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('project_funding');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->unsignedTinyInteger('quorum_percent')->default(50);
            $table->unsignedInteger('quorum_absolute')->nullable();
            $table->string('majority_rule')->default('simple');
            $table->boolean('allow_abstention')->default(true);
            $table->string('result')->nullable();
            $table->unsignedInteger('eligible_count')->nullable();
            $table->unsignedInteger('ballots_count')->default(0);
            $table->unsignedInteger('for_count')->default(0);
            $table->unsignedInteger('against_count')->default(0);
            $table->unsignedInteger('abstain_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
            $table->index('project_id');
            $table->index('closes_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
