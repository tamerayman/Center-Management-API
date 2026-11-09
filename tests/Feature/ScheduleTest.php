<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
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

    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create(array_merge([
            'course_id' => $this->course->id,
            'title' => 'Upcoming Lesson',
            'lesson_date' => now()->addDays(3)->format('Y-m-d 14:00:00'),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'location' => 'Room 5',
            'delivery_type' => 'onsite',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // إنشاء درس بالحقول الجديدة
    // ---------------------------------------------------------------

    public function test_teacher_can_create_lesson_with_schedule_fields(): void
    {
        $response = $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->id}/lessons", [
            'title' => 'Chapter 3 — Online',
            'lesson_date' => now()->addWeek()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'location' => 'Zoom',
            'delivery_type' => 'online',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.delivery_type', 'online')
            ->assertJsonPath('data.location', 'Zoom')
            ->assertJsonPath('data.status', 'upcoming');

        $this->assertDatabaseHas('lessons', [
            'title' => 'Chapter 3 — Online',
            'delivery_type' => 'online',
        ]);
    }

    public function test_lesson_time_validation(): void
    {
        // end_time قبل start_time → مرفوض
        $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->id}/lessons", [
            'title' => 'Bad Times',
            'lesson_date' => now()->addWeek()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '16:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');

        // delivery_type غلط → مرفوض
        $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->id}/lessons", [
            'title' => 'Bad Type',
            'lesson_date' => now()->addWeek()->toDateString(),
            'delivery_type' => 'telepathy',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_type');
    }

    // ---------------------------------------------------------------
    // GET /lessons/upcoming — role-scoped
    // ---------------------------------------------------------------

    public function test_upcoming_lessons_excludes_past_lessons(): void
    {
        $this->createLesson(); // قادم
        Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Old Lesson',
            'lesson_date' => now()->subDays(5)->format('Y-m-d 10:00:00'),
        ]);

        $response = $this->actingAs($this->student)->getJson('/api/lessons/upcoming');
        $response->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Upcoming Lesson', $response->json('data.0.title'));
        $this->assertEquals('upcoming', $response->json('data.0.status'));
    }

    public function test_upcoming_lessons_scoped_for_student(): void
    {
        $this->createLesson();

        // كورس تاني الطالب مش مسجل فيه
        $otherCourse = Course::factory()->create();
        Lesson::create([
            'course_id' => $otherCourse->id,
            'title' => 'Other Course Lesson',
            'lesson_date' => now()->addDays(2)->format('Y-m-d 10:00:00'),
        ]);

        $response = $this->actingAs($this->student)->getJson('/api/lessons/upcoming');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Upcoming Lesson');
    }

    public function test_upcoming_lessons_scoped_for_teacher(): void
    {
        $this->createLesson();

        // مدرس تاني — كورسه عليه درس قادم مش هيشوفه
        $otherTeacher = User::factory()->teacher()->create();
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id]);
        Lesson::create([
            'course_id' => $otherCourse->id,
            'title' => 'Other Teacher Lesson',
            'lesson_date' => now()->addDays(2)->format('Y-m-d 10:00:00'),
        ]);

        $mine = $this->actingAs($this->teacher)->getJson('/api/lessons/upcoming');
        $mine->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Upcoming Lesson');

        $others = $this->actingAs($otherTeacher)->getJson('/api/lessons/upcoming');
        $others->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Other Teacher Lesson');
    }

    public function test_upcoming_lessons_date_filters(): void
    {
        $this->createLesson(); // بعد 3 أيام
        Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Far Lesson',
            'lesson_date' => now()->addDays(20)->format('Y-m-d 10:00:00'),
        ]);

        // من بكرة لـ بعد أسبوع → الدرس القريب بس
        $response = $this->actingAs($this->teacher)->getJson(
            '/api/lessons/upcoming?date_from='.now()->addDay()->toDateString().
            '&date_to='.now()->addWeek()->toDateString()
        );
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Upcoming Lesson');

        // فلتر كورس
        $this->actingAs($this->teacher)->getJson("/api/lessons/upcoming?course_id={$this->course->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_guest_cannot_access_upcoming_lessons(): void
    {
        $this->getJson('/api/lessons/upcoming')->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Guardian — دروس قادمة لطفل معين
    // ---------------------------------------------------------------

    public function test_guardian_can_view_child_upcoming_lessons_only(): void
    {
        $this->createLesson();

        // طفل تاني في كورس تاني بدرس قادم — مش هو
        $otherStudent = User::factory()->student()->create();
        $otherCourse = Course::factory()->create();
        $otherCourse->students()->attach($otherStudent->id);
        Lesson::create([
            'course_id' => $otherCourse->id,
            'title' => 'Not My Child Lesson',
            'lesson_date' => now()->addDays(2)->format('Y-m-d 10:00:00'),
        ]);

        // ولي الأمر يسجل بكود الطالب الأول
        $code = $this->student->guardian_code;
        $token = $this->postJson('/api/guardian/register', [
            'name' => 'Guardian Father',
            'phone' => '01000001234',
            'password' => 'secret1234',
            'guardian_code' => $code,
            'relationship' => 'father',
        ])->json('token');

        $response = $this->withToken($token)
            ->getJson("/api/guardian/children/{$this->student->id}/lessons/upcoming");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Upcoming Lesson');
    }

    public function test_guardian_cannot_view_unrelated_child_upcoming_lessons(): void
    {
        $otherStudent = User::factory()->student()->create();
        $otherCourse = Course::factory()->create();
        $otherCourse->students()->attach($otherStudent->id);
        Lesson::create([
            'course_id' => $otherCourse->id,
            'title' => 'Hidden Lesson',
            'lesson_date' => now()->addDays(2)->format('Y-m-d 10:00:00'),
        ]);

        // Guardian مربوط بطالب تاني
        $this->postJson('/api/guardian/register', [
            'name' => 'Unrelated Guardian',
            'phone' => '01000004321',
            'password' => 'secret1234',
            'guardian_code' => $this->student->guardian_code,
            'relationship' => 'guardian',
        ])->assertCreated();

        $token = $this->postJson('/api/guardian/login', [
            'phone' => '01000004321',
            'password' => 'secret1234',
        ])->json('token');

        $this->withToken($token)
            ->getJson("/api/guardian/children/{$otherStudent->id}/lessons/upcoming")
            ->assertForbidden();
    }
}
