<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('funded_at');
            $table->dateTime('completed_at')->nullable()->after('started_at');
            $table->dateTime('failed_at')->nullable()->after('completed_at');
            $table->dateTime('cancelled_at')->nullable()->after('failed_at');
            $table->text('closure_note')->nullable()->after('cancelled_at');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('closure_note');
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('planned_amount', 14, 2)->nullable();
            $table->decimal('spent_amount', 14, 2)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('project_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('progress_percent');
            $table->decimal('spent_amount', 14, 2)->default(0);
            $table->text('summary');
            $table->text('risks')->nullable();
            $table->text('next_steps')->nullable();
            $table->date('reported_at');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'reported_at']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->string('ballot_visibility')->default('named')->after('allow_abstention');
            $table->dateTime('eligibility_locked_at')->nullable()->after('eligible_count');
            $table->foreignId('winning_option_id')->nullable()->after('result')->constrained('vote_options')->nullOnDelete();
            $table->json('result_summary')->nullable()->after('winning_option_id');
        });

        Schema::create('vote_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['vote_id', 'user_id']);
        });

        Schema::create('vote_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_eligible')->default(true);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->string('exclusion_reason')->nullable();
            $table->timestamps();
            $table->unique(['vote_id', 'user_id']);
            $table->index(['vote_id', 'is_eligible']);
        });

        Schema::table('contribution_plans', function (Blueprint $table) {
            $table->boolean('auto_generate')->default(true)->after('is_active');
            $table->date('starts_on')->nullable()->after('auto_generate');
            $table->unsignedTinyInteger('due_day')->default(5)->after('starts_on');
            $table->unsignedSmallInteger('grace_days')->default(5)->after('due_day');
            $table->dateTime('last_generated_at')->nullable()->after('grace_days');
        });

        Schema::create('contribution_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 20);
            $table->string('period_label');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->date('due_date');
            $table->string('status')->default('pending');
            $table->foreignId('contribution_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();
            $table->unique(['contribution_plan_id', 'user_id', 'period_key'], 'contribution_due_unique');
            $table->index(['status', 'due_date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('contribution_dues');
        Schema::table('contribution_plans', function (Blueprint $table) {
            $table->dropColumn(['auto_generate', 'starts_on', 'due_day', 'grace_days', 'last_generated_at']);
        });
        Schema::dropIfExists('vote_eligibilities');
        Schema::dropIfExists('vote_conflicts');
        Schema::table('votes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winning_option_id');
            $table->dropColumn(['ballot_visibility', 'eligibility_locked_at', 'result_summary']);
        });
        Schema::dropIfExists('project_progress_reports');
        Schema::dropIfExists('project_milestones');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'completed_at', 'failed_at', 'cancelled_at', 'closure_note', 'progress_percent']);
        });
    }
};
