<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Application\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(SystemHealthService $healthService): JsonResponse
    {
        $report = $healthService->collect();
        $httpStatus = $report->platformDb['status'] === 'ok' ? 200 : 503;

        return response()->json($report->toArray(), $httpStatus);
    }
}
