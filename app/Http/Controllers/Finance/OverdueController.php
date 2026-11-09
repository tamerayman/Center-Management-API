<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonthlyChargeResource;
use App\Services\Finance\FinancialReportService;
use Illuminate\Http\Request;

class OverdueController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}

    /**
     * مين عليه فلوس؟ — admin/secretary.
     */
    public function index(Request $request)
    {
        $this->authorize('finance.viewAny');

        $overdue = $this->reports->overdue($request->query());

        return MonthlyChargeResource::collection($overdue->paginate($this->perPage($request, 15)));
    }
}
