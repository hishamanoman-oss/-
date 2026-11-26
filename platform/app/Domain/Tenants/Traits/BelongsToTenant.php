<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Traits;

use App\Domain\Tenants\Exceptions\MissingTenantContextException;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                /** @var TenantContext $context */
                $context = app(TenantContext::class);

                if ($context->isBypassed()) {
                    return;
                }

                $tenantId = $context->id();
                if ($tenantId === null) {
                    throw MissingTenantContextException::forModel($model::class);
                }

                $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null) {
                if ($context->id() === null) {
                    if (! $context->isBypassed()) {
                        throw MissingTenantContextException::forModel($model::class);
                    }

                    return;
                }

                $model->setAttribute('tenant_id', $context->id());
            } elseif (! $context->isBypassed() && $context->id() !== null && (int) $model->getAttribute('tenant_id') !== $context->id()) {
                throw MissingTenantContextException::forModel($model::class);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
