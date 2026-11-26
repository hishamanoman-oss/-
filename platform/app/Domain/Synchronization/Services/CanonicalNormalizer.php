<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Services;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\FieldMapping;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

final class CanonicalNormalizer
{
    /**
     * Transform a raw source row into canonical fields using configured FieldMapping rules.
     *
     * @param  array<string, mixed>  $rawRow
     * @param  Collection<int, FieldMapping>  $mappings
     * @return array<string, mixed>
     */
    public function normalize(string $canonicalEntity, array $rawRow, Collection $mappings): array
    {
        $entityMappings = $mappings->where('canonical_entity', $canonicalEntity);
        if ($entityMappings->isEmpty()) {
            throw new DomainException("No field mappings configured for canonical entity [{$canonicalEntity}].");
        }

        $normalized = [];

        foreach ($entityMappings as $mapping) {
            $rawValue = $rawRow[$mapping->source_column]
                ?? $rawRow[$mapping->canonical_field]
                ?? $mapping->default_value;

            if (($rawValue === null || $rawValue === '') && $mapping->is_required) {
                throw new DomainException(
                    "Missing required source column [{$mapping->source_table}.{$mapping->source_column}] for [{$canonicalEntity}.{$mapping->canonical_field}]."
                );
            }

            if ($rawValue === null || $rawValue === '') {
                $normalized[$mapping->canonical_field] = null;

                continue;
            }

            $normalized[$mapping->canonical_field] = $this->applyTransform(
                $rawValue,
                $mapping->transform_rule
            );
        }

        return $normalized;
    }

    private function applyTransform(mixed $value, ?string $rule): mixed
    {
        return match ($rule) {
            'trim' => trim((string) $value),
            'uppercase' => mb_strtoupper(trim((string) $value), 'UTF-8'),
            'decimal' => DecimalMath::normalize((string) $value),
            'iso_date' => CarbonImmutable::parse((string) $value)->utc()->toIso8601String(),
            'map_status' => $this->mapStatus((string) $value),
            default => is_string($value) ? trim($value) : $value,
        };
    }

    private function mapStatus(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'completed', 'paid', 'done', 'closed' => 'completed',
            'partial', 'partially_paid' => 'partial',
            'unpaid', 'open', 'pending' => 'unpaid',
            'refunded' => 'refunded',
            'cancelled', 'canceled', 'void' => 'cancelled',
            '1', 'true', 'active', 'yes' => 'active',
            '0', 'false', 'inactive', 'no' => 'inactive',
            default => strtolower(trim($raw)),
        };
    }
}
