<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_fund_account_id')->nullable()->constrained('fund_accounts')->nullOnDelete();
            $table->foreignId('contribution_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('validated');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('direction');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('reference', 150)->nullable();
            $table->date('movement_date')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fund_account_id');
            $table->index('contribution_payment_id');
            $table->index('type');
            $table->index('status');
            $table->index('direction');
            $table->index('movement_date');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_movements');
    }
};
