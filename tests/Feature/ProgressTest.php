<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\StudentResult;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();

        $this->course = Course::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->course->students()->attach($this->student->id);
    }

    /**
     * بيانات مثبتة الأرقام:
     * حضور: present(1) + late(0.5) + absent(0) = 1.5 / 2 = 75%
     * امتحانين: 80/100 + 90/100 → متوسط 85%
     * overall = (75 + 85) / 2 = 80
     */
    private function seedFixedData(): void
    {
        $lesson1 = Lesson::factory()->create(['course_id' => $this->course->id]);
        $lesson2 = Lesson::factory()->create(['course_id' => $this->course->id]);

        Attendance::create(['student_id' => $this->student->id, 'lesson_id' => $lesson1->id, 'status' => 'present']);
        Attendance::create(['student_id' => $this->student->id, 'lesson_id' => $lesson2->id, 'status' => 'late']);

        $exam1 = Exam::factory()->create(['course_id' => $this->course->id, 'total_marks' => 100]);
        $exam2 = Exam::factory()->create(['course_id' => $this->course->id, 'total_marks' => 100]);

        StudentResult::create([
            'student_id' => $this->student->id, 'exam_id' => $exam1->id,
            'score' => 80, 'total_marks' => 100, 'status' => 'passed', 'submitted_at' => now(),
        ]);
        StudentResult::create([
            'student_id' => $this->student->id, 'exam_id' => $exam2->id,
            'score' => 90, 'total_marks' => 100, 'status' => 'passed', 'submitted_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // الحسابات
    // ---------------------------------------------------------------

    public function test_progress_calculations_are_exact(): void
    {
        $this->seedFixedData();

        $response = $this->actingAs($this->student)->getJson('/api/progress');
        $response->assertOk();

        $overall = $response->json('overall');
        $this->assertEquals(75.0, $overall['attendance_percentage']);
        $this->assertEquals(2, $overall['exams']['count']);
        $this->assertEquals(85.0, $overall['exams']['average']);
        $this->assertEquals(2, $overall['exams']['passed']);
        $this->assertEquals(0, $overall['exams']['failed']);
        $this->assertEquals(80.0, $overall['overall_progress']);
    }

    public function test_progress_per_course_is_independent(): void
    {
        $this->seedFixedData();

        // كورس تاني بحضور 100% ومن غير نتايج
        $course2 = Course::factory()->create();
        $this->student->enrolledCourses()->attach($course2->id);
        $lesson = Lesson::factory()->create(['course_id' => $course2->id]);
        Attendance::create(['student_id' => $this->student->id, 'lesson_id' => $lesson->id, 'status' => 'present']);

        $response = $this->actingAs($this->student)->getJson('/api/progress');
        $response->assertOk();

        $perCourse = collect($response->json('per_course'));
        $first = $perCourse->firstWhere('course.id', $this->course->id);
        $second = $perCourse->firstWhere('course.id', $course2->id);

        $this->assertEquals(75.0, $first['attendance_percentage']);
        $this->assertEquals(85.0, $first['exams_average']);
        $this->assertEquals(80.0, $first['overall_progress']);

        // الكورس التاني: حضور 100% ومفيش امتحانات → overall = 100 (متوسط المكوّن المتاح)
        $this->assertEquals(100.0, $second['attendance_percentage']);
        $this->assertNull($second['exams_average']);
        $this->assertEquals(100.0, $second['overall_progress']);
    }

    public function test_progress_with_course_filter(): void
    {
        $this->seedFixedData();

        $response = $this->actingAs($this->student)
            ->getJson('/api/progress?course_id='.$this->course->id);

        $response->assertOk();
        $this->assertEquals(75.0, $response->json('overall.attendance_percentage'));
        $this->assertEquals(80.0, $response->json('overall.overall_progress'));
        $this->assertNull($response->json('per_course'));
    }

    // ---------------------------------------------------------------
    // الحالات الناقصة — بدون أرقام مضللة
    // ---------------------------------------------------------------

    public function test_progress_with_no_data_is_null_not_zero(): void
    {
        $response = $this->actingAs($this->student)->getJson('/api/progress');
        $response->assertOk();

        $overall = $response->json('overall');
        $this->assertNull($overall['attendance_percentage']);
        $this->assertNull($overall['exams']['average']);
        $this->assertNull($overall['overall_progress']);
    }

    public function test_progress_with_only_attendance_uses_it_alone(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);
        Attendance::create(['student_id' => $this->student->id, 'lesson_id' => $lesson->id, 'status' => 'present']);

        $response = $this->actingAs($this->student)->getJson('/api/progress');
        $response->assertOk();

        $this->assertEquals(100.0, $response->json('overall.attendance_percentage'));
        $this->assertNull($response->json('overall.exams.average'));
        $this->assertEquals(100.0, $response->json('overall.overall_progress'));
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    public function test_student_can_view_own_progress(): void
    {
        $this->seedFixedData();

        $this->actingAs($this->student)->getJson('/api/progress')->assertOk();
    }

    public function test_non_student_cannot_use_my_progress(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->getJson('/api/progress')->assertForbidden();
    }

    public function test_admin_and_secretary_can_view_any_student_progress(): void
    {
        $this->seedFixedData();

        $secretary = User::factory()->secretary()->create();

        $this->actingAs($this->admin)->getJson("/api/students/{$this->student->id}/progress")
            ->assertOk()
            ->assertJsonPath('overall.overall_progress', 80);

        $this->actingAs($secretary)->getJson("/api/students/{$this->student->id}/progress")
            ->assertOk();
    }

    public function test_teacher_can_view_progress_of_his_course_students_only(): void
    {
        $this->seedFixedData();

        // مدرس الكورس → مسموح
        $this->actingAs($this->teacher)->getJson("/api/students/{$this->student->id}/progress")
            ->assertOk()
            ->assertJsonPath('overall.overall_progress', 80);

        // مدرس غريب → 403
        $otherTeacher = User::factory()->teacher()->create();
        $this->actingAs($otherTeacher)->getJson("/api/students/{$this->student->id}/progress")
            ->assertForbidden();
    }

    public function test_student_cannot_view_another_students_progress(): void
    {
        $this->seedFixedData();
        $other = User::factory()->student()->create();

        $this->actingAs($other)->getJson("/api/students/{$this->student->id}/progress")
            ->assertForbidden();
    }

    public function test_guest_cannot_access_progress(): void
    {
        $this->getJson('/api/progress')->assertUnauthorized();
        $this->getJson('/api/students/1/progress')->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Guardian progress
    // ---------------------------------------------------------------

    public function test_guardian_can_view_linked_child_progress(): void
    {
        $this->seedFixedData();

        $token = $this->postJson('/api/guardian/register', [
            'name' => 'Progress Father',
            'phone' => '01012349876',
            'password' => 'secret1234',
            'guardian_code' => $this->student->guardian_code,
            'relationship' => 'father',
        ])->json('token');

        $this->withToken($token)->getJson("/api/guardian/children/{$this->student->id}/progress")
            ->assertOk()
            ->assertJsonPath('overall.overall_progress', 80)
            ->assertJsonPath('student.id', $this->student->id);
    }

    public function test_guardian_cannot_view_unrelated_child_progress(): void
    {
        $this->seedFixedData();

        $token = $this->postJson('/api/guardian/register', [
            'name' => 'Unrelated Father',
            'phone' => '01012340000',
            'password' => 'secret1234',
            'guardian_code' => $this->student->guardian_code,
            'relationship' => 'father',
        ])->json('token');

        $other = User::factory()->student()->create();

        $this->withToken($token)->getJson("/api/guardian/children/{$other->id}/progress")
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Teacher dashboard insights
    // ---------------------------------------------------------------

    public function test_teacher_dashboard_course_insights(): void
    {
        $this->seedFixedData();

        // طالب متعثر: حضور 0% → يحتاج متابعة
        $struggling = User::factory()->student()->create();
        $this->course->students()->attach($struggling->id);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);
        Attendance::create(['student_id' => $struggling->id, 'lesson_id' => $lesson->id, 'status' => 'absent']);

        $response = $this->actingAs($this->teacher)->getJson('/api/dashboard');
        $response->assertOk();

        $insight = collect($response->json('data.course_insights'))
            ->firstWhere('course.id', $this->course->id);

        $this->assertNotNull($insight);
        $this->assertEquals(2, $insight['students_count']);
        $this->assertEquals(37.5, $insight['average_attendance']); // (75 + 0) / 2

        $attentionIds = collect($insight['students_needing_attention'])->pluck('id');
        $this->assertContains($struggling->id, $attentionIds);
        $this->assertNotContains($this->student->id, $attentionIds);
    }

    public function test_student_dashboard_includes_progress_and_upcoming_lessons(): void
    {
        $this->seedFixedData();

        Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Next Lesson',
            'lesson_date' => now()->addDays(2)->format('Y-m-d 14:00:00'),
        ]);

        $response = $this->actingAs($this->student)->getJson('/api/dashboard');
        $response->assertOk();

        $this->assertEquals(80, $response->json('data.progress.overall_progress'));
        $this->assertEquals(75, $response->json('data.progress.attendance_percentage'));
        // الدروس من الـ seed ضمن القادمة — المهم إن 'Next Lesson' موجودة
        $titles = collect($response->json('data.upcoming_lessons'))->pluck('title');
        $this->assertContains('Next Lesson', $titles);
    }

    /**
     * أمثلة القاعدة الموثقة — عبر المعادلة المركزية في ProgressService:
     * 4 present / 4 = 100 — 3 present + 1 absent = 75 — 3 present + 1 late = 87.5
     */
    public function test_centralized_attendance_percentage_documented_examples(): void
    {
        $service = app(ProgressService::class);

        $makeLessonAttendance = function (array $statuses) {
            $student = User::factory()->student()->create();
            $course = Course::factory()->create();
            $course->students()->attach($student->id);

            foreach ($statuses as $status) {
                $lesson = Lesson::factory()->create(['course_id' => $course->id]);
                Attendance::create([
                    'student_id' => $student->id,
                    'lesson_id' => $lesson->id,
                    'status' => $status,
                ]);
            }

            return $student;
        };

        // 4 present / 4 = 100%
        $s1 = $makeLessonAttendance(['present', 'present', 'present', 'present']);
        $this->assertEquals(100.0, $service->attendancePercentageFor($s1));

        // 3 present + 1 absent = 75%
        $s2 = $makeLessonAttendance(['present', 'present', 'present', 'absent']);
        $this->assertEquals(75.0, $service->attendancePercentageFor($s2));

        // 3 present + 1 late = 87.5%
        $s3 = $makeLessonAttendance(['present', 'present', 'present', 'late']);
        $this->assertEquals(87.5, $service->attendancePercentageFor($s3));

        // نفس الأرقام تطلع من الـ endpoint البروفايل بتاع ولي الأمر (قراءة نفس المصدر)
    }

    public function test_centralized_calculation_matches_child_view_output(): void
    {
        // نطابق إن شاشة الـ Guardian بتعرض نفس رقم الخدمة المركزية
        $this->seedFixedData();

        $service = app(ProgressService::class);
        $centralValue = $service->attendancePercentageFor($this->student);

        $this->assertNotNull($centralValue);

        $token = $this->postJson('/api/guardian/register', [
            'name' => 'Father',
            'phone' => '01012345678',
            'password' => 'secret1234',
            'guardian_code' => $this->student->guardian_code,
            'relationship' => 'father',
        ])->json('token');

        $childView = $this->withToken($token)->getJson("/api/guardian/children/{$this->student->id}");
        $childView->assertOk();

        $this->assertEquals($centralValue, $childView->json('data.attendance.rate'));
    }
}
