<?php

use App\Support\FundRules;
use App\Support\ProjectTypeDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lots 3 à 10 du règlement intérieur :
 * types de projets et critères, instruction par le comité, validations multiples des transactions,
 * prêts (échéancier, remboursements, pénalités, rappels), pénalités de cotisation, retraits,
 * rapports partagés et vérification annuelle des comptes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Un prêt reçoit plusieurs remboursements : l'unicité « un mouvement par projet et par type » ne vaut plus
        // que pour le décaissement, garantie désormais par une clé d'opération unique.
        DB::table('fund_movements')->where('type', 'project_funding')->whereNotNull('project_id')->whereNull('operation_leg_key')
            ->orderBy('id')->get(['id', 'project_id'])->each(fn ($m) => DB::table('fund_movements')->where('id', $m->id)->update(['operation_leg_key' => 'project-funding:'.$m->project_id]));
        Schema::table('fund_movements', function (Blueprint $table) {
            $table->dropUnique('fund_movement_project_type_unique');
        });

        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_loan')->default(false);
            $table->boolean('requires_personal_contribution')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_type_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_type_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_blocking')->default(true);
            $table->string('auto_check', 60)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_type_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->foreignId('resubmission_of_id')->nullable()->after('project_type_id')->constrained('projects')->nullOnDelete();
            $table->text('repayment_plan')->nullable()->after('description');
            $table->unsignedTinyInteger('loan_duration_months')->nullable()->after('repayment_plan');
            $table->date('presentation_date')->nullable()->after('loan_duration_months');
            $table->string('committee_decision', 20)->nullable()->after('committee_decision_comment');
            $table->text('information_request')->nullable()->after('committee_decision');
            $table->dateTime('decided_at')->nullable()->after('information_request');
            $table->decimal('loan_interest_rate', 6, 3)->nullable()->after('funded_amount');
            $table->decimal('loan_total_due', 14, 2)->nullable()->after('loan_interest_rate');
            $table->date('first_due_date')->nullable()->after('loan_total_due');
            $table->dateTime('repaid_at')->nullable()->after('first_due_date');
        });

        Schema::create('project_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_type_criterion_id')->nullable()->constrained('project_type_criteria')->nullOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_blocking')->default(true);
            $table->string('auto_check', 60)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status', 20)->default('pending'); // pending | validated | not_validated | not_applicable
            $table->string('auto_result', 20)->nullable();
            $table->string('auto_detail')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('project_documents', function (Blueprint $table) {
            $table->string('visibility', 20)->default('all')->after('type'); // all | committee
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_type_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending | executed | rejected | cancelled | failed
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->json('approver_roles')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejected_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['status', 'transaction_type_id']);
        });

        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 10); // approve | reject
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['approval_request_id', 'user_id']);
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('principal', 14, 2);
            $table->decimal('interest', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('penalty_amount', 14, 2)->default(0);
            $table->decimal('penalty_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending | partial | paid | late
            $table->dateTime('paid_at')->nullable();
            $table->unsignedTinyInteger('reminder_level')->default(0);
            $table->dateTime('last_reminded_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'number']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->json('allocation')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fund_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('contribution_dues', function (Blueprint $table) {
            $table->decimal('penalty_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('carried_penalty', 14, 2)->default(0)->after('penalty_amount');
            $table->foreignId('penalty_carried_to_id')->nullable()->after('carried_penalty')->constrained('contribution_dues')->nullOnDelete();
            $table->boolean('penalty_waived')->default(false)->after('penalty_carried_to_id');
            $table->text('penalty_waiver_reason')->nullable()->after('penalty_waived');
            $table->foreignId('penalty_waived_by')->nullable()->after('penalty_waiver_reason')->constrained('users')->nullOnDelete();
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->date('requested_on');
            $table->date('effective_date');
            $table->decimal('total_contributions', 14, 2)->default(0);
            $table->decimal('refund_percent', 5, 2)->default(95);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->boolean('funds_engaged_in_loans')->default(false);
            $table->string('status', 20)->default('requested'); // requested | scheduled | completed | cancelled
            $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('withdrawal_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fund_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shared_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind', 20)->default('quarterly');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('data');
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('shared_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('status', 20)->default('open'); // open | confirmed | issues
            $table->text('conclusion')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_review_user', function (Blueprint $table) {
            $table->foreignId('audit_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['audit_review_id', 'user_id']);
        });

        $now = now();

        // Types de projets et critères par défaut (modifiables ensuite par le président et le comité).
        foreach (ProjectTypeDefaults::TYPES as $type) {
            $id = DB::table('project_types')->insertGetId([
                'name' => $type['name'], 'code' => $type['code'], 'description' => $type['description'],
                'is_loan' => $type['is_loan'], 'requires_personal_contribution' => $type['requires_personal_contribution'],
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($type['criteria'] as $position => $criterion) {
                DB::table('project_type_criteria')->insert([
                    'project_type_id' => $id, 'label' => $criterion[0], 'description' => $criterion[1] ?? null,
                    'is_blocking' => $criterion[2] ?? true, 'auto_check' => $criterion[3] ?? null,
                    'position' => $position + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        // Les projets existants ont été saisis avec la règle 50/50 : ce sont des investissements.
        DB::table('projects')->whereNull('project_type_id')->update(['project_type_id' => DB::table('project_types')->where('code', 'investment')->value('id')]);

        foreach (FundRules::DEFINITIONS as $key => $definition) {
            if (! DB::table('application_settings')->where('key', $key)->exists()) {
                DB::table('application_settings')->insert([
                    'key' => $key, 'group' => 'règlement du fonds', 'label' => $definition['label'], 'value' => (string) $definition['default'],
                    'type' => $definition['type'], 'description' => $definition['description'] ?? null, 'is_public' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('fund_movements', fn (Blueprint $table) => $table->unique(['project_id', 'type'], 'fund_movement_project_type_unique'));
        Schema::dropIfExists('audit_review_user');
        Schema::dropIfExists('audit_reviews');
        Schema::dropIfExists('shared_reports');
        Schema::dropIfExists('withdrawal_installments');
        Schema::dropIfExists('withdrawal_requests');
        Schema::table('contribution_dues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penalty_carried_to_id');
            $table->dropConstrainedForeignId('penalty_waived_by');
            $table->dropColumn(['penalty_amount', 'carried_penalty', 'penalty_waived', 'penalty_waiver_reason']);
        });
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_requests');
        Schema::table('project_documents', fn (Blueprint $table) => $table->dropColumn('visibility'));
        Schema::dropIfExists('project_criteria');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_type_id');
            $table->dropConstrainedForeignId('resubmission_of_id');
            $table->dropColumn(['repayment_plan', 'loan_duration_months', 'presentation_date', 'committee_decision', 'information_request', 'decided_at', 'loan_interest_rate', 'loan_total_due', 'first_due_date', 'repaid_at']);
        });
        Schema::dropIfExists('project_type_criteria');
        Schema::dropIfExists('project_types');
    }
};
