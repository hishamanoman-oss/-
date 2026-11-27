<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Alerts\Mail\AlertRuleTriggeredMail;
use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Alerts\Services\AlertEvaluationService;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AlertEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_evaluates_low_stock_and_sync_failure_with_dedup_scoping_and_email(): void
    {
        Mail::fake();

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();

        $ctx->bind($tenantA);

        // Trigger low_stock rule: ensure an item has available <= reorder_point
        $warehouse = Warehouse::query()->firstOrFail();
        $product = Product::query()->create([
            'sku' => 'ALT-LOW-001',
            'name' => 'Alert Test SKU',
            'status' => 'active',
            'base_price' => '100.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '10.0000',
        ]);
        InventoryBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'on_hand' => '2.0000',
            'reserved' => '0.0000',
        ]);

        // Trigger sync_failure rule: mark DataSource as error & enable email on sync rule
        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $source->forceFill(['status' => 'error', 'freshness_status' => 'error'])->save();

        $syncRule = NotificationRule::query()->where('rule_type', 'sync_failure')->firstOrFail();
        $syncRule->forceFill([
            'channel_email' => true,
            'recipient_roles_json' => ['tenant_owner'],
        ])->save();

        /** @var AlertEvaluationService $evaluator */
        $evaluator = app(AlertEvaluationService::class);
        $firstRun = $evaluator->evaluateTenantRules($tenantA->id);

        $this->assertGreaterThanOrEqual(2, $firstRun['notifications_created']);
        $this->assertGreaterThanOrEqual(1, $firstRun['emails_dispatched']);

        Mail::assertSent(AlertRuleTriggeredMail::class, fn (AlertRuleTriggeredMail $mail): bool => $mail->hasTo($owner->email));
        Mail::assertNotSent(AlertRuleTriggeredMail::class, fn (AlertRuleTriggeredMail $mail): bool => $mail->hasTo($observer->email));

        // Second evaluation MUST respect dedup_key and create ZERO duplicate unread alerts
        $secondRun = $evaluator->evaluateTenantRules($tenantA->id);
        $this->assertSame(0, $secondRun['notifications_created']);

        // Observer was NOT in recipient_roles_json so observer received 0 notifications
        $this->assertSame(0, PlatformNotification::query()->where('user_id', $observer->id)->count());

        // Cross-tenant isolation: Tenant B has zero Tenant A notifications
        $ctx->bind($tenantB);
        $this->assertSame(0, PlatformNotification::query()->count());
        $ctx->clear();
    }
}
