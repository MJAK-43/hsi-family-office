<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_movements', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('contribution_payment_id')
                ->constrained()
                ->nullOnDelete();
            $table->index('project_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dateTime('funded_at')->nullable()->after('submitted_to_vote_at');
            $table->foreignId('funded_by')
                ->nullable()
                ->after('funded_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('funded_amount', 14, 2)->nullable()->after('funded_by');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funded_by');
            $table->dropColumn(['funded_at', 'funded_amount']);
        });

        Schema::table('fund_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
