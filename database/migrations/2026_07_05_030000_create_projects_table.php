<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('sector');
            $table->decimal('total_budget', 14, 2);
            $table->decimal('personal_contribution', 14, 2);
            $table->decimal('requested_amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('expected_duration_months')->nullable();
            $table->decimal('expected_roi_percentage', 5, 2)->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('committee_decision_comment')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->dateTime('submitted_to_vote_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('country_id');
            $table->index('sector');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
