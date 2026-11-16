<?php

namespace App\Console\Commands;

use App\Services\ContributionDueService;
use Illuminate\Console\Command;

class ProcessContributionDuesCommand extends Command
{
    protected $signature = 'contributions:process-dues';
    protected $description = 'Générer les échéances, marquer les retards et envoyer les relances';

    public function handle(ContributionDueService $service): int
    {
        $created = $service->generate();
        $result = $service->markLateAndRemind();
        $penalties = $service->applyPenalties();
        $this->info("{$created} échéance(s) créée(s), {$result['late']} retard(s), {$result['reminded']} relance(s), {$penalties} pénalité(s) mise(s) à jour.");
        return self::SUCCESS;
    }
}
