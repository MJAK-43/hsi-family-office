<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_accounts', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('description');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('color');
        });

        // Chaque projet est rattaché à un compte de fonds : c'est ce compte qui verse l'argent.
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('fund_account_id')->nullable()->after('project_type_id')->constrained('fund_accounts')->nullOnDelete();
            $table->boolean('is_example')->default(false)->after('repaid_at');
        });
        Schema::table('fund_movements', function (Blueprint $table) {
            $table->boolean('is_example')->default(false)->after('status');
        });

        // Projets existants : le compte qui les a financés, sinon « Projets familiaux ».
        $familyProjects = DB::table('fund_accounts')->where('slug', 'family-projects')->value('id');
        foreach (DB::table('projects')->whereNull('fund_account_id')->pluck('id') as $projectId) {
            $funded = DB::table('fund_movements')->where('project_id', $projectId)->where('type', 'project_funding')->value('fund_account_id');
            DB::table('projects')->where('id', $projectId)->update(['fund_account_id' => $funded ?? $familyProjects]);
        }

        $order = ['family-projects' => 1, 'solidarity' => 2, 'education' => 3, 'long-term-investment' => 4, 'treasury' => 9];
        foreach ($order as $slug => $position) {
            DB::table('fund_accounts')->where('slug', $slug)->update(['sort_order' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('fund_movements', fn (Blueprint $table) => $table->dropColumn('is_example'));
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fund_account_id');
            $table->dropColumn('is_example');
        });
        Schema::table('fund_accounts', fn (Blueprint $table) => $table->dropColumn(['color', 'sort_order']));
    }
};
