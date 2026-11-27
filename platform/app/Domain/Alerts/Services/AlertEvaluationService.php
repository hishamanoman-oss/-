<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Services;

use App\Domain\Alerts\Mail\AlertRuleTriggeredMail;
use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Analytics\Services\AnalyticsPeriod;
use App\Domain\Analytics\Services\InventoryIntelligenceService;
use App\Domain\Analytics\Services\SalesAnalyticsService;
use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Synchronization\Models\SyncRun;
use App\Domain\Organizations\Models\UserBranchAccess;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Mail;

final class AlertEvaluationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryIntelligenceService $inventoryIntelligence,
        private readonly SalesAnalyticsService $salesAnalytics,
    ) {}

    /**
     * Evaluate all active notification rules for the specified tenant.
     *
     * @return array{rules_evaluated: int, notifications_created: int, emails_dispatched: int}
     */
    public function evaluateTenantRules(?int $tenantId = null): array
    {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        if ($resolvedTenantId === null) {
            return ['rules_evaluated' => 0, 'notifications_created' => 0, 'emails_dispatched' => 0];
        }

        $tenant = $this->tenantContext->runWithoutTenantScope(
            fn (): ?Tenant => Tenant::query()->find($resolvedTenantId)
        );
        if ($tenant === null) {
            return ['rules_evaluated' => 0, 'notifications_created' => 0, 'emails_dispatched' => 0];
        }

        $rules = NotificationRule::query()
            ->where('tenant_id', $resolvedTenantId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $createdCount = 0;
        $emailsDispatched = 0;

        foreach ($rules as $rule) {
            $trigger = $this->evaluateSingleRule($rule, $resolvedTenantId);
            if ($trigger === null) {
                continue;
            }

            $recipients = $this->resolveRecipients($resolvedTenantId, $rule);
            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($recipients as $membership) {
                $user = $membership->user;
                if ($user === null || ! $user->is_active) {
                    continue;
                }

                // Prevent duplicate unread notification spam for the same rule condition & user
                $alreadyExists = PlatformNotification::query()
                    ->where('tenant_id', $resolvedTenantId)
                    ->where('user_id', $user->id)
                    ->where('dedup_key', $trigger['dedup_key'])
                    ->whereNull('read_at')
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                if ($rule->channel_in_app) {
                    PlatformNotification::query()->create([
                        'tenant_id' => $resolvedTenantId,
                        'user_id' => $user->id,
                        'branch_id' => $rule->branch_id,
                        'notification_rule_id' => $rule->id,
                        'severity' => $trigger['severity'],
                        'title' => $trigger['title'],
                        'body' => $trigger['body'],
                        'action_url' => $trigger['action_url'],
                        'metadata_json' => $trigger['metadata'],
                        'dedup_key' => $trigger['dedup_key'],
                    ]);
                    $createdCount++;
                }

                if ($rule->channel_email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($user->email)->send(new AlertRuleTriggeredMail(
                        alertTitle: $trigger['title'],
                        alertBody: $trigger['body'],
                        severity: $trigger['severity'],
                        tenantName: $tenant->name,
                        actionUrl: $trigger['action_url'],
                    ));
                    $emailsDispatched++;
                }
            }
        }

        return [
            'rules_evaluated' => $rules->count(),
            'notifications_created' => $createdCount,
            'emails_dispatched' => $emailsDispatched,
        ];
    }

    /**
     * @return array{
     *     severity: string,
     *     title: string,
     *     body: string,
     *     action_url: string,
     *     dedup_key: string,
     *     metadata: array<string, mixed>
     * }|null
     */
    private function evaluateSingleRule(NotificationRule $rule, int $tenantId): ?array
    {
        $threshold = is_array($rule->threshold_json) ? $rule->threshold_json : [];

        return match ($rule->rule_type) {
            'low_stock' => $this->checkLowStock($rule, $tenantId, $threshold),
            'sales_drop_anomaly' => $this->checkSalesDrop($rule, $tenantId, $threshold),
            'cancellation_spike' => $this->checkCancellationSpike($rule, $tenantId, $threshold),
            'sync_failure' => $this->checkSyncFailure($rule, $tenantId),
            'command_approval_needed' => $this->checkPendingCommands($rule, $tenantId),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $threshold
     */
    private function checkLowStock(NotificationRule $rule, int $tenantId, array $threshold): ?array
    {
        $minItems = max(1, (int) ($threshold['min_items'] ?? 1));
        $intel = $this->inventoryIntelligence->analyze(
            tenantId: $tenantId,
            branchId: $rule->branch_id ? (int) $rule->branch_id : null,
        );

        $affected = $intel['low_stock_count'] + $intel['out_of_stock_count'];
        if ($affected < $minItems) {
            return null;
        }

        $firstSku = $intel['low_stock_items'][0]['sku'] ?? 'SKU';

        return [
            'severity' => $intel['out_of_stock_count'] > 0 ? 'critical' : 'warning',
            'title' => __('ui.alert_low_stock_title', ['count' => $affected]),
            'body' => __('ui.alert_low_stock_body', [
                'low' => $intel['low_stock_count'],
                'out' => $intel['out_of_stock_count'],
                'sku' => $firstSku,
            ]),
            'action_url' => '/inventory',
            'dedup_key' => "rule:{$rule->id}:low_stock:{$affected}:".now()->toDateString(),
            'metadata' => [
                'low_stock_count' => $intel['low_stock_count'],
                'out_of_stock_count' => $intel['out_of_stock_count'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $threshold
     */
    private function checkSalesDrop(NotificationRule $rule, int $tenantId, array $threshold): ?array
    {
        $dropPercentThreshold = max(1, (float) ($threshold['drop_percent'] ?? 20));
        $periodKey = (string) ($threshold['period'] ?? '7d');
        $window = AnalyticsPeriod::resolve($periodKey);

        $sales = $this->salesAnalytics->analyze(
            tenantId: $tenantId,
            branchId: $rule->branch_id ? (int) $rule->branch_id : null,
            period: $window,
        );

        if ($sales['delta_percent'] === null || DecimalMath::lte($sales['previous_net_sales'], '0')) {
            return null;
        }

        $dropLimit = '-'.number_format($dropPercentThreshold, 4, '.', '');
        if (DecimalMath::gt($sales['delta_percent'], $dropLimit)) {
            return null;
        }

        return [
            'severity' => 'critical',
            'title' => __('ui.alert_sales_drop_title'),
            'body' => __('ui.alert_sales_drop_body', [
                'percent' => number_format((float) $sales['delta_percent'], 1),
                'net' => number_format((float) $sales['net_sales'], 2),
            ]),
            'action_url' => '/dashboard',
            'dedup_key' => "rule:{$rule->id}:sales_drop:".now()->toDateString(),
            'metadata' => [
                'delta_percent' => $sales['delta_percent'],
                'net_sales' => $sales['net_sales'],
                'previous_net_sales' => $sales['previous_net_sales'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $threshold
     */
    private function checkCancellationSpike(NotificationRule $rule, int $tenantId, array $threshold): ?array
    {
        $minCancelled = max(1, (int) ($threshold['min_cancelled'] ?? 3));
        $window = AnalyticsPeriod::resolve((string) ($threshold['period'] ?? '7d'));

        $sales = $this->salesAnalytics->analyze(
            tenantId: $tenantId,
            branchId: $rule->branch_id ? (int) $rule->branch_id : null,
            period: $window,
        );

        if ($sales['cancelled_orders_count'] < $minCancelled) {
            return null;
        }

        return [
            'severity' => 'warning',
            'title' => __('ui.alert_cancellation_title', ['count' => $sales['cancelled_orders_count']]),
            'body' => __('ui.alert_cancellation_body', ['count' => $sales['cancelled_orders_count']]),
            'action_url' => '/orders',
            'dedup_key' => "rule:{$rule->id}:cancellations:{$sales['cancelled_orders_count']}:".now()->toDateString(),
            'metadata' => ['cancelled_orders_count' => $sales['cancelled_orders_count']],
        ];
    }

    private function checkSyncFailure(NotificationRule $rule, int $tenantId): ?array
    {
        $erroredSources = DataSource::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('status', 'error')->orWhere('freshness_status', 'error'))
            ->get();

        $failedRunsCount = SyncRun::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'failed')
            ->where('started_at', '>=', now()->subHours(24))
            ->count();

        if ($erroredSources->isEmpty() && $failedRunsCount === 0) {
            return null;
        }

        $sourceName = $erroredSources->first()?->name ?? 'Data Source';

        return [
            'severity' => 'critical',
            'title' => __('ui.alert_sync_failure_title'),
            'body' => __('ui.alert_sync_failure_body', [
                'source' => $sourceName,
                'runs' => $failedRunsCount,
            ]),
            'action_url' => '/integrations',
            'dedup_key' => "rule:{$rule->id}:sync_failure:{$erroredSources->count()}:{$failedRunsCount}:".now()->toDateString(),
            'metadata' => [
                'errored_sources' => $erroredSources->pluck('id')->all(),
                'failed_runs_24h' => $failedRunsCount,
            ],
        ];
    }

    private function checkPendingCommands(NotificationRule $rule, int $tenantId): ?array
    {
        $pendingQuery = RemoteCommand::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->when($rule->branch_id !== null, fn ($q) => $q->where('branch_id', $rule->branch_id));

        $count = $pendingQuery->count();
        if ($count === 0) {
            return null;
        }

        return [
            'severity' => 'warning',
            'title' => __('ui.alert_command_approval_title', ['count' => $count]),
            'body' => __('ui.alert_command_approval_body', ['count' => $count]),
            'action_url' => '/commands',
            'dedup_key' => "rule:{$rule->id}:pending_cmd:{$count}:".now()->toDateString(),
            'metadata' => ['pending_commands_count' => $count],
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TenantMembership>
     */
    private function resolveRecipients(int $tenantId, NotificationRule $rule)
    {
        $targetRoles = is_array($rule->recipient_roles_json) && $rule->recipient_roles_json !== []
            ? $rule->recipient_roles_json
            : ['tenant_owner', 'general_manager'];

        $memberships = $this->tenantContext->runWithoutTenantScope(fn () => TenantMembership::query()
            ->with(['user', 'roles'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', $targetRoles))
            ->get()
        );

        if ($rule->branch_id === null) {
            return $memberships;
        }

        return $memberships->filter(function (TenantMembership $membership) use ($rule): bool {
            $roles = $membership->roles->pluck('slug')->all();
            if (in_array('tenant_owner', $roles, true) || in_array('general_manager', $roles, true)) {
                return true;
            }

            return UserBranchAccess::query()
                ->where('tenant_membership_id', $membership->id)
                ->where('branch_id', (int) $rule->branch_id)
                ->exists();
        })->values();
    }
}
