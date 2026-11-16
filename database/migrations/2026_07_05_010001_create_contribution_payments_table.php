<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedTinyInteger('payment_month')->nullable();
            $table->unsignedSmallInteger('payment_year')->nullable();
            $table->string('payment_period_label')->nullable();
            $table->string('payment_method');
            $table->string('reference', 150)->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('contribution_plan_id');
            $table->index('status');
            $table->index('payment_year');
            $table->index('payment_month');
            $table->index('payment_method');
            $table->index('validated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_payments');
    }
};
