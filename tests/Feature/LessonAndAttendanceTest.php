<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();

        $this->course = Course::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->course->students()->attach($this->student->id);
    }

    public function test_teacher_can_create_lesson_and_students_get_notified(): void
    {
        $response = $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->id}/lessons", [
            'title' => 'Chapter 1',
            'description' => 'Introduction.',
            'lesson_date' => now()->addWeek()->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Chapter 1');

        $this->assertDatabaseHas(Notification::class, [
            'user_id' => $this->student->id,
            'title' => 'New Lesson',
        ]);
    }

    public function test_student_cannot_create_lesson(): void
    {
        $this->actingAs($this->student)->postJson("/api/courses/{$this->course->id}/lessons", [
            'title' => 'Fake Lesson',
            'lesson_date' => now()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_other_teacher_cannot_update_lesson(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->putJson("/api/lessons/{$lesson->id}", [
            'title' => 'Hijacked',
            'lesson_date' => now()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_my_lessons_scopes_by_role_and_paginates(): void
    {
        Lesson::factory()->count(12)->create(['course_id' => $this->course->id]);

        $this->actingAs($this->student)->getJson('/api/my-lessons')
            ->assertOk()
            ->assertJsonCount(10, 'data'); // pagination

        $otherStudent = User::factory()->student()->create();
        $this->actingAs($otherStudent)->getJson('/api/my-lessons')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_teacher_can_record_attendance_for_lesson(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);
        $secondStudent = User::factory()->student()->create();
        $this->course->students()->attach($secondStudent->id);

        $this->actingAs($this->teacher)->postJson("/api/lessons/{$lesson->id}/attendance", [
            'students' => [
                ['student_id' => $this->student->id, 'status' => 'present'],
                ['student_id' => $secondStudent->id, 'status' => 'late'],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Attendance saved successfully.');

        $this->assertDatabaseHas(Attendance::class, [
            'student_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'present',
        ]);

        // إعادة تسجيل نفس الحضور بتعدل مش بتضيف
        $this->actingAs($this->teacher)->postJson("/api/lessons/{$lesson->id}/attendance", [
            'students' => [
                ['student_id' => $this->student->id, 'status' => 'absent'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas(Attendance::class, [
            'student_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'absent',
        ]);

        $this->assertDatabaseCount(Attendance::class, 2);
    }

    public function test_other_teacher_cannot_record_attendance_for_lesson_he_does_not_own(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->postJson("/api/lessons/{$lesson->id}/attendance", [
            'students' => [
                ['student_id' => $this->student->id, 'status' => 'present'],
            ],
        ])->assertForbidden();
    }

    public function test_student_cannot_record_attendance(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        $this->actingAs($this->student)->postJson("/api/lessons/{$lesson->id}/attendance", [
            'students' => [
                ['student_id' => $this->student->id, 'status' => 'present'],
            ],
        ])->assertForbidden();
    }

    public function test_teacher_can_view_lesson_attendance(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        Attendance::create([
            'student_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'present',
        ]);

        $this->actingAs($this->teacher)->getJson("/api/lessons/{$lesson->id}/attendance")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'present')
            ->assertJsonPath('data.0.student.name', $this->student->name);
    }

    public function test_student_can_view_own_attendance(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        Attendance::create([
            'student_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'late',
        ]);

        $this->actingAs($this->student)->getJson('/api/my-attendance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'late')
            ->assertJsonPath('data.0.lesson.course.title', $this->course->title);
    }
}
