<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Web;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class OrderInvoiceController
{
    public function __invoke(int $order, TenantContext $tenantContext): View
    {
        $orderModel = Order::query()->findOrFail($order);
        Gate::authorize('view', $orderModel);

        $orderModel->load([
            'branch',
            'warehouse',
            'customer',
            'items',
            'payments',
            'refunds',
        ]);

        $externalRef = ExternalMapping::query()
            ->where('canonical_entity', 'order')
            ->where('internal_id', $orderModel->id)
            ->value('external_id');

        return view('orders.invoice', [
            'order' => $orderModel,
            'tenant' => $tenantContext->tenant(),
            'externalRef' => $externalRef,
            'decimalHelper' => DecimalMath::class,
        ]);
    }
}
