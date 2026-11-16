<?php

namespace App\Services;

use App\Enums\MajorityRule;
use App\Enums\MemberStatus;
use App\Enums\ProjectStatus;
use App\Enums\VoteResult;
use App\Enums\VoteStatus;
use App\Enums\VoteType;
use App\Models\Project;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteConflict;
use App\Models\VoteEligibility;
use App\Models\VoteOption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use App\Notifications\FundNotification;
use App\Support\FundRules;
use App\Support\Notifier;

class VoteService
{
    /**
     * Vote des membres sur un projet accepté par le comité (section 5.4) : seuil de 80 % des voix pour et contre,
     * abstentions non comptées, vote secret, porteur du projet exclu. Ouvert tout de suite ou programmé.
     */
    public function openForProject(Project $project, User $creator, ?int $durationDays = null, ?string $opensAt = null, ?string $closesAt = null): Vote
    {
        if (! in_array($project->status, [ProjectStatus::SubmittedToVote, ProjectStatus::ApprovedByCommittee], true)) {
            throw new RuntimeException('Le projet doit être approuvé par le comité ou soumis au vote.');
        }

        $existing = Vote::query()->where('project_id', $project->id)
            ->whereIn('status', [VoteStatus::Draft->value, VoteStatus::Scheduled->value, VoteStatus::Open->value])->first();
        if ($existing) return $existing;

        $start = $opensAt ? \Carbon\Carbon::parse($opensAt, 'Europe/Paris')->utc() : now();
        $end = $closesAt ? \Carbon\Carbon::parse($closesAt, 'Europe/Paris')->utc() : $start->copy()->addDays($durationDays ?? FundRules::get('vote_duration_days'));
        if ($end->lte($start) || $end->isPast()) {
            throw new RuntimeException('La date de clôture doit être postérieure à l’ouverture et dans le futur.');
        }
        $scheduled = $start->isFuture() && $start->diffInMinutes(now(), true) > 1;

        $vote = DB::transaction(function () use ($project, $creator, $start, $end, $scheduled) {
            $vote = Vote::create([
                'title' => 'Financement — '.$project->title,
                'description' => 'Vote des membres sur la demande « '.$project->title.' » ('.($project->type?->name ?? 'Projet').'). Montant demandé : '.$project->requested_amount.' '.$project->currency.'. Approbation à '.FundRules::get('vote_approval_percent').' % des voix pour et contre.',
                'type' => VoteType::ProjectFunding, 'project_id' => $project->id, 'status' => $scheduled ? VoteStatus::Scheduled : VoteStatus::Open,
                'opens_at' => $scheduled ? $start : now(), 'closes_at' => $end, 'quorum_percent' => 0,
                'majority_rule' => MajorityRule::Threshold, 'allow_abstention' => true, 'eligible_count' => 0,
                'ballot_visibility' => 'secret', 'created_by' => $creator->id,
            ]);
            $this->createDefaultOptions($vote);
            activity('votes')->performedOn($vote)->causedBy($creator)->event('vote.created')
                ->withProperties(['project_id' => $project->id, 'opens_at' => $vote->opens_at?->toDateTimeString(), 'closes_at' => $vote->closes_at?->toDateTimeString()])
                ->log($scheduled ? 'Vote des membres programmé' : 'Vote familial créé pour le projet');
            if (! $scheduled) {
                $this->snapshotEligibility($vote);
                activity('votes')->performedOn($vote)->causedBy($creator)->event('vote.opened')->log('Vote familial ouvert');
            }
            return $vote->fresh(['options']);
        });
        if (! $scheduled) $this->notifyOpened($vote);

        return $vote;
    }

