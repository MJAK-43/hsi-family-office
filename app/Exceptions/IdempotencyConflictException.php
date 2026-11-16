<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cette clé d’idempotence a déjà été utilisée avec des données différentes.');
    }
}
