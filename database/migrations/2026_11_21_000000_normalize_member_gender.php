<?php

use App\Support\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le genre était saisi en texte libre dans la fiche membre (« Masculin », « Non précisé »…),
 * alors que le profil n'accepte que female / male / other : le formulaire du profil était refusé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_profiles')->whereNotNull('gender')->orderBy('id')->each(function ($row) {
            $code = Gender::normalize($row->gender);
            if ($code !== $row->gender) {
                DB::table('member_profiles')->where('id', $row->id)->update(['gender' => $code]);
            }
        });
    }

    public function down(): void
    {
        // Conversion sans retour : les anciennes valeurs libres ne sont pas restaurées.
    }
};