    /** Modifier les dates d'un vote programmé (ouverture et clôture) ou ouvert (clôture seulement). */
    public function reschedule(Vote $vote, User $user, ?string $opensAt, string $closesAt): Vote
    {
        $end = \Carbon\Carbon::parse($closesAt, 'Europe/Paris')->utc();
        if ($vote->status === VoteStatus::Scheduled) {
            $start = $opensAt ? \Carbon\Carbon::parse($opensAt, 'Europe/Paris')->utc() : $vote->opens_at;
            if (! $start || $end->lte($start)) throw new RuntimeException('La clôture doit être postérieure à l’ouverture.');
            $vote->update(['opens_at' => $start, 'closes_at' => $end]);
        } elseif ($vote->status === VoteStatus::Open) {
            if ($end->isPast()) throw new RuntimeException('La nouvelle date de clôture doit être dans le futur.');
            $vote->update(['closes_at' => $end]);
        } else {
            throw new RuntimeException('Seul un vote programmé ou ouvert peut être modifié.');
        }
        activity('votes')->performedOn($vote)->causedBy($user)->event('vote.rescheduled')
            ->withProperties(['opens_at' => $vote->opens_at?->toDateTimeString(), 'closes_at' => $vote->closes_at?->toDateTimeString()])
            ->log('Dates du vote modifiées');

        return $vote->fresh();
    }

    private function notifyOpened(Vote $vote): void
    {
        $voters = User::query()->whereIn('id', $vote->eligibilities()->where('is_eligible', true)->pluck('user_id'))->get();
        Notifier::send($voters, new FundNotification(
            ['Vote ouvert : :title', ['title' => $vote->title]],
            [['Vous pouvez voter jusqu’au :date.', ['date' => $vote->closes_at?->timezone('Europe/Paris')->format('d/m/Y H:i')]], ['Le vote est secret.']],
            route('votes.show', $vote), 'Voter', 'vote',
        ));
    }

    public function create(array $data, User $creator): Vote
    {
        return DB::transaction(function () use ($data, $creator) {
            $status = VoteStatus::from($data['status'] ?? VoteStatus::Open->value);
            $opensAt = ! empty($data['opens_at']) ? now()->parse($data['opens_at']) : null;
            if ($status === VoteStatus::Open && $opensAt?->isFuture()) $status = VoteStatus::Scheduled;

            $vote = Vote::create([
                'title' => $data['title'], 'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? VoteType::Other->value, 'project_id' => $data['project_id'] ?? null,
                'status' => $status, 'opens_at' => $status === VoteStatus::Open ? ($opensAt ?? now()) : $opensAt,
                'closes_at' => $data['closes_at'] ?? now()->addDays(7), 'quorum_percent' => $data['quorum_percent'] ?? 50,
                'quorum_absolute' => $data['quorum_absolute'] ?? null,
                'majority_rule' => $data['majority_rule'] ?? MajorityRule::Simple->value,
                'allow_abstention' => $data['allow_abstention'] ?? true,
                'ballot_visibility' => $data['ballot_visibility'] ?? 'named', 'eligible_count' => 0,
                'created_by' => $creator->id,
            ]);
            $this->createOptions($vote, $data['options'] ?? [], (bool) ($data['allow_abstention'] ?? true));
            if ($status === VoteStatus::Open) $this->snapshotEligibility($vote);
            activity('votes')->performedOn($vote)->causedBy($creator)->event('vote.created')->log('Vote créé');
            if ($status === VoteStatus::Open) activity('votes')->performedOn($vote)->causedBy($creator)->event('vote.opened')->log('Vote ouvert');
            return $vote->fresh(['options']);
        });
    }

    public function castBallot(Vote $vote, User $user, string $optionValue, ?string $comment = null): VoteBallot
    {
        return DB::transaction(function () use ($vote, $user, $optionValue, $comment) {
            $vote = Vote::query()->lockForUpdate()->findOrFail($vote->id);
            if ($vote->status !== VoteStatus::Open || $vote->isExpired()) throw new RuntimeException('Ce vote n’est plus ouvert.');
            $eligibility = VoteEligibility::query()->where('vote_id', $vote->id)->where('user_id', $user->id)->first();
            if (! $eligibility?->is_eligible) throw new RuntimeException('Vous ne figurez pas dans la liste électorale figée pour ce vote.');
            if (VoteBallot::where('vote_id', $vote->id)->where('user_id', $user->id)->exists()) throw new RuntimeException('Vous avez déjà voté.');
            $option = $vote->options()->where('value', $optionValue)->first();
            if (! $option) throw new InvalidArgumentException('Option de vote invalide.');
            if ($optionValue === 'abstain' && ! $vote->allow_abstention) throw new InvalidArgumentException('L’abstention n’est pas autorisée.');
            try {
                $ballot = VoteBallot::create(['vote_id' => $vote->id, 'user_id' => $user->id, 'vote_option_id' => $option->id, 'comment' => $comment, 'cast_at' => now()]);
            } catch (QueryException) {
                throw new RuntimeException('Votre bulletin a déjà été enregistré.');
            }
            $this->refreshCounts($vote);
            activity('votes')->performedOn($vote)->causedBy($user)->event('vote.ballot_cast')
                ->withProperties($vote->hasSecretBallots() ? [] : ['option' => $option->value])->log('Bulletin de vote enregistré');
            return $ballot;
        });
    }

