<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditCriticalIntegrityCommand extends Command
{
    protected $signature = 'hsi:audit-critical-integrity
        {--orphans-only : Vérifier uniquement les références orphelines avant une migration}';

    protected $description = 'Audite les références critiques et leurs règles de suppression';

    /** @var array<int, array{child: string, column: string, parent: string}> */
    private const RELATIONS = [
        ['child' => 'member_profiles', 'column' => 'user_id', 'parent' => 'users'],
        ['child' => 'contribution_payments', 'column' => 'user_id', 'parent' => 'users'],
        ['child' => 'projects', 'column' => 'owner_id', 'parent' => 'users'],
        ['child' => 'vote_ballots', 'column' => 'user_id', 'parent' => 'users'],
        ['child' => 'fund_movements', 'column' => 'fund_account_id', 'parent' => 'fund_accounts'],
    ];

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();
        $checkDeleteRules = ! $this->option('orphans-only');
        $failed = false;
        $rows = [];

        if ($checkDeleteRules && $driver !== 'mysql') {
            $this->error('La vérification des règles ON DELETE exige une connexion MySQL/MariaDB.');

            return self::FAILURE;
        }

        foreach (self::RELATIONS as $relation) {
            $childExists = Schema::hasTable($relation['child']);
            $parentExists = Schema::hasTable($relation['parent']);
            $tablesExist = $childExists && $parentExists;
            $orphans = $tablesExist ? $this->orphanCount($relation) : null;
            $deleteRule = $checkDeleteRules && $tablesExist
                ? $this->deleteRule($relation)
                : 'non vérifiée';

            $futureChildIsValid = ! $checkDeleteRules && ! $childExists && $parentExists;
            $valid = $futureChildIsValid || ($tablesExist
                && $orphans === 0
                && (! $checkDeleteRules || in_array($deleteRule, ['RESTRICT', 'NO ACTION'], true)));

            $failed = $failed || ! $valid;
            $rows[] = [
                $relation['child'].'.'.$relation['column'],
                $relation['parent'].'.id',
                $tablesExist ? (string) $orphans : ($futureChildIsValid ? 'table future' : 'tables absentes'),
                $deleteRule ?? 'contrainte absente',
                $valid ? 'OK' : 'ÉCHEC',
            ];
        }

        $this->table(['Référence', 'Parent', 'Orphelins', 'ON DELETE', 'Résultat'], $rows);

        if ($failed) {
            $this->error('L’audit d’intégrité critique a échoué.');

            return self::FAILURE;
        }

        $this->info('L’audit d’intégrité critique est conforme.');

        return self::SUCCESS;
    }

    /** @param array{child: string, column: string, parent: string} $relation */
    private function orphanCount(array $relation): int
    {
        return DB::table($relation['child'].' as child')
            ->leftJoin($relation['parent'].' as parent', 'child.'.$relation['column'], '=', 'parent.id')
            ->whereNotNull('child.'.$relation['column'])
            ->whereNull('parent.id')
            ->count();
    }

    /** @param array{child: string, column: string, parent: string} $relation */
    private function deleteRule(array $relation): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE as kcu')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as rc', function ($join): void {
                $join->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.CONSTRAINT_SCHEMA')
                    ->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME')
                    ->on('rc.TABLE_NAME', '=', 'kcu.TABLE_NAME');
            })
            ->where('kcu.CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('kcu.TABLE_NAME', $relation['child'])
            ->where('kcu.COLUMN_NAME', $relation['column'])
            ->where('kcu.REFERENCED_TABLE_NAME', $relation['parent'])
            ->value('rc.DELETE_RULE');
    }
}
