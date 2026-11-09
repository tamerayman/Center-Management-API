<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Services\GuardianNotifier;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * ملخص لكل طفل: أكاديمي + مالي + إشعارات غير مقروءة.
     */
    public function index(Request $request)
    {
        $guardian = $request->user('guardian');
        $progressService = app(ProgressService::class);

        $children = $guardian->children()->get()->map(function ($student) use ($guardian, $progressService) {
            $attendanceRate = $progressService->attendancePercentageFor($student) ?? 0;

            $latestResult = $student->results()->with('exam')->latest('submitted_at')->first();

            $due = (float) $student->charges()->sum('amount');
            $paid = (float) $student->charges()->sum('paid_amount');
            $overdueCharges = $student->charges()->outstanding()->count();

            return [
                'id' => $student->id,
                'name' => $student->name,
                'courses_count' => $student->enrolledCourses()->count(),
                'attendance_rate' => $attendanceRate,
                'latest_result' => $latestResult ? [
                    'exam' => $latestResult->exam?->title,
                    'score' => $latestResult->score,
                    'total_marks' => $latestResult->total_marks,
                    'status' => $latestResult->status,
                ] : null,
                'finance' => [
                    'total_due' => number_format($due, 2, '.', ''),
                    'total_paid' => number_format($paid, 2, '.', ''),
                    'remaining' => number_format(max(0, $due - $paid), 2, '.', ''),
                    'outstanding_charges' => $overdueCharges,
                ],
                'unread_notifications' => $guardian->notifications()
                    ->unread()->where('student_id', $student->id)->count(),
            ];
        });

        return response()->json([
            'data' => $children,
            'unread_notifications_total' => $guardian->notifications()->unread()->count(),
        ]);
    }
}
