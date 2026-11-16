<?php

namespace Database\Seeders;

use App\Enums\MajorityRule;
use App\Enums\VoteStatus;
use App\Enums\VoteType;
use App\Models\Project;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use App\Services\VoteService;
use Illuminate\Database\Seeder;

class DemoVoteSeeder extends Seeder
{
    public function run(): void
    {
        $president = User::where('email', 'president@hsi-family-office.test')->first()
            ?? User::where('email', 'admin@hsi-family-office.test')->first();

        $project = Project::where('slug', 'application-mobile-services-familiaux')->first();

        if ($project && $president) {
            $vote = app(VoteService::class)->openForProject($project, $president, 7);

            $voters = User::whereIn('email', [
                'marc.d@hsi-family-office.test',
                'claire.d@hsi-family-office.test',
                'isabelle.m@hsi-family-office.test',
            ])->get();

            $for = $vote->options()->where('value', 'for')->first();
            $against = $vote->options()->where('value', 'against')->first();

            foreach ($voters as $index => $voter) {
                if (VoteBallot::where('vote_id', $vote->id)->where('user_id', $voter->id)->exists()) {
                    continue;
                }

                $option = $index === 2 ? $against : $for;
                if (! $option) {
                    continue;
                }

                VoteBallot::create([
                    'vote_id' => $vote->id,
                    'user_id' => $voter->id,
                    'vote_option_id' => $option->id,
                    'comment' => $index === 0 ? 'Projet aligné avec nos valeurs.' : null,
                    'cast_at' => now()->subHours(6 - $index),
                ]);
            }

            app(VoteService::class)->refreshCounts($vote);
        }

        $admin = User::where('email', 'admin@hsi-family-office.test')->first();
        if (! $admin) {
            return;
        }

        $governance = Vote::updateOrCreate(
            ['slug' => 'validation-budget-solidarite-2026'],
            [
                'title' => 'Validation du budget solidarité 2026',
                'description' => 'Approuver l\'enveloppe solidarité pour l\'exercice en cours.',
                'type' => VoteType::Budget,
                'status' => VoteStatus::Open,
                'opens_at' => now()->subDays(2),
                'closes_at' => now()->addDays(12),
                'quorum_percent' => 50,
                'majority_rule' => MajorityRule::Simple,
                'allow_abstention' => true,
                'eligible_count' => app(VoteService::class)->eligibleVotersCount(),
                'created_by' => $admin->id,
            ],
        );

        if ($governance->options()->count() === 0) {
            foreach ([
                ['label' => 'Pour', 'value' => 'for', 'sort_order' => 1],
                ['label' => 'Contre', 'value' => 'against', 'sort_order' => 2],
                ['label' => 'Abstention', 'value' => 'abstain', 'sort_order' => 3],
            ] as $option) {
                VoteOption::create(['vote_id' => $governance->id, ...$option]);
            }
        }

        $marc = User::where('email', 'marc.d@hsi-family-office.test')->first();
        $forOption = $governance->options()->where('value', 'for')->first();
        if ($marc && $forOption && ! VoteBallot::where('vote_id', $governance->id)->where('user_id', $marc->id)->exists()) {
            VoteBallot::create([
                'vote_id' => $governance->id,
                'user_id' => $marc->id,
                'vote_option_id' => $forOption->id,
                'cast_at' => now()->subDay(),
            ]);
        }

        app(VoteService::class)->refreshCounts($governance);
    }
}
