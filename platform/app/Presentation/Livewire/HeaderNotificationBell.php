<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

final class HeaderNotificationBell extends Component
{
    public bool $open = false;

    public function toggleDropdown(): void
    {
        $this->open = ! $this->open;
    }

    public function markRead(int $notificationId): void
    {
        $notification = PlatformNotification::query()
            ->where('user_id', Auth::id())
            ->findOrFail($notificationId);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    #[On('notifications-refresh')]
    #[On('command-realtime-refresh')]
    public function refreshBell(): void
    {
        // Trigger clean Livewire re-render
    }

    public function render(TenantContext $tenantContext): View
    {
        $userId = Auth::id();
        $tenantId = $tenantContext->id();

        $unreadCount = 0;
        $recent = collect();

        if ($userId !== null && $tenantId !== null) {
            $unreadCount = PlatformNotification::query()
                ->where('user_id', $userId)
                ->whereNull('read_at')
                ->count();

            $recent = PlatformNotification::query()
                ->where('user_id', $userId)
                ->latest('id')
                ->limit(5)
                ->get();
        }

        return view('livewire.header-notification-bell', [
            'unreadCount' => $unreadCount,
            'recent' => $recent,
        ]);
    }
}
