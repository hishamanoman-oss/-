<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Web;

use Illuminate\Contracts\View\View;

final class SystemStatusController
{
    public function __invoke(): View
    {
        return view('system-status');
    }
}
