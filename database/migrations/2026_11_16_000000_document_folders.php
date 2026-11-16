<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dossiers de la bibliothèque documentaire (explorateur façon gestionnaire de fichiers).
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('color', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['parent_id', 'name']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('document_category_id')->constrained('document_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
        Schema::dropIfExists('document_folders');
    }
};
