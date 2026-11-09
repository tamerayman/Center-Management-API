<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Notification;
use App\Models\Question;
use App\Models\StudentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();

        // كورس للمدرس، الطالب منضم له، وامتحان مفتوح على الكورس
        $course = Course::factory()->create(['teacher_id' => $this->teacher->id]);

        $this->exam = Exam::factory()
            ->open()
            ->create([
                'course_id' => $course->id,
                'title' => 'Midterm Exam',
                'duration' => 60,
                'total_marks' => 10,
                'pass_mark' => 50,
            ]);

        $course->students()->attach($this->student->id);

        // سؤالين: كل واحد 5 درجات — الإجابة الصح في الاتنين = 10/10
        Question::factory()->create(['exam_id' => $this->exam->id, 'marks' => 5, 'correct_answer' => 'a']);
        Question::factory()->create(['exam_id' => $this->exam->id, 'marks' => 5, 'correct_answer' => 'b']);
    }

    private function question(int $index): Question
    {
        return $this->exam->questions->get($index);
    }

    public function test_teacher_can_create_exam_and_students_get_notified(): void
    {
        $this->actingAs($this->teacher)->postJson("/api/courses/{$this->exam->course_id}/exams", [
            'title' => 'Quiz 1',
            'description' => 'First quiz.',
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->addHours(2)->toDateTimeString(),
            'duration' => 60,
            'total_marks' => 20,
            'pass_mark' => 60,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Quiz 1')
            ->assertJsonPath('data.pass_mark', 60);

        $this->assertDatabaseHas(Notification::class, [
            'user_id' => $this->student->id,
            'title' => 'New Exam',
        ]);
    }

    public function test_student_cannot_create_exam(): void
    {
        $this->actingAs($this->student)->postJson("/api/courses/{$this->exam->course_id}/exams", [
            'title' => 'Fake Exam',
            'start_time' => now()->toDateTimeString(),
            'end_time' => now()->addHour()->toDateTimeString(),
            'duration' => 60,
            'total_marks' => 10,
        ])->assertForbidden();
    }

    public function test_other_teacher_cannot_update_exam_he_does_not_own(): void
    {
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->putJson("/api/exams/{$this->exam->id}", [
            'title' => 'Hijacked',
            'start_time' => $this->exam->start_time->toDateTimeString(),
            'end_time' => $this->exam->end_time->toDateTimeString(),
            'duration' => 60,
            'total_marks' => 10,
        ])->assertForbidden();
    }

    public function test_student_must_be_enrolled_to_start_exam(): void
    {
        $outsider = User::factory()->student()->create();

        $this->actingAs($outsider)->postJson("/api/exams/{$this->exam->id}/start")
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not enrolled in this course.');
    }

    public function test_student_cannot_start_exam_before_it_opens(): void
    {
        $exam = Exam::factory()->upcoming()->create([
            'course_id' => $this->exam->course_id,
        ]);

        $this->actingAs($this->student)->postJson("/api/exams/{$exam->id}/start")
            ->assertBadRequest()
            ->assertJsonPath('message', 'Exam has not started yet.');
    }

    public function test_student_starts_exam_and_gets_deadline_based_on_duration(): void
    {
        $this->travelTo(now()->startOfMinute());

        $response = $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        $response->assertOk()
            ->assertJsonPath('duration', 60);

        // المدة 60 دقيقة، ونهاية الامتحان بعد 3 ساعات → الـ deadline هو وقت البدء + 60 دقيقة
        $this->assertSame(
            now()->addMinutes(60)->format('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', strtotime($response->json('deadline'))),
        );

        $this->assertDatabaseHas(ExamAttempt::class, [
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
        ]);
    }

    public function test_second_start_returns_same_attempt_not_a_new_one(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");
        $first = $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        $first->assertOk();

        $this->assertDatabaseCount(ExamAttempt::class, 1);
    }

    public function test_student_cannot_answer_without_starting_exam(): void
    {
        $question = $this->question(0);

        $this->actingAs($this->student)->postJson("/api/questions/{$question->id}/answer", [
            'student_answer' => 'a',
        ])->assertBadRequest()
            ->assertJsonPath('message', 'You must start the exam before answering.');
    }

    public function test_student_answers_question_and_correctness_is_graded(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        $q0 = $this->question(0);
        $q1 = $this->question(1);

        // سؤال صح وسؤال غلط → الإجابة الغلط مش بيكشف الصح
        $this->actingAs($this->student)->postJson("/api/questions/{$q0->id}/answer", [
            'student_answer' => 'A', // صح (uppercase مقبول)
        ])->assertSuccessful();

        $this->actingAs($this->student)->postJson("/api/questions/{$q1->id}/answer", [
            'student_answer' => 'c', // غلط
        ])->assertSuccessful();

        $this->assertDatabaseHas('student_answers', [
            'student_id' => $this->student->id,
            'question_id' => $q0->id,
            'is_correct' => true,
        ]);

        $this->assertDatabaseHas('student_answers', [
            'student_id' => $this->student->id,
            'question_id' => $q1->id,
            'is_correct' => false,
        ]);
    }

    public function test_student_cannot_see_correct_answer_in_questions_list(): void
    {
        $this->actingAs($this->student)->getJson("/api/exams/{$this->exam->id}/questions")
            ->assertOk()
            ->assertJsonMissing(['correct_answer' => 'a'])
            ->assertJsonMissing(['correct_answer' => 'b']);
    }

    public function test_student_cannot_submit_before_answering_all_questions(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit")
            ->assertBadRequest()
            ->assertJsonPath('message', 'You must answer all questions before submitting.');
    }

    public function test_student_submits_exam_and_gets_graded_with_pass_mark(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        $this->actingAs($this->student)->postJson("/api/questions/{$this->question(0)->id}/answer", [
            'student_answer' => 'a', // صح → 5
        ]);

        $this->actingAs($this->student)->postJson("/api/questions/{$this->question(1)->id}/answer", [
            'student_answer' => 'c', // غلط → 0
        ]);

        $response = $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit");

        $response->assertOk()
            ->assertJsonPath('result.score', 5)
            ->assertJsonPath('result.total_marks', 10);

        // 5 >= (10 * 50 / 100) = 5 → ناجح بالظبط على الـ pass_mark
        $this->assertDatabaseHas(StudentResult::class, [
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'score' => 5,
            'total_marks' => 10,
            'status' => 'passed',
        ]);

        $this->assertDatabaseHas(Notification::class, [
            'user_id' => $this->student->id,
            'title' => 'Exam Result',
        ]);
    }

    public function test_student_passes_exam_with_custom_pass_mark(): void
    {
        $this->exam->update(['pass_mark' => 40]);

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        $this->actingAs($this->student)->postJson("/api/questions/{$this->question(0)->id}/answer", [
            'student_answer' => 'a', // 5 من 10 = 50% ≥ 40% → ناجح
        ]);

        $this->actingAs($this->student)->postJson("/api/questions/{$this->question(1)->id}/answer", [
            'student_answer' => 'c',
        ]);

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit")
            ->assertOk();

        $this->assertDatabaseHas(StudentResult::class, [
            'score' => 5,
            'status' => 'passed',
        ]);
    }

    public function test_student_cannot_submit_twice(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");
        $this->answerAllCorrectly();
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit")->assertOk();

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit")
            ->assertConflict();
    }

    public function test_student_cannot_answer_after_duration_expires(): void
    {
        $this->travelTo(now()->startOfMinute());

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");

        // نسافر 61 دقيقة — المدة 60 دقيقة خلصت
        $this->travel(61)->minutes();

        $this->actingAs($this->student)->postJson("/api/questions/{$this->question(0)->id}/answer", [
            'student_answer' => 'a',
        ])->assertBadRequest()
            ->assertJsonPath('message', 'Your exam time is up. You can no longer answer.');
    }

    public function test_student_cannot_submit_after_duration_expires(): void
    {
        $this->travelTo(now()->startOfMinute());

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");
        $this->answerAllCorrectly();

        $this->travel(90)->minutes(); // المدة 60 خلصت، بس الامتحان نفسه لسه مفتوح ساعة تانية

        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit")
            ->assertBadRequest()
            ->assertJsonPath('message', 'Your exam time is up. You can no longer submit.');
    }

    public function test_student_cannot_answer_after_exam_window_ends(): void
    {
        $exam = Exam::factory()->ended()->create([
            'course_id' => $this->exam->course_id,
        ]);

        Question::factory()->create(['exam_id' => $exam->id, 'marks' => 5, 'correct_answer' => 'a']);
        $question = $exam->questions->first();

        $this->actingAs($this->student)->postJson("/api/questions/{$question->id}/answer", [
            'student_answer' => 'a',
        ])->assertBadRequest();
    }

    public function test_student_can_view_own_results(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");
        $this->answerAllCorrectly();
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit");

        $this->actingAs($this->student)->getJson('/api/my-results')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score', 10)
            ->assertJsonPath('data.0.status', 'passed');
    }

    public function test_teacher_can_view_exam_results_but_other_teacher_cannot(): void
    {
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/start");
        $this->answerAllCorrectly();
        $this->actingAs($this->student)->postJson("/api/exams/{$this->exam->id}/submit");

        $this->actingAs($this->teacher)->getJson("/api/exams/{$this->exam->id}/results")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->getJson("/api/exams/{$this->exam->id}/results")
            ->assertForbidden();
    }

    public function test_my_exams_does_not_crash_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/my-exams')
            ->assertOk()
            ->assertJsonCount(1, 'data'); // الأدمن يشوف كل الامتحانات
    }

    public function test_my_exams_paginates_and_scopes_by_role(): void
    {
        // 3 امتحانات إضافية للكورس → المجموع 4
        Exam::factory()->count(3)->create(['course_id' => $this->exam->course_id]);

        $teacherPage = $this->actingAs($this->teacher)->getJson('/api/my-exams?per_page=2');
        $teacherPage->assertOk()->assertJsonCount(2, 'data');
        $this->assertEquals(4, $teacherPage->json('meta.total'));

        // طالب مش منضم في كورس تاني → يشوف امتحان كورسه بس
        $otherStudent = User::factory()->student()->create();
        $this->actingAs($otherStudent)->getJson('/api/my-exams')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function answerAllCorrectly(): void
    {
        foreach ($this->exam->questions as $question) {
            $this->actingAs($this->student)->postJson("/api/questions/{$question->id}/answer", [
                'student_answer' => $question->correct_answer,
            ])->assertSuccessful();
        }
    }
}
