<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Web;

use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Tenants\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class ReportDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $tenantContext,
        ReportExportService $exportService,
    ): Response {
        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', ReportExportService::SUPPORTED_TYPES)],
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'period' => ['nullable', 'in:today,7d,30d,this_month,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $tenantContext->id();
        if ($tenantId === null) {
            abort(403, 'Active tenant context is required.');
        }

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $requestedBranchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : $tenantContext->activeBranchId();

        if ($requestedBranchId !== null && ! $tenantContext->canAccessBranch($requestedBranchId)) {
            abort(403, 'You are not authorized to export reports for this branch.');
        }

        $payload = $exportService->build(
            reportType: $validated['type'],
            tenantId: $tenantId,
            allowedBranchIds: $accessibleBranchIds,
            branchId: $requestedBranchId,
            periodKey: $validated['period'] ?? '30d',
            customFrom: $validated['from'] ?? null,
            customTo: $validated['to'] ?? null,
        );

        $format = $validated['format'];
        $exportService->recordExportAudit(
            tenantId: $tenantId,
            actorUserId: Auth::id(),
            reportType: $validated['type'],
            format: $format,
            branchId: $requestedBranchId,
        );

        $slug = $validated['type'].'-'.now()->format('Ymd-His');

        if ($format === 'xlsx') {
            return response($exportService->toXlsxBinary($payload), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$slug}.xlsx\"",
                'Cache-Control' => 'no-store',
            ]);
        }

        if ($format === 'pdf') {
            return response($exportService->toPdfBinary($payload), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$slug}.pdf\"",
                'Cache-Control' => 'no-store',
            ]);
        }

        return response($exportService->toCsv($payload), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$slug}.csv\"",
            'Cache-Control' => 'no-store',
        ]);
    }
}
