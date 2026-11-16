<?php

namespace App\Console\Commands;

use App\Services\LoanService;
use Illuminate\Console\Command;

class ProcessLoansCommand extends Command
{
    protected $signature = 'loans:process';
    protected $description = 'Marquer les échéances de prêt en retard, calculer les pénalités et envoyer les rappels';

    public function handle(LoanService $loans): int
    {
        $stats = $loans->processDaily();
        $this->info("{$stats['late']} échéance(s) passée(s) en retard, {$stats['reminders']} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
