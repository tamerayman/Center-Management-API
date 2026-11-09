<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\StudentResult;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isStudent()) {
            return $this->studentDashboard($user);
        }

        if ($user->isTeacher()) {
            return $this->teacherDashboard($user);
        }

        if ($user->isAdmin()) {
            return $this->adminDashboard($user);
        }

        return response()->json([
            'message' => 'Dashboard not available for this role.',
        ], 403);
    }

    private function studentDashboard($user)
    {
        $totalScore = $user->results()->sum('score');
        $totalMarks = $user->results()->sum('total_marks');

        $attendanceTotal = $user->attendances()->count();
        // نفس المعادلة المركزية — الشاشة دي معتادة تعرض 0 بدل null لو مفيش سجلات
        $attendanceRate = app(ProgressService::class)
            ->attendancePercentageFor($user) ?? 0;

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

        $upcomingExams = Exam::whereIn('course_id', $user->enrolledCourses()->pluck('courses.id'))
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->take(5)
            ->get();

        $recentResults = $user->results()->with('exam')->latest('submitted_at')->take(5)->get();

        $upcomingLessons = Lesson::query()
            ->with('course')
            ->upcoming()
            ->whereHas('course.students', fn ($q) => $q->where('student_id', $user->id))
            ->orderBy('lesson_date')
            ->take(5)
            ->get();

        $progressService = app(ProgressService::class);
        $progress = $progressService->forStudent($user);

        return new DashboardResource([
            'user' => $user,
            'statistics' => $statistics,
            'courses' => $courses,
            'upcoming_exams' => $upcomingExams,
            'recent_results' => $recentResults,
            'upcoming_lessons' => $upcomingLessons,
            'progress' => $progress,
            'progress_per_course' => $progressService->perCourse($user),
        ]);
    }

    private function teacherDashboard($user)
    {
        $courseIds = $user->courses()->pluck('id');

        $recentMaterials = LessonMaterial::whereHas('lesson', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with('lesson.course')
            ->latest()
            ->take(5)
            ->get();

        $statistics = [
            'courses_count' => $user->courses()->count(),
            'students_count' => $user->courses()->withCount('students')->get()->sum('students_count'),
            'lessons_count' => $user->courses()->withCount('lessons')->get()->sum('lessons_count'),
            'materials_count' => LessonMaterial::whereHas('lesson', fn ($q) => $q->whereIn('course_id', $courseIds))->count(),
            'exams_count' => $user->courses()->withCount('exams')->get()->sum('exams_count'),
        ];

        $courses = $user->courses()->withCount(['students', 'lessons', 'exams'])->get();

        $upcomingExams = Exam::whereIn('course_id', $courseIds)
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->take(5)
            ->get();

        $upcomingLessons = Lesson::query()
            ->with('course')
            ->upcoming()
            ->whereIn('course_id', $courseIds)
            ->orderBy('lesson_date')
            ->take(5)
            ->get();

        // لكل كورس: متوسط الحضور + متوسط أداء الامتحانات + الطلاب المحتاجين متابعة (< 50%)
        $progressService = app(ProgressService::class);

        $courseInsights = $user->courses()->with(['students', 'lessons.attendances'])->get()
            ->map(function (Course $course) use ($progressService) {
                $attendanceScores = [];
                $examScores = [];
                $attention = [];

                foreach ($course->students as $student) {
                    $stats = $progressService->forStudent($student, $course->id);

                    if ($stats['attendance_percentage'] !== null) {
                        $attendanceScores[] = $stats['attendance_percentage'];
                    }

                    if ($stats['exams']['average'] !== null) {
                        $examScores[] = $stats['exams']['average'];
                    }

                    $needsAttention = ($stats['attendance_percentage'] !== null && $stats['attendance_percentage'] < 50)
                        || ($stats['exams']['average'] !== null && $stats['exams']['average'] < 50);

                    if ($needsAttention) {
                        $attention[] = [
                            'id'                    => $student->id,
                            'name'                  => $student->name,
                            'attendance_percentage' => $stats['attendance_percentage'],
                            'exams_average'         => $stats['exams']['average'],
                        ];
                    }
                }

                return [
                    'course' => ['id' => $course->id, 'title' => $course->title],
                    'students_count' => $course->students->count(),
                    'average_attendance' => $attendanceScores !== []
                        ? round(array_sum($attendanceScores) / count($attendanceScores), 2)
                        : null,
                    'average_exam_performance' => $examScores !== []
                        ? round(array_sum($examScores) / count($examScores), 2)
                        : null,
                    'students_needing_attention' => $attention,
                ];
            });

        return new DashboardResource([
            'user' => $user,
            'statistics' => $statistics,
            'courses' => $courses,
            'upcoming_exams' => $upcomingExams,
            'recent_materials' => $recentMaterials,
            'upcoming_lessons' => $upcomingLessons,
            'course_insights' => $courseInsights,
        ]);
    }

    private function adminDashboard($user)
    {
        $statistics = [
            'teachers_count' => User::where('role', 'teacher')->count(),
            'students_count' => User::where('role', 'student')->count(),
            'courses_count' => Course::count(),
            'lessons_count' => Lesson::count(),
            'exams_count' => Exam::count(),
            'materials_count' => LessonMaterial::count(),
            'results_count' => StudentResult::count(),
        ];

        $courses = Course::with('teacher')->withCount(['students', 'lessons', 'exams'])
            ->latest()
            ->take(5)
            ->get();

        $upcomingExams = Exam::with('course')
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->take(5)
            ->get();

        return new DashboardResource([
            'user' => $user,
            'statistics' => $statistics,
            'courses' => $courses,
            'upcoming_exams' => $upcomingExams,
        ]);
    }
}
