<?php

use App\Support\ContributionRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_plans', function (Blueprint $table) {
            $table->boolean('is_regulatory')->default(false)->after('is_default');
        });

        // Section 4 du règlement : une seule contribution obligatoire, trimestrielle, avant le 15 du 1er mois.
        // Les anciens plans restent consultables mais ne génèrent plus d'échéances automatiques.
        DB::table('contribution_plans')->update(['is_default' => false, 'auto_generate' => false]);
        ContributionRules::ensurePlan();
    }

    public function down(): void
    {
        DB::table('contribution_plans')->where('is_regulatory', true)->delete();
        Schema::table('contribution_plans', fn (Blueprint $table) => $table->dropColumn('is_regulatory'));
    }
};
