<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use OverflowException;

final readonly class Money
{
    private const MINOR_UNIT_FACTOR = 100;

    private function __construct(
        public int $minorUnits,
        public Currency $currency,
    ) {}

    public static function fromDecimal(mixed $amount, Currency|string $currency): self
    {
        if (! is_int($amount) && ! is_string($amount)) {
            throw new InvalidArgumentException('Le montant doit être fourni sous forme de chaîne décimale ou d’entier.');
        }

        $currency = is_string($currency) ? Currency::from($currency) : $currency;
        $normalized = trim((string) $amount);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Le montant doit être un nombre décimal avec au maximum deux décimales.');
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $maxWhole = (string) intdiv(PHP_INT_MAX - (int) $fraction, self::MINOR_UNIT_FACTOR);

        if (strlen($whole) > strlen($maxWhole)
            || (strlen($whole) === strlen($maxWhole) && strcmp($whole, $maxWhole) > 0)) {
            throw new OverflowException('Le montant dépasse la capacité entière de la plateforme.');
        }

        $minorUnits = ((int) $whole * self::MINOR_UNIT_FACTOR) + (int) $fraction;

        if (($matches[1] ?? '') === '-') {
            $minorUnits *= -1;
        }

        return new self($minorUnits, $currency);
    }

    public static function fromMinorUnits(int $minorUnits, Currency|string $currency): self
    {
        if ($minorUnits === PHP_INT_MIN) {
            throw new OverflowException('Le montant dépasse la capacité entière de la plateforme.');
        }

        return new self(
            $minorUnits,
            is_string($currency) ? Currency::from($currency) : $currency,
        );
    }

    public static function zero(Currency|string $currency): self
    {
        return self::fromMinorUnits(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinorUnits($this->checkedAdd($this->minorUnits, $other->minorUnits), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->minorUnits === PHP_INT_MIN) {
            throw new OverflowException('Le résultat dépasse la capacité entière de la plateforme.');
        }

        return self::fromMinorUnits($this->checkedAdd($this->minorUnits, -$other->minorUnits), $this->currency);
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    /**
     * Répartit un montant positif selon des poids exprimés en points de base.
     *
     * Les poids doivent totaliser exactement 10 000. Les centimes restants sont
     * distribués selon la méthode des plus forts restes, puis selon l'ordre
     * initial en cas d'égalité.
     *
     * @param  array<int, int>  $basisPoints
     * @return array<int, self>
     */
    public function allocateByBasisPoints(array $basisPoints): array
    {
        if ($this->isNegative()) {
            throw new InvalidArgumentException('Un montant négatif ne peut pas être réparti.');
        }

        if ($basisPoints === [] || array_sum($basisPoints) !== 10_000) {
            throw new InvalidArgumentException('Les points de base doivent totaliser exactement 10 000.');
        }

        foreach ($basisPoints as $basisPoint) {
            if (! is_int($basisPoint) || $basisPoint < 0 || $basisPoint > 10_000) {
                throw new InvalidArgumentException('Chaque poids doit être un entier compris entre 0 et 10 000.');
            }
        }

        $wholeUnits = intdiv($this->minorUnits, 10_000);
        $remainingUnits = $this->minorUnits % 10_000;
        $allocatedUnits = [];
        $remainders = [];

        foreach ($basisPoints as $index => $basisPoint) {
            $weightedRemainder = $remainingUnits * $basisPoint;
            $allocatedUnits[$index] = ($wholeUnits * $basisPoint) + intdiv($weightedRemainder, 10_000);
            $remainders[$index] = $weightedRemainder % 10_000;
        }

        $unitsToDistribute = $this->minorUnits - array_sum($allocatedUnits);
        $rankedIndexes = array_keys($basisPoints);
        usort($rankedIndexes, function (int $left, int $right) use ($remainders): int {
            return ($remainders[$right] <=> $remainders[$left]) ?: ($left <=> $right);
        });

        for ($offset = 0; $offset < $unitsToDistribute; $offset++) {
            $allocatedUnits[$rankedIndexes[$offset]]++;
        }

        ksort($allocatedUnits);

        return array_map(
            fn (int $minorUnits): self => self::fromMinorUnits($minorUnits, $this->currency),
            $allocatedUnits,
        );
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function decimal(): string
    {
        $absolute = abs($this->minorUnits);
        $whole = intdiv($absolute, self::MINOR_UNIT_FACTOR);
        $fraction = $absolute % self::MINOR_UNIT_FACTOR;
        $sign = $this->minorUnits < 0 ? '-' : '';

        return sprintf('%s%d.%02d', $sign, $whole, $fraction);
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency)
            && $this->minorUnits === $other->minorUnits;
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new InvalidArgumentException(sprintf(
                'Opération impossible entre les devises %s et %s.',
                $this->currency->code,
                $other->currency->code,
            ));
        }
    }

    private function checkedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new OverflowException('Le résultat dépasse la capacité entière de la plateforme.');
        }

        return $left + $right;
    }
}
