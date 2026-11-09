<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_authenticated_can_browse_courses_with_pagination(): void
    {
        Course::factory()->count(15)->create();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'teacher']],
                'links',
                'meta',
            ]);

        $this->assertCount(10, $response->json('data')); // 10 per page
    }

    public function test_teacher_can_create_course(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->postJson('/api/courses', [
            'title' => 'Laravel Basics',
            'description' => 'Learn Laravel from scratch.',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Laravel Basics');

        $this->assertDatabaseHas(Course::class, [
            'title' => 'Laravel Basics',
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_student_cannot_create_course(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->postJson('/api/courses', [
            'title' => 'My Course',
        ])->assertForbidden();
    }

    public function test_owner_teacher_can_update_and_delete_course(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->putJson("/api/courses/{$course->id}", [
            'title' => 'Updated Title',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        $this->actingAs($teacher)->deleteJson("/api/courses/{$course->id}")
            ->assertOk();

        $this->assertDatabaseMissing(Course::class, ['id' => $course->id]);
    }

    public function test_another_teacher_cannot_update_someone_elses_course(): void
    {
        $course = Course::factory()->create();
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->putJson("/api/courses/{$course->id}", [
            'title' => 'Hacked Title',
        ])->assertForbidden();

        $this->actingAs($otherTeacher)->deleteJson("/api/courses/{$course->id}")
            ->assertForbidden();
    }

    public function test_admin_can_update_and_delete_any_course(): void
    {
        $course = Course::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson("/api/courses/{$course->id}", [
            'title' => 'Admin Updated',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Admin Updated');

        $this->actingAs($admin)->deleteJson("/api/courses/{$course->id}")
            ->assertOk();

        $this->assertDatabaseMissing(Course::class, ['id' => $course->id]);
    }

    public function test_student_can_join_and_leave_a_course(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();

        $this->actingAs($student)->postJson("/api/courses/{$course->id}/join")
            ->assertOk()
            ->assertJsonPath('message', 'Course joined successfully.');

        $this->assertDatabaseHas('course_student', [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        // انضمام مرتين مرفوض
        $this->actingAs($student)->postJson("/api/courses/{$course->id}/join")
            ->assertConflict();

        $this->actingAs($student)->deleteJson("/api/courses/{$course->id}/leave")
            ->assertOk();

        $this->assertDatabaseMissing('course_student', [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_my_courses_returns_enrolled_courses_for_student(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        $course->students()->attach($student->id);

        $this->actingAs($student)->getJson('/api/my-courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $course->id);
    }

    public function test_student_cannot_see_details_of_course_not_enrolled_in(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();

        $this->actingAs($student)->getJson("/api/my-courses/{$course->id}")
            ->assertNotFound();
    }

    public function test_teacher_can_list_course_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $students = User::factory()->student()->count(3)->create();
        $course->students()->attach($students->pluck('id'));

        $this->actingAs($teacher)->getJson("/api/courses/{$course->id}/students")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_see_dashboard_statistics(): void
    {
        $admin = User::factory()->admin()->create();
        $teachers = User::factory()->teacher()->count(2)->create();
        User::factory()->student()->count(5)->create();

        Course::factory()->count(3)->create(['teacher_id' => $teachers->first()->id]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.statistics.teachers_count', 2)
            ->assertJsonPath('data.statistics.students_count', 5)
            ->assertJsonPath('data.statistics.courses_count', 3);
    }

    public function test_teacher_dashboard_shows_own_statistics(): void
    {
        $teacher = User::factory()->teacher()->create();
        Course::factory()->count(2)->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.statistics.courses_count', 2);
    }
}
