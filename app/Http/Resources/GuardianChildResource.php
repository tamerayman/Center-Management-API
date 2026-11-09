<?php

namespace App\Http\Resources;

use App\Models\Exam;
use App\Models\Lesson;
use App\Services\Finance\FinancialReportService;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianChildResource extends JsonResource
{
    /**
     * ولي الأمر يقرأ فقط — الملف الكامل لطفل واحد.
     */
    public function toArray(Request $request): array
    {
        $student = $this->resource;

        $attendanceTotal = $student->attendances()->count();
        // نفس الحساب المركزي — الشاشة دي معتادة تعرض 0 بدل null لو مفيش سجلات
        $attendanceRate = app(ProgressService::class)
            ->attendancePercentageFor($student) ?? 0;

        $enrolledCourses = $student->enrolledCourses()->with('teacher')->get();

        $upcomingExams = Exam::whereIn(
            'course_id',
            $student->enrolledCourses()->pluck('courses.id')
        )
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->take(5)
            ->get();

        $recentResults = $student->results()->with('exam')->latest('submitted_at')->take(10)->get();

        // الوضع المالي معاد استخدامه من نفس خدمات النظام المالي — لا منطق مكرر
        $finance = app(FinancialReportService::class)->studentFinancialProfile($student);
        unset($finance['payment_history']); // ليها endpoint منفصل paginated

        $recentAttendance = $student->attendances()->with('lesson')->latest()->take(10)->get();

        $progressService = app(ProgressService::class);
        $progress = $progressService->forStudent($student);

        $upcomingLessons = Lesson::query()
            ->with('course')
            ->upcoming()
            ->whereHas('course.students', fn ($q) => $q->where('student_id', $student->id))
            ->orderBy('lesson_date')
            ->take(5)
            ->get();

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'relationship' => $student->guardians->firstWhere('id', $request->user('guardian')->id)?->pivot?->relationship,
            'courses' => $enrolledCourses->map(fn ($course) => [
                'id' => $course->id,
                'title' => $course->title,
                'teacher' => $course->teacher?->name,
            ]),
            'attendance' => [
                'rate' => $attendanceRate,
                'total_records' => $attendanceTotal,
                'recent' => $recentAttendance->map(fn ($attendance) => [
                    'lesson' => $attendance->lesson?->title,
                    'lesson_date' => $attendance->lesson?->lesson_date,
                    'status' => $attendance->status,
                ]),
            ],
            'upcoming_exams' => $upcomingExams->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'course' => $exam->course->title,
                'start_time' => $exam->start_time,
            ]),
            'upcoming_lessons' => $upcomingLessons->map(fn ($lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'course' => $lesson->course->title,
                'lesson_date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
                'delivery_type' => $lesson->delivery_type,
            ]),
            'progress' => [
                'attendance_percentage' => $progress['attendance_percentage'],
                'exams_average' => $progress['exams']['average'],
                'overall_progress' => $progress['overall_progress'],
            ],
            'results' => $recentResults->map(fn ($result) => [
                'exam' => $result->exam?->title,
                'score' => $result->score,
                'total_marks' => $result->total_marks,
                'status' => $result->status,
                'submitted_at' => $result->submitted_at,
            ]),
            'finance' => $finance,
        ];
    }
}
