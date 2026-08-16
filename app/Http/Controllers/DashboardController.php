<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\Exam;
use App\Models\LessonMaterial;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'student') {
            return $this->studentDashboard($user);
        }

        if ($user->role === 'teacher') {
            return $this->teacherDashboard($user);
        }

        return response()->json([
            'message' => 'Dashboard not available for this role.'
        ], 403);
    }

    private function studentDashboard($user)
    {
        $totalScore = $user->results()->sum('score');
        $totalMarks = $user->results()->sum('total_marks');

        $attendanceTotal = $user->attendances()->count();
        $attendanceScore = $user->attendances()
            ->selectRaw("
        SUM(
            CASE
                WHEN status = 'present' THEN 1
                WHEN status = 'late' THEN 0.5
                ELSE 0
            END
        ) as attendance_score
    ")
            ->value('attendance_score') ?? 0;

        $attendanceRate = $attendanceTotal > 0
            ? round(($attendanceScore / $attendanceTotal) * 100, 2)
            : 0;
        $statistics = [
            'courses_count' => $user->enrolledCourses()->count(),

            'completed_exams' => $user->results()->count(),

            'total_score' => $totalScore,

            'total_marks' => $totalMarks,

            'overall_percentage' => $totalMarks > 0
                ? round(($totalScore / $totalMarks) * 100, 2)
                : 0,

            'attendance_rate' => $attendanceRate,
        ];

        $courses = $user->enrolledCourses()->with('teacher')->get();

        $upcomingExams = Exam::whereIn(
            'course_id',
            $user->enrolledCourses()->pluck('courses.id')
        )
            ->where('start_time', '>', now())
            ->orderBy('start_time')->take(5)->get();

        $recentResults = $user->results()->with('exam')->latest('submitted_at')->take(5)->get();




        return new DashboardResource([
            'user' => $user,
            'statistics' => $statistics,
            'courses' => $courses,
            'upcoming_exams' => $upcomingExams,
            'recent_results' => $recentResults,
        ]);
    }

    private function teacherDashboard($user)
    {
        $coursesQuery = $user->courses();

        $recentMaterials = LessonMaterial::whereHas('lesson.course', function ($query) use ($user) {
      $query->where('teacher_id', $user->id);
       })->with('lesson.course')->latest()->take(5)->get();

        $statistics = [
            'courses_count' => $coursesQuery->count(),

            'students_count' => $user->courses()->withCount('students')->get()->sum('students_count'),

            'lessons_count' => $user->courses()->withCount('lessons')->get()->sum('lessons_count'),

            'materials_count' => LessonMaterial::whereHas('lesson', function ($query) use ($user) {
                $query->whereIn('course_id', $user->courses()->pluck('id'));
            })->count(),

            'exams_count' => $user->courses()->withCount('exams')->get()->sum('exams_count'),

        ];

        $courses = $user->courses()->withCount(['students', 'lessons', 'exams'])->get();

        $upcomingExams = Exam::whereIn(
            'course_id',
            $user->courses()->pluck('id')
        )
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->take(5)
            ->get();

        return new DashboardResource([
            'user' => $user,
            'statistics' => $statistics,
            'courses' => $courses,
            'upcoming_exams' => $upcomingExams,
             'recent_materials' => $recentMaterials,
        ]);
    }
}
