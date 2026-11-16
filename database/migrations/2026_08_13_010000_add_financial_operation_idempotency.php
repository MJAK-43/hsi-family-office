<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateMovements();

        Schema::create('financial_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_type', 80);
            $table->string('idempotency_key', 150);
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->json('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_type', 'idempotency_key'], 'financial_operations_type_key_unique');
            $table->index(['operation_type', 'status'], 'financial_operations_type_status_index');
        });

        Schema::table('fund_movements', function (Blueprint $table): void {
            $table->foreignId('financial_operation_id')
                ->nullable()
                ->after('project_id')
                ->constrained('financial_operations')
                ->restrictOnDelete();
            $table->string('operation_leg_key', 180)->nullable()->after('financial_operation_id');

            $table->unique(
                ['contribution_payment_id', 'fund_account_id', 'type'],
                'fund_movement_contribution_account_type_unique',
            );
            $table->unique(['project_id', 'type'], 'fund_movement_project_type_unique');
            $table->unique('operation_leg_key', 'fund_movement_operation_leg_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fund_movements', function (Blueprint $table): void {
            $table->dropUnique('fund_movement_contribution_account_type_unique');
            $table->dropUnique('fund_movement_project_type_unique');
            $table->dropUnique('fund_movement_operation_leg_unique');
            $table->dropColumn('operation_leg_key');
            $table->dropConstrainedForeignId('financial_operation_id');
        });

        Schema::dropIfExists('financial_operations');
    }

    private function assertNoDuplicateMovements(): void
    {
        $duplicates = [
            'allocations de cotisation' => DB::table('fund_movements')
                ->whereNotNull('contribution_payment_id')
                ->selectRaw('contribution_payment_id, fund_account_id, type, COUNT(*) AS aggregate')
                ->groupBy('contribution_payment_id', 'fund_account_id', 'type')
                ->havingRaw('COUNT(*) > 1')
                ->exists(),
            'financements de projet' => DB::table('fund_movements')
                ->whereNotNull('project_id')
                ->selectRaw('project_id, type, COUNT(*) AS aggregate')
                ->groupBy('project_id', 'type')
                ->havingRaw('COUNT(*) > 1')
                ->exists(),
            'jambes de transfert' => DB::table('fund_movements')
                ->whereNotNull('reference')
                ->whereIn('type', ['transfer_in', 'transfer_out'])
                ->selectRaw('reference, type, COUNT(*) AS aggregate')
                ->groupBy('reference', 'type')
                ->havingRaw('COUNT(*) > 1')
                ->exists(),
        ];

        foreach ($duplicates as $label => $exists) {
            if ($exists) {
                throw new RuntimeException("Migration interrompue : doublons détectés pour {$label}.");
            }
        }
    }
};
