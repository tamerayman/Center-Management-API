<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\StudentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherA;

    private User $teacherB;

    private User $student;

    private Course $courseA;

    private Course $courseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->teacherA = User::factory()->teacher()->create();
        $this->teacherB = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();

        $this->courseA = Course::factory()->create([
            'teacher_id' => $this->teacherA->id,
            'title' => 'PHP Backend Development',
        ]);
        $this->courseB = Course::factory()->create([
            'teacher_id' => $this->teacherB->id,
            'title' => 'Laravel Advanced',
        ]);
    }

    // ---------------------------------------------------------------
    // Students search (admin)
    // ---------------------------------------------------------------

    public function test_admin_can_search_students_by_partial_name(): void
    {
        User::factory()->student()->create(['name' => 'Tamer Ayman']);
        User::factory()->student()->create(['name' => 'Tamer Sobhy']);
        User::factory()->student()->create(['name' => 'Someone Else']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?role=student&search=Tamer');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_admin_can_search_students_by_phone(): void
    {
        $student = User::factory()->student()->create(['name' => 'Phone Guy']);
        Profile::where('user_id', $student->id)->update(['phone' => '01099998888']);

        // بحث جزئي بالموبايل
        $response = $this->actingAs($this->admin)->getJson('/api/admin/users?search=99998888');
        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Phone Guy', $response->json('data.0.name'));
    }

    public function test_student_search_with_no_matches_returns_empty(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users?role=student&search=nonexistent-name-xyz');

        $response->assertOk();
        $this->assertEquals(0, $response->json('meta.total'));
        $this->assertCount(0, $response->json('data'));
    }

    // ---------------------------------------------------------------
    // Course filters
    // ---------------------------------------------------------------

    public function test_courses_can_be_filtered_by_search(): void
    {
        $this->actingAs($this->admin)->getJson('/api/courses?search=php')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'PHP Backend Development');
    }

    public function test_courses_search_is_case_insensitive_partial(): void
    {
        $this->actingAs($this->admin)->getJson('/api/courses?search=LARAVEL')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_courses_can_be_filtered_by_teacher_id(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/courses?teacher_id={$this->teacherA->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($this->teacherA->id, $response->json('data.0.teacher.id'));
    }

    public function test_course_filters_can_be_combined(): void
    {
        // بحث مش مطابق + teacher_id → فاضي
        $this->actingAs($this->admin)
            ->getJson("/api/courses?search=php&teacher_id={$this->teacherB->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ---------------------------------------------------------------
    // Attendance listing + filters + scoping
    // ---------------------------------------------------------------

    private function makeAttendanceFixture(): array
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson1 = Lesson::factory()->create([
            'course_id' => $course->id,
            'lesson_date' => '2026-09-10 10:00:00',
        ]);
        $lesson2 = Lesson::factory()->create([
            'course_id' => $course->id,
            'lesson_date' => '2026-08-05 10:00:00',
        ]);

        $a1 = Attendance::create([
            'student_id' => $this->student->id, 'lesson_id' => $lesson1->id, 'status' => 'absent',
        ]);
        $a2 = Attendance::create([
            'student_id' => $this->student->id, 'lesson_id' => $lesson2->id, 'status' => 'present',
        ]);

        return [$teacher, $course, $a1, $a2];
    }

    public function test_attendance_listing_scoped_by_role(): void
    {
        [$teacher, $course, $a1, $a2] = $this->makeAttendanceFixture();

        // student → سجله فقط
        $response = $this->actingAs($this->student)->getJson('/api/attendance');
        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));

        // teacher → كورساته (الكورس ده بتاع teacher التاني في الفيكسشر ده؟ لا — teacher هو صاحبه)
        $response = $this->actingAs($teacher)->getJson('/api/attendance');
        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));

        // teacher غريب → فاضي (scoped)
        $otherTeacher = User::factory()->teacher()->create();
        $this->actingAs($otherTeacher)->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // admin → الكل
        $this->actingAs($this->admin)->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_attendance_filters(): void
    {
        [$teacher, $course, $a1, $a2] = $this->makeAttendanceFixture();

        // فلتر الحالة
        $this->actingAs($this->admin)->getJson('/api/attendance?status=absent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'absent');

        // فلتر التاريخ (lesson_date)
        $this->actingAs($this->admin)->getJson('/api/attendance?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'absent');

        // فلتر الطالب + الحالة معاً
        $this->actingAs($this->admin)->getJson("/api/attendance?student_id={$this->student->id}&status=present")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // فلتر كورس
        $this->actingAs($this->admin)->getJson("/api/attendance?course_id={$course->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ---------------------------------------------------------------
    // Payment filters
    // ---------------------------------------------------------------

    public function test_payment_filters_by_date_and_amount(): void
    {
        $course = Course::factory()->create();

        Payment::create([
            'student_id' => $this->student->id, 'course_id' => $course->id,
            'amount' => 500, 'allocated_amount' => 500,
            'paid_at' => '2026-09-05', 'method' => 'cash', 'created_by' => $this->admin->id,
        ]);
        Payment::create([
            'student_id' => $this->student->id, 'course_id' => $course->id,
            'amount' => 100, 'allocated_amount' => 100,
            'paid_at' => '2026-08-01', 'method' => 'card', 'created_by' => $this->admin->id,
        ]);

        // date range
        $this->actingAs($this->admin)->getJson('/api/finance/payments?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', '500.00');

        // amount range
        $this->actingAs($this->admin)->getJson('/api/finance/payments?amount_min=400')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->admin)->getJson('/api/finance/payments?amount_max=200')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', '100.00');

        // الجمع: method + amount
        $this->actingAs($this->admin)->getJson('/api/finance/payments?method=cash&amount_min=100')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---------------------------------------------------------------
    // Results — admin filters + scoped endpoint
    // ---------------------------------------------------------------

    private function makeResultFixture(): array
    {
        $course = Course::factory()->create();
        $exam = Exam::factory()->create(['course_id' => $course->id, 'total_marks' => 100]);

        $r1 = StudentResult::create([
            'student_id' => $this->student->id, 'exam_id' => $exam->id,
            'score' => 85, 'total_marks' => 100, 'status' => 'passed', 'submitted_at' => '2026-09-10 12:00:00',
        ]);
        $student2 = User::factory()->student()->create();
        $r2 = StudentResult::create([
            'student_id' => $student2->id, 'exam_id' => $exam->id,
            'score' => 40, 'total_marks' => 100, 'status' => 'failed', 'submitted_at' => '2026-09-11 12:00:00',
        ]);

        return [$course, $exam, $r1, $r2, $student2];
    }

    public function test_admin_results_filters(): void
    {
        [$course, $exam, $r1, $r2, $student2] = $this->makeResultFixture();

        $this->actingAs($this->admin)->getJson('/api/admin/results?course_id='.$course->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->admin)->getJson('/api/admin/results?status=failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score', 40);

        $this->actingAs($this->admin)->getJson('/api/admin/results?min_score=60')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score', 85);

        $this->actingAs($this->admin)->getJson('/api/admin/results?date_from=2026-09-11')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score', 40);
    }

    public function test_scoped_results_endpoint_by_role(): void
    {
        [$course, $exam, $r1, $r2, $student2] = $this->makeResultFixture();

        // الطالب → نتايجه فقط
        $mine = $this->actingAs($this->student)->getJson('/api/results');
        $mine->assertOk();
        $this->assertEquals(1, $mine->json('meta.total'));
        $this->assertEquals($this->student->id, $mine->json('data.0.student.id'));

        // طالب تاني مش شايف نتايج غيره
        $other = User::factory()->student()->create();
        $this->actingAs($other)->getJson('/api/results')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // teacher → نتايج كورساته (الكورس بتاع teacher غريب هنا — نعمل واحد بتاعه)
        $myTeacher = User::factory()->teacher()->create();
        $myCourse = Course::factory()->create(['teacher_id' => $myTeacher->id]);
        $myExam = Exam::factory()->create(['course_id' => $myCourse->id]);
        StudentResult::create([
            'student_id' => $this->student->id, 'exam_id' => $myExam->id,
            'score' => 70, 'total_marks' => 100, 'status' => 'passed', 'submitted_at' => now(),
        ]);

        $teacherView = $this->actingAs($myTeacher)->getJson('/api/results');
        $teacherView->assertOk();
        $this->assertEquals(1, $teacherView->json('meta.total'));

        // secretary → الكل
        $secretary = User::factory()->secretary()->create();
        $this->actingAs($secretary)->getJson('/api/results')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
