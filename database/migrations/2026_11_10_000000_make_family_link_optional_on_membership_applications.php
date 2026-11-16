<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La section « lien avec la famille » a été retirée du formulaire d'adhésion :
// le champ devient facultatif (les anciennes demandes gardent leur valeur).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('family_link', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('family_link', 60)->nullable(false)->default('autre')->change();
        });
    }
};
