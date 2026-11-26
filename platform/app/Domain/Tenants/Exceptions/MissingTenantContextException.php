<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Exceptions;

use RuntimeException;

final class MissingTenantContextException extends RuntimeException
{
    public static function forModel(string $modelClass): self
    {
        return new self("Attempted tenant-scoped operation on [{$modelClass}] without an active TenantContext.");
    }
}
