<?php

namespace App\Console\Commands;

use App\Services\VoteService;
use Illuminate\Console\Command;

class CloseExpiredVotesCommand extends Command
{
    protected $signature = 'votes:close-expired';

    protected $description = 'Clôturer automatiquement les votes familiaux arrivés à échéance';

    public function handle(VoteService $voteService): int
    {
        $opened = $voteService->openScheduled();
        $closed = $voteService->closeExpired();
        $this->info("{$opened} vote(s) ouvert(s), {$closed} vote(s) clôturé(s).");

        return self::SUCCESS;
    }
}
