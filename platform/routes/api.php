<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Api\V1\AgentController;
use App\Presentation\Http\Controllers\Api\V1\HealthController;
use App\Presentation\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::post('/agent/enroll', [AgentController::class, 'enroll'])->name('api.v1.agent.enroll');

    Route::middleware('auth.connector')->prefix('agent')->group(function (): void {
        Route::post('/heartbeat', [AgentController::class, 'heartbeat'])->name('api.v1.agent.heartbeat');
        Route::get('/config', [AgentController::class, 'config'])->name('api.v1.agent.config');
        Route::post('/sync/batches', [AgentController::class, 'ingestBatch'])->name('api.v1.agent.sync.batches');
        Route::get('/commands/pending', [AgentController::class, 'pendingCommands'])->name('api.v1.agent.commands.pending');
        Route::post('/commands/{command}/ack', [AgentController::class, 'ackCommand'])
            ->whereNumber('command')
            ->name('api.v1.agent.commands.ack');
    });

    Route::post('/webhooks/{dataSource}', WebhookController::class)->name('api.v1.webhooks.receive');
});
