<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recette : réponses aux retours « À revoir » (explication, correction faite, à retester…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_feedback_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_feedback_id')->constrained('test_feedback')->cascadeOnDelete();
            $table->string('author_name', 60);
            $table->string('author_key', 60);
            $table->text('body');
            $table->boolean('marks_fixed')->default(false); // « Corrigé, à retester »
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_feedback_replies');
    }
};
