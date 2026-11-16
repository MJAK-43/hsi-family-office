<?php

namespace Tests\Unit;

use App\ValueObjects\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_it_normalizes_an_iso_currency_code(): void
    {
        $currency = Currency::from(' eur ');

        $this->assertSame('EUR', $currency->code);
        $this->assertSame('EUR', (string) $currency);
        $this->assertTrue($currency->equals(Currency::from('EUR')));
    }

    public function test_it_rejects_an_invalid_currency_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Currency::from('EURO');
    }
}
