<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Finance\Support\DecimalMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DecimalMathTest extends TestCase
{
    public function test_exact_bcmath_addition_and_multiplication_have_no_float_drift(): void
    {
        $this->assertSame('0.3000', DecimalMath::add('0.1', '0.2'));
        $this->assertSame('59.9850', DecimalMath::mul('19.9950', 3));
        $this->assertSame('940.0000', DecimalMath::sub('1000.0000', '60.0000'));
        $this->assertSame('33.3333', DecimalMath::div('100', '3'));
        $this->assertTrue(DecimalMath::eq('10.50', '10.5000'));
        $this->assertSame('1,234.50', DecimalMath::formatDisplay('1234.5000', 2));
    }

    public function test_invalid_numeric_string_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMath::normalize('not-a-number');
    }
}
