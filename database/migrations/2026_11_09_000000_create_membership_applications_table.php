<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email');
            // Mot de passe choisi par le candidat, haché. Effacé dès que la demande est traitée.
            $table->string('password')->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country', 100);
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('profession', 150)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('family_link', 60);
            $table->string('sponsor_name', 150)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
