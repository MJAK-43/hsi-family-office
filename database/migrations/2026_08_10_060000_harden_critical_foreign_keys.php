<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve financial, governance and identity history when a parent is deleted.
     */
    public function up(): void
    {
        $this->replaceDeleteRule('member_profiles', 'user_id', 'users', restrict: true);
        $this->replaceDeleteRule('contribution_payments', 'user_id', 'users', restrict: true);
        $this->replaceDeleteRule('projects', 'owner_id', 'users', restrict: true);
        $this->replaceDeleteRule('vote_ballots', 'user_id', 'users', restrict: true);
        $this->replaceDeleteRule('fund_movements', 'fund_account_id', 'fund_accounts', restrict: true);
    }

    public function down(): void
    {
        $this->replaceDeleteRule('member_profiles', 'user_id', 'users', restrict: false);
        $this->replaceDeleteRule('contribution_payments', 'user_id', 'users', restrict: false);
        $this->replaceDeleteRule('projects', 'owner_id', 'users', restrict: false);
        $this->replaceDeleteRule('vote_ballots', 'user_id', 'users', restrict: false);
        $this->replaceDeleteRule('fund_movements', 'fund_account_id', 'fund_accounts', restrict: false);
    }

    private function replaceDeleteRule(string $tableName, string $column, string $parentTable, bool $restrict): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($column, $parentTable, $restrict): void {
            $table->dropForeign([$column]);

            $foreign = $table->foreign($column)->references('id')->on($parentTable);

            if ($restrict) {
                $foreign->restrictOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }
};