    public function close(Vote $vote, User $user): Vote
    {
        return DB::transaction(function () use ($vote, $user) {
            $vote = Vote::query()->lockForUpdate()->findOrFail($vote->id);
            if ($vote->status !== VoteStatus::Open) throw new RuntimeException('Seuls les votes ouverts peuvent être clôturés.');
            $this->refreshCounts($vote);
            $result = $this->computeResult($vote);
            $vote->update(['status' => VoteStatus::Closed, 'result' => $result, 'closed_by' => $user->id, 'closed_at' => now()]);
            if ($vote->type === VoteType::ProjectFunding) $this->markBinaryWinner($vote, $result);
            $this->applyProjectDecision($vote, $result, $user);
            activity('votes')->performedOn($vote)->causedBy($user)->event('vote.closed')->withProperties(['result' => $result->value])->log('Vote clôturé');
            return $vote->fresh(['options', 'project', 'winningOption']);
        });
    }

    public function cancel(Vote $vote, User $user): Vote
    {
        if (! in_array($vote->status, [VoteStatus::Draft, VoteStatus::Scheduled, VoteStatus::Open], true)) throw new RuntimeException('Ce vote ne peut pas être annulé.');
        $vote->update(['status' => VoteStatus::Cancelled, 'closed_by' => $user->id, 'closed_at' => now()]);
        activity('votes')->performedOn($vote)->causedBy($user)->event('vote.cancelled')->log('Vote annulé');
        return $vote->fresh();
    }

    public function openScheduled(): int
    {
        $count = 0;
        Vote::query()->scheduled()->whereNotNull('opens_at')->where('opens_at', '<=', now())->each(function (Vote $vote) use (&$count) {
            DB::transaction(function () use ($vote, &$count) {
                $locked = Vote::query()->lockForUpdate()->findOrFail($vote->id);
                if ($locked->status !== VoteStatus::Scheduled) return;
                $this->snapshotEligibility($locked);
                $locked->update(['status' => VoteStatus::Open]);
                activity('votes')->performedOn($locked)->event('vote.opened')->log('Vote ouvert automatiquement');
                $count++;
                $this->notifyOpened($locked->fresh());
            });
        });
        return $count;
    }

    public function closeExpired(): int
    {
        $systemUser = User::query()->role('super_admin')->first() ?? User::query()->role('president')->first();
        if (! $systemUser) return 0;
        $count = 0;
        Vote::query()->open()->whereNotNull('closes_at')->where('closes_at', '<=', now())->each(function (Vote $vote) use ($systemUser, &$count) {
            $this->close($vote, $systemUser); $count++;
        });
        return $count;
    }

    public function snapshotEligibility(Vote $vote): void
    {
        $users = User::query()->permission('cast votes')->where(function ($query) {
            $query->whereDoesntHave('memberProfile')->orWhereHas('memberProfile', fn ($profile) => $profile->where('status', MemberStatus::Active));
        })->get(['id']);
        $excluded = VoteConflict::query()->where('vote_id', $vote->id)->where('status', 'confirmed')->pluck('reason', 'user_id');
        // Le porteur d'un projet ne vote pas sur sa propre demande (section 2.2).
        if ($vote->project_id && $ownerId = $vote->project?->owner_id) {
            $excluded->put($ownerId, 'Porteur du projet');
        }
        foreach ($users as $user) {
            VoteEligibility::updateOrCreate(['vote_id' => $vote->id, 'user_id' => $user->id], [
                'is_eligible' => ! $excluded->has($user->id), 'weight' => 1, 'exclusion_reason' => $excluded->get($user->id),
            ]);
        }
        $vote->update(['eligible_count' => $vote->eligibilities()->where('is_eligible', true)->sum('weight'), 'eligibility_locked_at' => now()]);
    }

