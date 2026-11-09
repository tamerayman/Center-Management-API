<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialAuditLogResource;
use App\Models\FinancialAuditLog;
use App\Services\Finance\FinancialReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}

    /**
     * الـ Financial Dashboard لشهر معين — admin/secretary.
     */
    public function dashboard(Request $request)
    {
        $this->authorize('finance.viewAny');

        return response()->json($this->reports->dashboard(
            (int) ($request->query('year') ?? now()->year),
            (int) ($request->query('month') ?? now()->month),
            $request->filled('course_id') ? (int) $request->query('course_id') : null,
        ));
    }

    /**
     * تقرير الإيراد الشهري لسنة — admin/secretary.
     */
    public function monthly(Request $request)
    {
        $this->authorize('finance.viewAny');

        return response()->json($this->reports->monthlyRevenueReport(
            (int) ($request->query('year') ?? now()->year),
            $request->filled('course_id') ? (int) $request->query('course_id') : null,
        ));
    }

    /**
     * Collection rate لفترة — admin/secretary.
     */
    public function collection(Request $request)
    {
        $this->authorize('finance.viewAny');

        $summary = $this->reports->dashboard(
            (int) ($request->query('year') ?? now()->year),
            (int) ($request->query('month') ?? now()->month),
            $request->filled('course_id') ? (int) $request->query('course_id') : null,
        );

        return response()->json([
            'period' => $summary['period'],
            'expected' => $summary['expected'],
            'collected' => $summary['collected'],
            'remaining' => $summary['remaining'],
            'collection_rate' => $summary['collection_rate'],
        ]);
    }

    /**
     * سجل العمليات المالية (من عمل إيه وإمتى) — للأدمن فقط.
     */
    public function audit(Request $request)
    {
        $this->authorize('finance.sensitive');

        $logs = FinancialAuditLog::query()
            ->with('actor')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->query('actor_id')))
            ->latest()
            ->paginate($this->perPage($request, 20));

        return FinancialAuditLogResource::collection($logs);
    }
}
