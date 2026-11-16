<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cotisations d'exemple (contribution volontaire fictive), supprimées avec les autres données d'exemple.
        Schema::table('contribution_payments', function (Blueprint $table) {
            $table->boolean('is_example')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_payments', fn (Blueprint $table) => $table->dropColumn('is_example'));
    }
};
