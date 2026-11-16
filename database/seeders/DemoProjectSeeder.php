<?php

namespace Database\Seeders;

use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\Models\Country;
use App\Models\Project;
use App\Models\ProjectEvaluation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $cameroun = Country::where('code', 'CM')->first();
        $france = Country::where('code', 'FR')->first();
        $belgique = Country::where('code', 'BE')->first();

        $marc = User::where('email', 'marc.d@hsi-family-office.test')->first();
        $sophie = User::where('email', 'sophie.n@hsi-family-office.test')->first();
        $claire = User::where('email', 'claire.d@hsi-family-office.test')->first();
        $philippe = User::where('email', 'philippe.r@hsi-family-office.test')->first();
        $admin = User::where('email', 'admin@hsi-family-office.test')->first();

        $president = User::updateOrCreate(
            ['email' => 'president@hsi-family-office.test'],
            [
                'name' => 'Jean-Pierre H.',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $president->syncRoles(['president']);

        $committee = User::updateOrCreate(
            ['email' => 'comite@hsi-family-office.test'],
            [
                'name' => 'Dr. Emmanuel K.',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $committee->syncRoles(['investment_committee']);

        $projects = [
            [
                'slug' => 'pisciculture-moderne-cameroun',
                'title' => 'Pisciculture moderne au Cameroun',
                'owner' => $marc,
                'country_id' => $cameroun?->id,
                'city' => 'Douala',
                'sector' => ProjectSector::FishFarming,
                'total_budget' => 200000,
                'personal_contribution' => 120000,
                'requested_amount' => 80000,
                'status' => ProjectStatus::Draft,
                'description' => 'Ferme piscicole moderne avec bassins automatisés pour tilapia et carpe.',
            ],
            [
                'slug' => 'residence-etudiante-yaounde',
                'title' => 'Résidence étudiante à Yaoundé',
                'owner' => $philippe,
                'country_id' => $cameroun?->id,
                'city' => 'Yaoundé',
                'sector' => ProjectSector::RealEstate,
                'total_budget' => 500000,
                'personal_contribution' => 300000,
                'requested_amount' => 200000,
                'status' => ProjectStatus::Submitted,
                'submitted_at' => now()->subDays(5),
                'description' => 'Résidence de 80 chambres pour étudiants près de l\'université.',
            ],
            [
                'slug' => 'plateforme-elearning-hsi-academy',
                'title' => 'Plateforme e-learning HSI Academy',
                'owner' => $sophie,
                'country_id' => $belgique?->id,
                'city' => 'Bruxelles',
                'sector' => ProjectSector::Education,
                'total_budget' => 80000,
                'personal_contribution' => 50000,
                'requested_amount' => 30000,
                'status' => ProjectStatus::UnderReview,
                'submitted_at' => now()->subDays(12),
                'description' => 'Plateforme de formation en ligne pour les membres de la famille HSI.',
            ],
            [
                'slug' => 'agriculture-avocat-hass',
                'title' => 'Agriculture avocat Hass',
                'owner' => $marc,
                'country_id' => $cameroun?->id,
                'city' => 'Bafoussam',
                'sector' => ProjectSector::Agriculture,
                'total_budget' => 150000,
                'personal_contribution' => 90000,
                'requested_amount' => 60000,
                'status' => ProjectStatus::ApprovedByCommittee,
                'submitted_at' => now()->subDays(20),
                'reviewed_at' => now()->subDays(3),
                'reviewed_by' => $committee?->id,
                'committee_decision_comment' => 'Projet solide avec bon potentiel de rentabilité et impact familial.',
                'description' => 'Plantation de 20 hectares d\'avocat Hass pour export.',
            ],
            [
                'slug' => 'centre-medical-familial',
                'title' => 'Centre médical familial',
                'owner' => $claire,
                'country_id' => $france?->id,
                'city' => 'Lyon',
                'sector' => ProjectSector::Health,
                'total_budget' => 350000,
                'personal_contribution' => 200000,
                'requested_amount' => 150000,
                'status' => ProjectStatus::RejectedByCommittee,
                'submitted_at' => now()->subDays(15),
                'reviewed_at' => now()->subDays(2),
                'reviewed_by' => $committee?->id,
                'rejected_reason' => 'Le plan de financement nécessite des ajustements sur la partie exploitation.',
                'description' => 'Centre de santé pluridisciplinaire pour les membres de la famille.',
            ],
            [
                'slug' => 'application-mobile-services-familiaux',
                'title' => 'Application mobile de services familiaux',
                'owner' => $sophie,
                'country_id' => $france?->id,
                'city' => 'Paris',
                'sector' => ProjectSector::Technology,
                'total_budget' => 60000,
                'personal_contribution' => 35000,
                'requested_amount' => 25000,
                'status' => ProjectStatus::SubmittedToVote,
                'submitted_at' => now()->subDays(25),
                'reviewed_at' => now()->subDays(8),
                'reviewed_by' => $committee?->id,
                'submitted_to_vote_at' => now()->subDays(1),
                'committee_decision_comment' => 'Innovation alignée avec les valeurs HSI. Recommandé pour vote familial.',
                'description' => 'Application mobile centralisant les services HSI Family Office.',
            ],
        ];

        foreach ($projects as $data) {
            $owner = $data['owner'];
            unset($data['owner']);

            $project = Project::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'owner_id' => $owner?->id ?? $admin?->id,
                    'currency' => 'USD',
                    'expected_duration_months' => 24,
                    'expected_roi_percentage' => 12.5,
                    'short_description' => mb_substr($data['description'], 0, 120),
                    'created_by' => $owner?->id ?? $admin?->id,
                ],
            );

            if (in_array($project->status, [ProjectStatus::UnderReview, ProjectStatus::ApprovedByCommittee, ProjectStatus::SubmittedToVote], true)) {
                ProjectEvaluation::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'evaluator_id' => $committee?->id,
                    ],
                    [
                        'profitability_score' => 16,
                        'risk_score' => 12,
                        'founder_score' => 18,
                        'family_impact_score' => 17,
                        'sustainability_score' => 15,
                        'values_alignment_score' => 19,
                        'total_score' => 97,
                        'recommendation' => 'Favorable',
                        'comment' => 'Évaluation positive du comité investissement.',
                        'evaluated_at' => now()->subDays(10),
                    ],
                );
            }
        }
    }
}
