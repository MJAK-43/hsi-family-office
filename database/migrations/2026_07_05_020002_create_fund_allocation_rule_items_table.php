<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_allocation_rule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_allocation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fund_account_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['fund_allocation_rule_id', 'fund_account_id'], 'fund_rule_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_allocation_rule_items');
    }
};
