<?php

namespace App\Data;

use App\Models\FinancialOperation;

final readonly class IdempotencyClaim
{
    public function __construct(
        public FinancialOperation $operation,
        public bool $replayed,
    ) {}
}
