<?php

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class Currency
{
    private function __construct(public string $code) {}

    public static function from(string $code): self
    {
        $normalized = strtoupper(trim($code));

        if (! preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new InvalidArgumentException('La devise doit être un code ISO composé de trois lettres.');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
