<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use Carbon\CarbonImmutable;

final readonly class AnalyticsPeriod
{
    public function __construct(
        public string $key,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $previousStart,
        public CarbonImmutable $previousEnd,
    ) {}

    public static function resolve(
        string $key = '30d',
        ?string $customFrom = null,
        ?string $customTo = null,
        ?CarbonImmutable $now = null,
    ): self {
        $clock = $now ?? CarbonImmutable::now();

        [$start, $end] = match ($key) {
            'today' => [$clock->startOfDay(), $clock->endOfDay()],
            '7d' => [$clock->subDays(6)->startOfDay(), $clock->endOfDay()],
            'this_month' => [$clock->startOfMonth()->startOfDay(), $clock->endOfDay()],
            'custom' => self::parseCustomRange($customFrom, $customTo, $clock),
            default => [$clock->subDays(29)->startOfDay(), $clock->endOfDay()],
        };

        $normalizedKey = in_array($key, ['today', '7d', '30d', 'this_month', 'custom'], true)
            ? $key
            : '30d';

        $spanSeconds = max(1, (int) $start->diffInSeconds($end) + 1);
        $prevEnd = $start->subSecond();
        $prevStart = $prevEnd->subSeconds($spanSeconds - 1);

        return new self(
            key: $normalizedKey,
            start: $start,
            end: $end,
            previousStart: $prevStart,
            previousEnd: $prevEnd,
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function parseCustomRange(?string $from, ?string $to, CarbonImmutable $clock): array
    {
        try {
            $s = $from ? CarbonImmutable::parse($from)->startOfDay() : $clock->subDays(29)->startOfDay();
            $e = $to ? CarbonImmutable::parse($to)->endOfDay() : $clock->endOfDay();

            if ($e->lt($s)) {
                return [$e->startOfDay(), $s->endOfDay()];
            }

            return [$s, $e];
        } catch (\Throwable) {
            return [$clock->subDays(29)->startOfDay(), $clock->endOfDay()];
        }
    }
}
