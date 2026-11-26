<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Application\Services\SystemHealthService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class SystemStatus extends Component
{
    /** @var array<string, mixed> */
    public array $report = [];

    public function mount(SystemHealthService $healthService): void
    {
        $this->loadReport($healthService);
    }

    public function refreshStatus(SystemHealthService $healthService): void
    {
        $this->loadReport($healthService);
    }

    private function loadReport(SystemHealthService $healthService): void
    {
        $this->report = $healthService->collect()->toArray();
    }

    public function render(): View
    {
        return view('livewire.system-status');
    }
}
