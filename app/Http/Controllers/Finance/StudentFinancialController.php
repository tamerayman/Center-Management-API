<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\FinancialReportService;
use Illuminate\Http\Request;

class StudentFinancialController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}

    /**
     * الملف المالي الكامل لطالب.
     */
    public function show(Request $request, User $student)
    {
        $this->authorize('finance.viewStudent', [$student]);

        $caller = $request->user();

        // المدرس يرى بيانات كورساته فقط — الأدمن/السكرتير يرون الكل
        $restrictToCourseIds = null;
        if ($caller->isTeacher()) {
            $restrictToCourseIds = $caller->courses()->pluck('id')->all();
        }

        return response()->json(
            $this->reports->studentFinancialProfile($student, $restrictToCourseIds)
        );
    }
}
