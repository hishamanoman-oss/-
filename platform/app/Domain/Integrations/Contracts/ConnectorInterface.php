<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Synchronization\Models\SyncCursor;

interface ConnectorInterface
{
    /**
     * @return array{ok: bool, engine?: string, database?: string, error?: string}
     */
    public function testConnection(): array;

    /**
     * Discover tables and columns safely from source metadata.
     *
     * @return array<string, array<string, string>>
     */
    public function discoverSchema(): array;

    public function inspectCapabilities(): ConnectorCapabilityContract;

    /**
     * Fetch incremental rows after the given cursor safely (whitelisted table/column only).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchChanges(SyncCursor $cursor, int $limit = 500): array;

    public function disconnect(): void;
}
