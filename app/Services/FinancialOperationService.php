<?php

namespace App\Services;

use App\Data\IdempotencyClaim;
use App\Exceptions\IdempotencyConflictException;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class FinancialOperationService
{
    /** @throws JsonException */
    public function claim(
        string $operationType,
        string $idempotencyKey,
        array $payload,
        ?User $createdBy = null,
    ): IdempotencyClaim {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Une opération idempotente doit être ouverte dans une transaction.');
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,150}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('La clé d’idempotence est invalide.');
        }

        $requestHash = hash('sha256', json_encode(
            $this->canonicalize([
                ...$payload,
                '_created_by' => $createdBy?->id,
            ]),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        $now = now();
        $inserted = DB::table('financial_operations')->insertOrIgnore([
            'operation_type' => $operationType,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'status' => 'processing',
            'created_by' => $createdBy?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $operation = FinancialOperation::query()
            ->where('operation_type', $operationType)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->firstOrFail();

        if ($inserted === 0) {
            if (! hash_equals($operation->request_hash, $requestHash)) {
                throw new IdempotencyConflictException;
            }

            if ($operation->status !== 'completed') {
                throw new RuntimeException('Une opération identique est déjà en cours.');
            }

            return new IdempotencyClaim($operation, replayed: true);
        }

        return new IdempotencyClaim($operation, replayed: false);
    }

    public function complete(FinancialOperation $operation, array $result): FinancialOperation
    {
        $operation->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);

        return $operation->fresh();
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
