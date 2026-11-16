<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identifiant Google du compte (champ « sub »), pour la connexion « Continuer avec Google ».
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique()->after('email');
        });

        // Demande faite avec Google : pas de mot de passe, le compte sera relié à ce Google.
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
