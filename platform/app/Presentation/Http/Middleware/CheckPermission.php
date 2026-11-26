<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Domain\Tenants\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckPermission
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $this->tenantContext->hasPermission($permission)) {
            abort(403, "Missing required permission [{$permission}].");
        }

        return $next($request);
    }
}
