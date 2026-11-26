<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Tenants\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateConnectorInstance
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken();
        if (! is_string($rawToken) || trim($rawToken) === '') {
            return response()->json(['message' => 'Missing connector bearer token.'], 401);
        }

        $tokenHash = hash('sha256', trim($rawToken));

        /** @var ConnectorInstance|null $instance */
        $instance = $this->tenantContext->runWithoutTenantScope(fn () => ConnectorInstance::query()
            ->with(['tenant', 'dataSource'])
            ->where('token_hash', $tokenHash)
            ->where('status', 'active')
            ->first());

        if (
            $instance === null
            || $instance->tenant === null
            || $instance->tenant->status !== 'active'
            || $instance->dataSource === null
        ) {
            return response()->json(['message' => 'Invalid or revoked connector token.'], 401);
        }

        $this->tenantContext->bind($instance->tenant, null);
        $request->attributes->set('connector_instance', $instance);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
