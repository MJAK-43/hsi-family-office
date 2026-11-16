<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recette de l'application : chaque testeur donne son résultat (validé / à revoir / pas testé) sur une tâche.
 * Chaque envoi est une nouvelle ligne : le dernier envoi d'un testeur fait foi, les précédents forment l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('task_key', 20)->index();
            $table->string('tester_name', 60);
            $table->string('tester_key', 60)->index(); // prénom normalisé (minuscules, espaces réduits)
            $table->string('status', 20); // untested | validated | to_review
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('test_feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_feedback_id')->constrained('test_feedback')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_feedback_attachments');
        Schema::dropIfExists('test_feedback');
    }
};
