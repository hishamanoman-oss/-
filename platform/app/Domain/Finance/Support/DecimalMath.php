<?php

declare(strict_types=1);

namespace App\Domain\Finance\Support;

use InvalidArgumentException;

final class DecimalMath
{
    public const SCALE = 4;

    public static function zero(): string
    {
        return '0.0000';
    }

    public static function normalize(string|int $value): string
    {
        if (is_int($value)) {
            return bcadd((string) $value, '0', self::SCALE);
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            throw new InvalidArgumentException("Invalid numeric string [{$value}] for exact decimal math.");
        }

        return bcadd($trimmed, '0', self::SCALE);
    }

    public static function add(string|int $left, string|int $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function sub(string|int $left, string|int $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function mul(string|int $left, string|int $right): string
    {
        return bcmul(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function div(string|int $left, string|int $right): string
    {
        $divisor = self::normalize($right);
        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero in DecimalMath.');
        }

        return bcdiv(self::normalize($left), $divisor, self::SCALE);
    }

    public static function comp(string|int $left, string|int $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function gt(string|int $left, string|int $right): bool
    {
        return self::comp($left, $right) > 0;
    }

    public static function gte(string|int $left, string|int $right): bool
    {
        return self::comp($left, $right) >= 0;
    }

    public static function lt(string|int $left, string|int $right): bool
    {
        return self::comp($left, $right) < 0;
    }

    public static function lte(string|int $left, string|int $right): bool
    {
        return self::comp($left, $right) <= 0;
    }

    public static function eq(string|int $left, string|int $right): bool
    {
        return self::comp($left, $right) === 0;
    }

    public static function maxZero(string|int $value): string
    {
        $norm = self::normalize($value);

        return self::lt($norm, '0') ? self::zero() : $norm;
    }

    public static function formatDisplay(string|int|null $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '—';
        }

        $norm = self::normalize($value);
        $negative = str_starts_with($norm, '-');
        $abs = $negative ? substr($norm, 1) : $norm;

        [$intPart, $fracPart] = array_pad(explode('.', $abs, 2), 2, '0000');
        $fracPart = str_pad($fracPart, self::SCALE, '0');
        $roundedFrac = substr($fracPart, 0, $decimals);

        // Group thousands in integer part without converting to float
        $rev = strrev($intPart);
        $grouped = strrev(implode(',', str_split($rev, 3)));
        $prefix = $negative ? '-' : '';

        return $decimals > 0 ? "{$prefix}{$grouped}.{$roundedFrac}" : "{$prefix}{$grouped}";
    }
}