    public function declareConflict(Vote $vote, User $subject, User $declarer, string $reason): VoteConflict
    {
        if (! in_array($vote->status, [VoteStatus::Draft, VoteStatus::Scheduled], true)) throw new RuntimeException('Un conflit doit être déclaré avant l’ouverture du vote.');
        return VoteConflict::updateOrCreate(['vote_id' => $vote->id, 'user_id' => $subject->id], [
            'reason' => $reason, 'status' => 'pending', 'declared_by' => $declarer->id,
            'resolved_by' => null, 'resolved_at' => null, 'resolution_note' => null,
        ]);
    }

    public function resolveConflict(VoteConflict $conflict, User $resolver, bool $confirmed, ?string $note): VoteConflict
    {
        if (! in_array($conflict->vote->status, [VoteStatus::Draft, VoteStatus::Scheduled], true)) throw new RuntimeException('La liste électorale est déjà figée.');
        $conflict->update(['status' => $confirmed ? 'confirmed' : 'rejected', 'resolved_by' => $resolver->id, 'resolved_at' => now(), 'resolution_note' => $note]);
        return $conflict->fresh();
    }

    public function eligibleVotersCount(): int
    {
        return User::query()->permission('cast votes')->where(function ($query) {
            $query->whereDoesntHave('memberProfile')->orWhereHas('memberProfile', fn ($profile) => $profile->where('status', MemberStatus::Active));
        })->count();
    }

    public function refreshCounts(Vote $vote): void
    {
        $options = $vote->options()->withCount('ballots')->get()->keyBy('value');
        $vote->update([
            'ballots_count' => $vote->ballots()->count(), 'for_count' => $options->get('for')?->ballots_count ?? 0,
            'against_count' => $options->get('against')?->ballots_count ?? 0, 'abstain_count' => $options->get('abstain')?->ballots_count ?? 0,
            'eligible_count' => $vote->eligibility_locked_at ? $vote->eligible_count : $this->eligibleVotersCount(),
        ]);
        $vote->refresh();
    }

    public function computeResult(Vote $vote): VoteResult
    {
        $this->refreshCounts($vote);
        if (! $vote->quorumReached()) return VoteResult::NoQuorum;
        if ($vote->type !== VoteType::ProjectFunding) return $this->computeGenericResult($vote);
        $for = (int) $vote->for_count; $against = (int) $vote->against_count; $expressed = $for + $against; $eligible = max(1, (int) $vote->eligible_count);
        if ($vote->majority_rule === MajorityRule::Threshold) {
            if ($expressed === 0) return VoteResult::NoQuorum;
            return $for / $expressed * 100 >= FundRules::get('vote_approval_percent') ? VoteResult::Approved : VoteResult::Rejected;
        }
        return match ($vote->majority_rule) {
            MajorityRule::Absolute => $for > $eligible * .5 && $for > $against ? VoteResult::Approved : ($against > $eligible * .5 && $against > $for ? VoteResult::Rejected : VoteResult::Tied),
            MajorityRule::Supermajority => $expressed && $for / $expressed >= 2 / 3 ? VoteResult::Approved : ($expressed && $against / $expressed >= 2 / 3 ? VoteResult::Rejected : VoteResult::Tied),
            default => $expressed === 0 || $for === $against ? VoteResult::Tied : ($for > $against ? VoteResult::Approved : VoteResult::Rejected),
        };
    }

