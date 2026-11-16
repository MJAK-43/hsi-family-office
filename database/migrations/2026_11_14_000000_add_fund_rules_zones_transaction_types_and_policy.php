<?php

use App\Support\FundRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Socle du règlement intérieur :
 * - zones (Europe, Amérique…) avec trésoriers facultatifs ;
 * - types de transactions et nombre de validations requises ;
 * - acceptation du règlement par chaque utilisateur ;
 * - paramètres chiffrés du règlement ;
 * - passage au dollar américain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Trésoriers d'une zone : facultatifs, une zone peut n'en avoir aucun.
        Schema::create('zone_user', function (Blueprint $table) {
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['zone_id', 'user_id']);
        });

        Schema::table('member_profiles', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });

        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('direction', 10)->default('out'); // in | out | internal
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->json('approver_roles')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('policy_accepted_at')->nullable();
            $table->string('policy_accepted_version', 20)->nullable();
            $table->string('policy_accepted_ip', 45)->nullable();
        });

        $now = now();

        DB::table('zones')->insert([
            ['name' => 'Europe', 'code' => 'europe', 'description' => 'Membres résidant en Europe', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Amérique', 'code' => 'america', 'description' => 'Membres résidant en Amérique du Nord et du Sud', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Rattachement initial des membres existants selon leur pays (modifiable ensuite).
        $zones = DB::table('zones')->pluck('id', 'code');
        $americas = ['US', 'CA', 'MX', 'BR', 'AR', 'CL', 'CO', 'PE', 'HT', 'DO', 'CU', 'JM', 'VE', 'EC', 'BO', 'PY', 'UY', 'GT', 'HN', 'SV', 'NI', 'CR', 'PA'];
        $europe = ['FR', 'BE', 'CH', 'DE', 'GB', 'UK', 'IT', 'ES', 'PT', 'NL', 'LU', 'IE', 'AT', 'SE', 'NO', 'DK', 'FI', 'PL', 'CZ', 'GR', 'RO', 'HU', 'MC'];
        foreach (['america' => $americas, 'europe' => $europe] as $code => $countries) {
            $countryIds = DB::table('countries')->whereIn('code', $countries)->pluck('id');
            DB::table('member_profiles')->whereNull('zone_id')->whereIn('country_id', $countryIds)->update(['zone_id' => $zones[$code]]);
        }

        $approvers = json_encode(['treasurer', 'president', 'super_admin']);
        foreach (FundRules::SYSTEM_TRANSACTION_TYPES as [$code, $name, $direction, $approvals, $description]) {
            DB::table('transaction_types')->insert([
                'code' => $code, 'name' => $name, 'direction' => $direction, 'required_approvals' => $approvals,
                'description' => $description, 'approver_roles' => $approvers, 'is_system' => true, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (FundRules::DEFINITIONS as $key => $definition) {
            DB::table('application_settings')->updateOrInsert(['key' => $key], [
                'group' => 'règlement du fonds', 'label' => $definition['label'], 'value' => (string) $definition['default'],
                'type' => $definition['type'], 'description' => $definition['description'] ?? null, 'is_public' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('application_settings')->updateOrInsert(['key' => 'default_currency'], [
            'group' => 'general', 'label' => 'Devise par défaut', 'value' => 'USD', 'type' => 'string', 'is_public' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Tout le fonds fonctionne en dollars américains : les montants existants sont conservés,
        // seule l'unité change (données de recette).
        foreach (['contribution_plans', 'contribution_payments', 'contribution_dues', 'fund_accounts', 'fund_movements', 'projects'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency')) {
                DB::table($table)->where('currency', 'EUR')->update(['currency' => 'USD']);
            }
        }
    }

    public function down(): void
    {
        DB::table('application_settings')->whereIn('key', array_keys(FundRules::DEFINITIONS))->delete();
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['policy_accepted_at', 'policy_accepted_version', 'policy_accepted_ip']));
        Schema::dropIfExists('transaction_types');
        Schema::table('member_profiles', fn (Blueprint $table) => $table->dropConstrainedForeignId('zone_id'));
        Schema::dropIfExists('zone_user');
        Schema::dropIfExists('zones');
    }
};
