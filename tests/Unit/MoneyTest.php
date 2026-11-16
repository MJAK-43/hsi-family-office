<?php

namespace Tests\Unit;

use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('decimalAmounts')]
    public function test_it_parses_decimal_amounts_without_float(string|int $input, int $minorUnits, string $decimal): void
    {
        $money = Money::fromDecimal($input, 'EUR');

        $this->assertSame($minorUnits, $money->minorUnits);
        $this->assertSame($decimal, $money->decimal());
    }

    public static function decimalAmounts(): array
    {
        return [
            'integer' => [12, 1200, '12.00'],
            'one decimal' => ['12.3', 1230, '12.30'],
            'two decimals' => ['12.34', 1234, '12.34'],
            'leading zeroes' => ['00012.04', 1204, '12.04'],
            'negative' => ['-0.01', -1, '-0.01'],
        ];
    }

    public function test_it_adds_and_subtracts_exactly(): void
    {
        $first = Money::fromDecimal('0.10', 'EUR');
        $second = Money::fromDecimal('0.20', 'EUR');

        $this->assertSame('0.30', $first->add($second)->decimal());
        $this->assertSame('-0.10', $first->subtract($second)->decimal());
    }

    public function test_it_rejects_more_than_two_decimal_places_instead_of_rounding(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.001', 'EUR');
    }

    public function test_it_rejects_float_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal(10.25, 'EUR');
    }

    public function test_it_rejects_arithmetic_between_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.00', 'EUR')->add(Money::fromDecimal('10.00', 'USD'));
    }

    public function test_comparison_requires_and_preserves_currency(): void
    {
        $small = Money::fromDecimal('9.99', 'EUR');
        $large = Money::fromDecimal('10.00', 'EUR');

        $this->assertSame(-1, $small->compare($large));
        $this->assertTrue(Money::zero('EUR')->isZero());
        $this->assertTrue($small->subtract($large)->isNegative());
    }

    public function test_it_allocates_centimes_with_the_largest_remainder_method(): void
    {
        $allocations = Money::fromDecimal('0.03', 'EUR')
            ->allocateByBasisPoints([5000, 3000, 2000]);

        $this->assertSame(['0.01', '0.01', '0.01'], array_map(
            fn (Money $money): string => $money->decimal(),
            $allocations,
        ));
    }

    public function test_allocation_always_equals_the_original_amount(): void
    {
        $money = Money::fromDecimal('33.33', 'EUR');
        $allocations = $money->allocateByBasisPoints([4000, 3000, 2000, 1000]);

        $total = array_reduce(
            $allocations,
            fn (Money $carry, Money $allocation): Money => $carry->add($allocation),
            Money::zero('EUR'),
        );

        $this->assertTrue($money->equals($total));
        $this->assertSame(['13.33', '10.00', '6.67', '3.33'], array_map(
            fn (Money $allocation): string => $allocation->decimal(),
            $allocations,
        ));
    }

    public function test_allocation_requires_exactly_ten_thousand_basis_points(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.00', 'EUR')->allocateByBasisPoints([5000, 4999]);
    }
}