    private function computeGenericResult(Vote $vote): VoteResult
    {
        $tallies = $vote->options()->withCount('ballots')->get();
        $candidates = $tallies->reject(fn ($option) => $option->value === 'abstain');
        $max = (int) ($candidates->max('ballots_count') ?? 0); $winners = $candidates->where('ballots_count', $max);
        $vote->update(['result_summary' => $tallies->mapWithKeys(fn ($option) => [$option->value => $option->ballots_count])->all()]);
        if ($max === 0 || $winners->count() !== 1) return VoteResult::Tied;
        $winner = $winners->first(); $vote->options()->update(['is_winning' => false]); $winner->update(['is_winning' => true]);
        $vote->update(['winning_option_id' => $winner->id]);
        return VoteResult::Approved;
    }

    private function markBinaryWinner(Vote $vote, VoteResult $result): void
    {
        $vote->options()->update(['is_winning' => false]);
        $value = $result === VoteResult::Approved ? 'for' : ($result === VoteResult::Rejected ? 'against' : null);
        $winner = $value ? $vote->options()->where('value', $value)->first() : null;
        if ($winner) $winner->update(['is_winning' => true]);
        $vote->update(['winning_option_id' => $winner?->id]);
    }

    private function applyProjectDecision(Vote $vote, VoteResult $result, User $user): void
    {
        if (! $vote->project_id || $vote->type !== VoteType::ProjectFunding) return;
        $project = $vote->project;
        if (! $project || $project->status !== ProjectStatus::SubmittedToVote) return;
        if ($result === VoteResult::Approved) $project->update(['status' => ProjectStatus::ApprovedByFamily, 'decided_at' => now()]);
        elseif ($result === VoteResult::Rejected) $project->update(['status' => ProjectStatus::RejectedByFamily, 'decided_at' => now(), 'rejected_reason' => 'Vote des membres : '.$vote->for_count.' pour, '.$vote->against_count.' contre (seuil '.FundRules::get('vote_approval_percent').' %).']);
        else return;
        Notifier::send([$project->owner, ...Notifier::committee(), ...Notifier::treasurers()], new FundNotification(
            [$result === VoteResult::Approved ? 'Demande approuvée par les membres : :title' : 'Demande refusée par les membres : :title', ['title' => $project->title]],
            [['Résultat : :for pour, :against contre, :abstain abstention(s).', ['for' => $vote->for_count, 'against' => $vote->against_count, 'abstain' => $vote->abstain_count]],
             [$result === VoteResult::Approved ? 'Le décaissement peut maintenant être préparé par les trésoriers.' : 'Un nouveau dépôt du même projet sera possible après 3 mois, un autre projet après 30 jours.']],
            route('projects.show', $project), 'Voir le dossier', 'project',
        ));
        activity('projects')->performedOn($project)->causedBy($user)->event($result === VoteResult::Approved ? 'project.approved_by_family' : 'project.rejected_by_family')
            ->withProperties(['vote_id' => $vote->id])->log($result === VoteResult::Approved ? 'Projet approuvé par le vote familial' : 'Projet rejeté par le vote familial');
    }

    private function createDefaultOptions(Vote $vote, bool $allowAbstention = true): void
    {
        $options = [['label' => 'Pour', 'value' => 'for'], ['label' => 'Contre', 'value' => 'against']];
        if ($allowAbstention) $options[] = ['label' => 'Abstention', 'value' => 'abstain'];
        foreach ($options as $index => $option) VoteOption::create(['vote_id' => $vote->id, ...$option, 'sort_order' => $index + 1]);
    }

    private function createOptions(Vote $vote, array $customOptions, bool $allowAbstention): void
    {
        $labels = collect($customOptions)->map(fn ($option) => trim(is_array($option) ? ($option['label'] ?? '') : (string) $option))->filter()->unique()->values();
        if ($labels->count() < 2) { $this->createDefaultOptions($vote, $allowAbstention); return; }
        foreach ($labels as $index => $label) VoteOption::create(['vote_id' => $vote->id, 'label' => $label, 'value' => 'option_'.($index + 1), 'sort_order' => $index + 1]);
        if ($allowAbstention) VoteOption::create(['vote_id' => $vote->id, 'label' => 'Abstention', 'value' => 'abstain', 'sort_order' => $labels->count() + 1]);
    }
}
