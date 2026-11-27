<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Alerts\Services\AlertEvaluationService;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

final class AlertsCenterWorkspace extends Component
{
    use WithPagination;

    public string $activeTab = 'inbox';

    public string $inboxFilter = 'all';

    public string $severityFilter = 'all';

    // New Alert Rule form
    public string $ruleName = '';

    public string $ruleType = 'low_stock';

    public ?int $ruleBranchId = null;

    public int $thresholdValue = 1;

    public bool $channelInApp = true;

    public bool $channelEmail = false;

    public function updatingInboxFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSeverityFilter(): void
    {
        $this->resetPage();
    }

    public function markRead(int $notificationId): void
    {
        $notification = PlatformNotification::query()->findOrFail($notificationId);
        Gate::authorize('update', $notification);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
            $this->dispatch('notifications-refresh');
        }
    }

    public function markAllRead(): void
    {
        PlatformNotification::query()
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->dispatch('notifications-refresh');
        $this->dispatch('toast', message: __('ui.all_notifications_read'), type: 'success');
    }

    public function evaluateNow(AlertEvaluationService $evaluator, TenantContext $tenantContext): void
    {
        Gate::authorize('manage', NotificationRule::class);

        $summary = $evaluator->evaluateTenantRules($tenantContext->id());
        $this->dispatch('notifications-refresh');
        $this->dispatch('toast', message: __('ui.rules_evaluated_toast', ['count' => $summary['notifications_created']]), type: 'success');
    }

    public function toggleRule(int $ruleId): void
    {
        $rule = NotificationRule::query()->findOrFail($ruleId);
        Gate::authorize('update', $rule);

        $rule->forceFill(['is_active' => ! $rule->is_active])->save();
    }

    public function createRule(): void
    {
        Gate::authorize('manage', NotificationRule::class);

        $validated = $this->validate([
            'ruleName' => ['required', 'string', 'max:150'],
            'ruleType' => ['required', 'in:low_stock,sales_drop_anomaly,cancellation_spike,sync_failure,command_approval_needed'],
            'ruleBranchId' => ['nullable', 'integer'],
            'thresholdValue' => ['required', 'integer', 'min:1', 'max:1000'],
            'channelInApp' => ['boolean'],
            'channelEmail' => ['boolean'],
        ]);

        $thresholdJson = match ($validated['ruleType']) {
            'sales_drop_anomaly' => ['drop_percent' => $validated['thresholdValue'], 'period' => '7d'],
            'cancellation_spike' => ['min_cancelled' => $validated['thresholdValue'], 'period' => '7d'],
            default => ['min_items' => $validated['thresholdValue']],
        };

        NotificationRule::query()->create([
            'branch_id' => $validated['ruleBranchId'] ?: null,
            'name' => trim($validated['ruleName']),
            'rule_type' => $validated['ruleType'],
            'threshold_json' => $thresholdJson,
            'channel_in_app' => (bool) $validated['channelInApp'],
            'channel_email' => (bool) $validated['channelEmail'],
            'recipient_roles_json' => ['tenant_owner', 'general_manager'],
            'is_active' => true,
        ]);

        $this->ruleName = '';
        $this->thresholdValue = 1;
        $this->dispatch('toast', message: __('ui.rule_created_toast'), type: 'success');
    }

    #[On('notifications-refresh')]
    public function refreshFromEvent(): void
    {
        // Re-render inbox when live notifications update
    }

    public function render(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', PlatformNotification::class);

        $query = PlatformNotification::query()
            ->with('branch:id,name,code')
            ->where('user_id', Auth::id())
            ->latest('id');

        if ($this->inboxFilter === 'unread') {
            $query->whereNull('read_at');
        }

        if ($this->severityFilter !== 'all') {
            $query->where('severity', $this->severityFilter);
        }

        $canManageRules = Gate::allows('manage', NotificationRule::class);
        $rules = $canManageRules
            ? NotificationRule::query()->with('branch:id,name,code')->orderBy('id')->get()
            : collect();

        $branches = Branch::query()->orderBy('id')->get(['id', 'name', 'code']);

        return view('livewire.alerts-center-workspace', [
            'notifications' => $query->paginate(15),
            'rules' => $rules,
            'branches' => $branches,
            'canManageRules' => $canManageRules,
        ]);
    }
}
