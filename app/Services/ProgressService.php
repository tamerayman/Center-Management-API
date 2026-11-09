<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Student Progress — محسوب من البيانات الموجودة (حضور + نتايج) بدون أي جدول progress.
 *
 * المعادلة (موثقة وشفافة):
 *   attendance_percentage = weighted(present=1, late=0.5, absent=0) / total × 100
 *   exams_average         = avg(score / total_marks × 100) على نتايج الطالب
 *   overall_progress      = متوسط المكوّنات المتاحة
 *                           (لو مفيش حضور → exams لوحده، ولو مفيش نتايج → attendance لوحده،
 *                            ولو مفيش الاتنين → null بدل رقم مضلل)
 */
class ProgressService
{
    /**
     * تقدم طالب — كلي أو لكورس معين.
     */
    public function forStudent(User $student, ?int $courseId = null): array
    {
        $attendanceQuery = $student->attendances();
        $resultsQuery = $student->results()->with('exam');

        if ($courseId) {
            $attendanceQuery->whereHas('lesson', fn ($q) => $q->where('course_id', $courseId));
            $resultsQuery->whereHas('exam', fn ($q) => $q->where('course_id', $courseId));
        }

        $attendancePercentage = $this->attendancePercentage($attendanceQuery);
        $exams = $this->examsStats($resultsQuery);

        return [
            'attendance_percentage' => $attendancePercentage,
            'exams' => $exams,
            'overall_progress' => $this->overallProgress($attendancePercentage, $exams['average']),
        ];
    }

    /**
     * تقدم الطالب في كل كورس مسجل فيه — للـ dashboards.
     */
    public function perCourse(User $student): array
    {
        return $student->enrolledCourses()->get()->map(function (Course $course) use ($student) {
            $stats = $this->forStudent($student, $course->id);

            return [
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                ],
                'attendance_percentage' => $stats['attendance_percentage'],
                'exams_average' => $stats['exams']['average'],
                'overall_progress' => $stats['overall_progress'],
            ];
        })->all();
    }

    /**
     * المصدر الوحيد لحساب نسبة الحضور — كل الشاشات بتستخدمه.
     *
     * القاعدة الثابتة: present = 1، late = 0.5، absent = 0
     * percentage = (النقاط المرجحة / عدد السجلات) × 100
     * بدون سجلات → null (بدل صفر مضلل)
     *
     * @param  HasMany|Builder  $query
     */
    public function attendancePercentageFrom($query): ?float
    {
        $total = (int) (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        $score = (float) ((clone $query)
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 WHEN status = 'late' THEN 0.5 ELSE 0 END) as s")
            ->value('s')) ?? 0;

        return round(($score / $total) * 100, 2);
    }

    /**
     * نسبة حضور طالب — كلي أو لكورس معين.
     */
    public function attendancePercentageFor(User $student, ?int $courseId = null): ?float
    {
        $query = $student->attendances();

        if ($courseId) {
            $query->whereHas('lesson', fn ($q) => $q->where('course_id', $courseId));
        }

        return $this->attendancePercentageFrom($query);
    }

    private function attendancePercentage($query): ?float
    {
        return $this->attendancePercentageFrom($query);
    }

    private function examsStats($query): array
    {
        $results = $query->get();

        if ($results->isEmpty()) {
            return ['count' => 0, 'average' => null, 'passed' => 0, 'failed' => 0];
        }

        $percentages = $results
            ->filter(fn ($result) => (float) $result->total_marks > 0)
            ->map(fn ($result) => ((float) $result->score / (float) $result->total_marks) * 100);

        return [
            'count' => $results->count(),
            'average' => $percentages->isNotEmpty()
                ? round($percentages->avg(), 2)
                : null,
            'passed' => $results->where('status', 'passed')->count(),
            'failed' => $results->where('status', 'failed')->count(),
        ];
    }

    private function overallProgress(?float $attendancePercentage, ?float $examsAverage): ?float
    {
        $components = array_filter([$attendancePercentage, $examsAverage], fn ($v) => $v !== null);

        if ($components === []) {
            return null;
        }

        return round(array_sum($components) / count($components), 2);
    }
}
