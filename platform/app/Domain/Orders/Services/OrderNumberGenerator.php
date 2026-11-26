<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Tenants\Exceptions\MissingTenantContextException;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\DB;

final class OrderNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Generates a collision-free monotonic order number per tenant inside the active transaction.
     */
    public function next(?int $tenantId = null): string
    {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        if ($resolvedTenantId === null) {
            throw MissingTenantContextException::forModel(self::class);
        }

        return DB::transaction(function () use ($resolvedTenantId): string {
            $row = DB::table('order_sequences')
                ->where('tenant_id', $resolvedTenantId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('order_sequences')->insert([
                    'tenant_id' => $resolvedTenantId,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $next = 1;
            } else {
                $next = ((int) $row->last_number) + 1;
                DB::table('order_sequences')
                    ->where('tenant_id', $resolvedTenantId)
                    ->update([
                        'last_number' => $next,
                        'updated_at' => now(),
                    ]);
            }

            return sprintf('ORD-%s-%06d', now()->format('Y'), $next);
        });
    }
}
