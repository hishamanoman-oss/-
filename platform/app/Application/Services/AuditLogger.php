<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        string $entityType,
        string|int|null $entityId = null,
        array $metadata = [],
        ?int $tenantId = null,
        ?int $actorUserId = null,
    ): AuditLog {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        $resolvedTenantId = $tenantId ?? $context->id();
        $resolvedActorId = $actorUserId ?? Auth::id();
        $request = request();

        return AuditLog::query()->create([
            'tenant_id' => $resolvedTenantId,
            'actor_user_id' => $resolvedActorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'metadata_json' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
