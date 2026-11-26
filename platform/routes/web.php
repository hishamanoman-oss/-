<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Web\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('system.status'));
Route::get('/system-status', SystemStatusController::class)->name('system.status');
