<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Analytics\Models\PerformanceTarget;
use App\Domain\Analytics\Services\PerformanceTargetService;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PerformanceTargetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_evaluates_target_attainment_using_exact_bcmath_and_respects_tenant_scope(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();

        $ctx->bind($tenantA);
        // Remove seeded default target to isolate exact math assertion
        PerformanceTarget::query()->delete();

        // Create an orders_count target of 4 where Tenant A has 3 seeded active orders => 75.0000% (on_track)
        PerformanceTarget::query()->create([
            'branch_id' => null,
            'metric_type' => 'orders_count',
            'period_type' => 'monthly',
            'period_start' => now()->subDays(5)->toDateString(),
            'period_end' => now()->addDays(5)->toDateString(),
            'target_value' => '4.0000',
            'currency_code' => 'SAR',
            'created_by_user_id' => $owner->id,
        ]);

        /** @var PerformanceTargetService $service */
        $service = app(PerformanceTargetService::class);
        $evaluated = $service->evaluateTargets($tenantA->id);

        $this->assertCount(1, $evaluated);
        $this->assertSame('3.0000', $evaluated[0]['actual_value']);
        $this->assertSame('4.0000', $evaluated[0]['target_value']);
        $this->assertSame('75.0000', $evaluated[0]['attainment_percent']);
        $this->assertSame('on_track', $evaluated[0]['status']);

        // Tenant B sees zero Tenant A targets
        $ctx->bind($tenantB);
        $this->assertCount(0, $service->evaluateTargets($tenantB->id));
        $ctx->clear();
    }
}
