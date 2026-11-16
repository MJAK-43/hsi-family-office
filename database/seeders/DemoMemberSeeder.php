<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Models\Country;
use App\Models\FamilyBranch;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoMemberSeeder extends Seeder
{
    public function run(): void
    {
        $france = Country::where('code', 'FR')->first();
        $suisse = Country::where('code', 'CH')->first();
        $belgique = Country::where('code', 'BE')->first();

        $finance = FamilyBranch::where('slug', 'finance')->first();
        $immobilier = FamilyBranch::where('slug', 'immobilier')->first();
        $industrie = FamilyBranch::where('slug', 'industrie')->first();
        $sante = FamilyBranch::where('slug', 'sante')->first();
        $tech = FamilyBranch::where('slug', 'technologie')->first();
        $education = FamilyBranch::where('slug', 'education')->first();

        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@hsi-family-office.test'],
            [
                'name' => 'Andy N.',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->syncRoles(['super_admin']);

        MemberProfile::updateOrCreate(
            ['user_id' => $superAdmin->id],
            [
                'country_id' => $france?->id,
                'family_branch_id' => $finance?->id,
                'phone' => '+33 6 12 34 56 78',
                'city' => 'Paris',
                'address' => 'Paris, France',
                'profession' => 'Gestionnaire de patrimoine',
                'bio' => 'Membre fondateur et administrateur du Family Office HSI. Spécialisé en gestion d\'actifs et gouvernance familiale.',
                'date_of_birth' => '1985-02-14',
                'gender' => 'male',
                'languages' => ['Français', 'Anglais'],
                'member_since' => '2022-06-12',
                'status' => MemberStatus::Active,
            ],
        );

        $demoMembers = [
            [
                'name' => 'Mme Claire D.',
                'email' => 'claire.d@hsi-family-office.test',
                'role' => 'secretary',
                'country_id' => $france?->id,
                'family_branch_id' => $sante?->id,
                'profession' => 'Avocate',
                'city' => 'Lyon',
                'status' => MemberStatus::Active,
                'member_since' => '2023-01-15',
            ],
            [
                'name' => 'Marc D.',
                'email' => 'marc.d@hsi-family-office.test',
                'role' => 'member',
                'country_id' => $suisse?->id,
                'family_branch_id' => $immobilier?->id,
                'profession' => 'Entrepreneur',
                'city' => 'Genève',
                'status' => MemberStatus::Active,
                'member_since' => '2023-03-20',
            ],
            [
                'name' => 'Sophie N.',
                'email' => 'sophie.n@hsi-family-office.test',
                'role' => 'member',
                'country_id' => $belgique?->id,
                'family_branch_id' => $education?->id,
                'profession' => 'Consultante',
                'city' => 'Bruxelles',
                'status' => MemberStatus::Pending,
                'member_since' => '2025-04-01',
            ],
            [
                'name' => 'Jules N.',
                'email' => 'jules.n@hsi-family-office.test',
                'role' => 'guest',
                'country_id' => $france?->id,
                'family_branch_id' => $tech?->id,
                'profession' => 'Ingénieur',
                'city' => 'Marseille',
                'status' => MemberStatus::Active,
                'member_since' => '2024-09-10',
            ],
            [
                'name' => 'Thomas L.',
                'email' => 'thomas.l@hsi-family-office.test',
                'role' => 'member',
                'country_id' => $france?->id,
                'family_branch_id' => $industrie?->id,
                'profession' => 'Directeur industriel',
                'city' => 'Lille',
                'status' => MemberStatus::Suspended,
                'member_since' => '2022-11-05',
            ],
            [
                'name' => 'Isabelle M.',
                'email' => 'isabelle.m@hsi-family-office.test',
                'role' => 'treasurer',
                'country_id' => $suisse?->id,
                'family_branch_id' => $finance?->id,
                'profession' => 'Contrôleuse de gestion',
                'city' => 'Lausanne',
                'status' => MemberStatus::Active,
                'member_since' => '2021-08-18',
            ],
            [
                'name' => 'Philippe R.',
                'email' => 'philippe.r@hsi-family-office.test',
                'role' => 'member',
                'country_id' => $belgique?->id,
                'family_branch_id' => $immobilier?->id,
                'profession' => 'Architecte',
                'city' => 'Liège',
                'status' => MemberStatus::Inactive,
                'member_since' => '2020-05-22',
            ],
        ];

        foreach ($demoMembers as $member) {
            $role = $member['role'];
            unset($member['role']);

            $user = User::updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$role]);

            MemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'country_id' => $member['country_id'],
                    'family_branch_id' => $member['family_branch_id'],
                    'profession' => $member['profession'],
                    'city' => $member['city'],
                    'phone' => '+33 6 00 00 00 00',
                    'gender' => 'Non précisé',
                    'languages' => ['Français'],
                    'member_since' => $member['member_since'],
                    'status' => $member['status'],
                ],
            );
        }
    }
}
