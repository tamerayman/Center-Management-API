<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Finance\FinancialReportService;
use Illuminate\Http\Request;

class CourseFinancialController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}

    /**
     * إحصائيات مالية لكورس — المدرس لكورسه فقط.
     */
    public function show(Request $request, Course $course)
    {
        $this->authorize('finance.viewCourse', [$course]);

        return response()->json(
            $this->reports->courseStatistics(
                $course,
                $request->filled('year') ? (int) $request->query('year') : null,
                $request->filled('month') ? (int) $request->query('month') : null,
            )
        );
    }
}
