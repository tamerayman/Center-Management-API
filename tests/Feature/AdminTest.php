<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Notification;
use App\Models\Question;
use App\Models\StudentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    // ---------------------------------------------------------------
    // الحماية: مين يدخل ومين ميدخليش
    // ---------------------------------------------------------------

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        foreach ([$teacher, $student] as $user) {
            $this->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
            $this->actingAs($user)->postJson('/api/admin/users', [
                'name' => 'X', 'email' => 'x@x.com', 'password' => 'password123', 'role' => 'student',
            ])->assertForbidden();
            $this->actingAs($user)->postJson('/api/admin/notifications/broadcast', [
                'title' => 'hi', 'message' => 'msg',
            ])->assertForbidden();
        }
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        // بدون أي actingAs — مستخدم مجهول → 401
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->postJson('/api/admin/users', [
            'name' => 'X', 'email' => 'x@x.com', 'password' => 'password123', 'role' => 'student',
        ])->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // إدارة المستخدمين
    // ---------------------------------------------------------------

    public function test_admin_can_list_users_with_role_filter_search_and_pagination(): void
    {
        User::factory()->teacher()->count(12)->create();
        User::factory()->student()->count(5)->create();

        // فلتر الدور + pagination
        $teachers = $this->actingAs($this->admin)->getJson('/api/admin/users?role=teacher&per_page=10');
        $teachers->assertOk()->assertJsonCount(10, 'data');
        $this->assertEquals(12, $teachers->json('meta.total'));

        // كل النتايج فيها role
        foreach ($teachers->json('data') as $row) {
            $this->assertEquals('teacher', $row['role']);
        }

        // بحث بالاسم
        User::factory()->create(['name' => 'Mohamed Salah', 'email' => 'mo@salah.com']);
        $search = $this->actingAs($this->admin)->getJson('/api/admin/users?search=Salah');
        $search->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('mo@salah.com', $search->json('data.0.email'));
    }

    public function test_admin_can_create_user_with_any_role_including_admin(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/users', [
            'name' => 'New Admin',
            'email' => 'new.admin@example.com',
            'password' => 'secret1234',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin');

        $user = User::whereEmail('new.admin@example.com')->first();
        $this->assertNotNull($user);
        // الـ Profile بيتعمل أوتوماتيك بالـ Observer
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
    }

    public function test_admin_create_user_validates_role_and_unique_email(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/users', [
            'name' => 'Bad',
            'email' => $existing->email,
            'password' => 'secret1234',
            'role' => 'superman',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role']);
    }

    public function test_admin_can_update_user_name_email_and_role(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->admin)->putJson("/api/admin/users/{$student->id}", [
            'name' => 'Promoted Student',
            'role' => 'teacher',
        ])->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.name', 'Promoted Student');

        $this->assertEquals('teacher', $student->fresh()->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $this->actingAs($this->admin)->putJson("/api/admin/users/{$this->admin->id}", [
            'role' => 'student',
        ])->assertBadRequest();

        $this->assertEquals('admin', $this->admin->fresh()->role);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->actingAs($this->admin)->deleteJson("/api/admin/users/{$this->admin->id}")
            ->assertBadRequest();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_delete_student_and_their_data_is_cleaned(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        $course->students()->attach($student->id);

        $this->actingAs($this->admin)->deleteJson("/api/admin/users/{$student->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('course_student', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('profiles', ['user_id' => $student->id]);
    }

    public function test_deleting_teacher_deletes_his_courses_and_their_content(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);

        $this->actingAs($this->admin)->deleteJson("/api/admin/users/{$teacher->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    // ---------------------------------------------------------------
    // إدارة الكورسات والاشتراكات
    // ---------------------------------------------------------------

    public function test_admin_can_create_course_for_any_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/courses', [
            'title' => 'Admin Made Course',
            'description' => 'Assigned by admin',
            'teacher_id' => $teacher->id,
        ])->assertCreated()
            ->assertJsonPath('data.teacher_id', $teacher->id);

        $this->assertDatabaseHas('courses', [
            'title' => 'Admin Made Course',
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_admin_create_course_requires_a_valid_teacher_id(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/courses', [
            'title' => 'Bad Course',
            'teacher_id' => $student->id, // طالب مش مدرس
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_admin_can_enroll_and_remove_students_by_force(): void
    {
        $course = Course::factory()->create();
        $students = User::factory()->student()->count(2)->create();
        $teacher = User::factory()->teacher()->create();

        // تسجيل بالقوة
        $this->actingAs($this->admin)->postJson("/api/admin/courses/{$course->id}/students", [
            'student_ids' => $students->pluck('id')->all(),
        ])->assertOk();

        foreach ($students as $student) {
            $this->assertDatabaseHas('course_student', [
                'course_id' => $course->id,
                'student_id' => $student->id,
            ]);
        }

        // إذن مش طالب → مرفوض
        $this->actingAs($this->admin)->postJson("/api/admin/courses/{$course->id}/students", [
            'student_ids' => [$teacher->id],
        ])->assertUnprocessable();

        // إخراج طالب
        $this->actingAs($this->admin)->deleteJson("/api/admin/courses/{$course->id}/students/{$students->first()->id}")
            ->assertOk();

        $this->assertDatabaseMissing('course_student', [
            'course_id' => $course->id,
            'student_id' => $students->first()->id,
        ]);

        // طالب مش مسجل أصلاً → 404
        $this->actingAs($this->admin)->deleteJson("/api/admin/courses/{$course->id}/students/{$students->first()->id}")
            ->assertNotFound();
    }

    // ---------------------------------------------------------------
    // إدارة النتايج
    // ---------------------------------------------------------------

    public function test_admin_can_list_results_with_filters(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $course = Course::factory()->create();
        $exam1 = Exam::factory()->create(['course_id' => $course->id]);
        $exam2 = Exam::factory()->create(['course_id' => $course->id]);

        StudentResult::create([
            'student_id' => $student1->id, 'exam_id' => $exam1->id,
            'score' => 9, 'total_marks' => 10, 'status' => 'passed', 'submitted_at' => now(),
        ]);
        StudentResult::create([
            'student_id' => $student2->id, 'exam_id' => $exam2->id,
            'score' => 3, 'total_marks' => 10, 'status' => 'failed', 'submitted_at' => now(),
        ]);

        $all = $this->actingAs($this->admin)->getJson('/api/admin/results');
        $all->assertOk()->assertJsonCount(2, 'data');

        $failed = $this->actingAs($this->admin)->getJson('/api/admin/results?status=failed');
        $failed->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($student2->id, $failed->json('data.0.student.id'));

        $byExam = $this->actingAs($this->admin)->getJson("/api/admin/results?exam_id={$exam1->id}");
        $byExam->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_delete_result_so_student_can_retake_exam(): void
    {
        // فلو كامل: طالب يسلم امتحان، الأدمن يمسح النتيجة، والطالب يعيد التسليم
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $course->students()->attach($student->id);

        $exam = Exam::factory()->open()->create([
            'course_id' => $course->id,
            'duration' => 60,
            'total_marks' => 10,
            'pass_mark' => 50,
        ]);
        $question = Question::factory()->create([
            'exam_id' => $exam->id, 'marks' => 10, 'correct_answer' => 'a',
        ]);

        $this->actingAs($student)->postJson("/api/exams/{$exam->id}/start");
        $this->actingAs($student)->postJson("/api/questions/{$question->id}/answer", [
            'student_answer' => 'b', // غلط → فشل
        ]);
        $this->actingAs($student)->postJson("/api/exams/{$exam->id}/submit")->assertOk();

        $this->assertDatabaseHas('student_results', [
            'student_id' => $student->id, 'status' => 'failed',
        ]);

        // الأدمن يمسح النتيجة
        $result = StudentResult::where('student_id', $student->id)->first();
        $this->actingAs($this->admin)->deleteJson("/api/admin/results/{$result->id}")
            ->assertOk();

        $this->assertDatabaseMissing('student_results', ['id' => $result->id]);

        // الطالب يعيد التسليم بنجاح (الإجابة لسه موجودة)
        $this->actingAs($student)->postJson("/api/questions/{$question->id}/answer", [
            'student_answer' => 'a', // يصححها
        ])->assertSuccessful();
        $this->actingAs($student)->postJson("/api/exams/{$exam->id}/submit")
            ->assertOk();

        $this->assertDatabaseHas('student_results', [
            'student_id' => $student->id, 'score' => 10, 'status' => 'passed',
        ]);
    }

    // ---------------------------------------------------------------
    // الإشعارات
    // ---------------------------------------------------------------

    public function test_admin_can_send_notification_to_specific_user(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/notifications', [
            'user_id' => $student->id,
            'title' => 'Meeting',
            'message' => 'Please visit the center tomorrow.',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Meeting',
        ]);
    }

    public function test_admin_can_broadcast_to_role_or_everyone(): void
    {
        User::factory()->teacher()->count(3)->create();
        User::factory()->student()->count(4)->create();

        // بث للطلاب بس
        $response = $this->actingAs($this->admin)->postJson('/api/admin/notifications/broadcast', [
            'role' => 'student',
            'title' => 'Holiday',
            'message' => 'Center closed next Sunday.',
        ])->assertCreated();

        $this->assertEquals(4, $response->json('recipients'));
        $this->assertEquals(4, Notification::where('title', 'Holiday')->count());

        // بث للكل
        $all = $this->actingAs($this->admin)->postJson('/api/admin/notifications/broadcast', [
            'title' => 'Announcement',
            'message' => 'New term starts next week.',
        ])->assertCreated();

        // 1 admin + 3 teachers + 4 students
        $this->assertEquals(8, $all->json('recipients'));
    }

    // ---------------------------------------------------------------
    // الأدمن يقدر يعمل كل حاجة على المحتوى زي المدرس
    // ---------------------------------------------------------------

    public function test_admin_can_manage_any_course_exam_and_lesson(): void
    {
        $owner = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $owner->id]);

        // إنشاء درس وامتحان على كورس مش بتعه
        $this->actingAs($this->admin)->postJson("/api/courses/{$course->id}/lessons", [
            'title' => 'Admin Lesson',
            'lesson_date' => now()->addWeek()->toDateTimeString(),
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson("/api/courses/{$course->id}/exams", [
            'title' => 'Admin Exam',
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDays(2)->toDateTimeString(),
            'duration' => 60,
            'total_marks' => 10,
        ])->assertCreated();

        $exam = Exam::where('course_id', $course->id)->first();

        $this->actingAs($this->admin)->postJson("/api/exams/{$exam->id}/questions", [
            'question_text' => 'Q?',
            'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
            'correct_answer' => 'a',
            'marks' => 5,
        ])->assertCreated();

        $question = $exam->questions->first();

        // تعديل وحذف محتوى حد تاني
        $this->actingAs($this->admin)->putJson("/api/exams/{$exam->id}", [
            'title' => 'Admin Exam — edited',
            'start_time' => $exam->start_time->toDateTimeString(),
            'end_time' => $exam->end_time->toDateTimeString(),
            'duration' => 60,
            'total_marks' => 10,
        ])->assertOk();

        $this->actingAs($this->admin)->deleteJson("/api/questions/{$question->id}")
            ->assertOk();

        $this->actingAs($this->admin)->getJson("/api/exams/{$exam->id}/results")
            ->assertOk();
    }
}
