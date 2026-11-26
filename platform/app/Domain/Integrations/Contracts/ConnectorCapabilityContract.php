<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

final readonly class ConnectorCapabilityContract
{
    /**
     * @param  list<string>  $allowedWriteCommands
     */
    public function __construct(
        public bool $supportsRead = true,
        public bool $supportsWrite = false,
        public bool $supportsCdc = false,
        public bool $supportsPolling = true,
        public bool $supportsWebhooks = false,
        public array $allowedWriteCommands = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supports_read' => $this->supportsRead,
            'supports_write' => $this->supportsWrite,
            'supports_cdc' => $this->supportsCdc,
            'supports_polling' => $this->supportsPolling,
            'supports_webhooks' => $this->supportsWebhooks,
            'allowed_write_commands' => $this->allowedWriteCommands,
        ];
    }
}
