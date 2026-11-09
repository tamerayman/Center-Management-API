<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Notification;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * امتحاناتي — المدرس: امتحانات كورساته، الطالب: امتحانات الكورسات المسجل بها،
     * الأدمن: كل الامتحانات (مع pagination).
     */
    public function myExams(Request $request)
    {
        $user = $request->user();

        $query = Exam::with('course')->latest();

        if ($user->isTeacher()) {
            $query->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->whereHas('course.students', fn ($q) => $q->where('student_id', $user->id));
        }

        return ExamResource::collection($query->paginate($this->perPage($request)));
    }

    public function myExamDetails(Request $request, Exam $exam)
    {
        $user = $request->user();

        $exam = Exam::with(['course', 'course.teacher'])
            ->when($user->isTeacher(), fn ($q) => $q->whereHas(
                'course',
                fn ($c) => $c->where('teacher_id', $user->id)
            ))
            ->when($user->isStudent(), fn ($q) => $q->whereHas(
                'course.students',
                fn ($c) => $c->where('student_id', $user->id)
            ))
            ->findOrFail($exam->id);

        return new ExamResource($exam);
    }

    /**
     * الطالب يبدأ الامتحان — بيتسجل وقت البدء ويترتب عليه
     * آخر لحظة مسموح له يحل فيها (وقت البدء + المدة).
     */
    public function start(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (! $exam->course->students()->where('student_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are not enrolled in this course.',
            ], 403);
        }

        if (now()->lt($exam->start_time)) {
            return response()->json([
                'message' => 'Exam has not started yet.',
            ], 400);
        }

        if (now()->gt($exam->end_time)) {
            return response()->json([
                'message' => 'Exam has already ended.',
            ], 400);
        }

        if ($exam->results()->where('student_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You have already submitted this exam.',
            ], 409);
        }

        $attempt = ExamAttempt::firstOrCreate(
            [
                'student_id' => $user->id,
                'exam_id' => $exam->id,
            ],
            [
                'started_at' => now(),
            ]
        );

        if ($attempt->isExpired()) {
            return response()->json([
                'message' => 'Your exam time is already up. You can no longer answer or submit.',
            ], 400);
        }

        $deadline = $attempt->deadline();

        return response()->json([
            'message' => 'Exam started successfully.',
            'exam_id' => $exam->id,
            'started_at' => $attempt->started_at,
            'duration' => $exam->duration,
            'deadline' => $deadline,
            'time_remaining' => max(0, now()->diffInSeconds($deadline)),
        ]);
    }

    public function store(StoreExamRequest $request, Course $course)
    {
        $this->authorize('create', [Exam::class, $course]);

        $exam = $course->exams()->create($request->validated());

        $this->notifyCourseStudents($course, 'New Exam', "A new exam '{$exam->title}' has been added to {$course->title}.");

        return new ExamResource($exam);
    }

    public function update(StoreExamRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $exam->update($request->validated());

        return new ExamResource($exam);
    }

    public function destroy(Request $request, Exam $exam)
    {
        $this->authorize('delete', $exam);

        $exam->delete();

        return response()->json(['message' => 'Exam deleted successfully']);
    }

    private function notifyCourseStudents(Course $course, string $title, string $message): void
    {
        $now = now();

        $rows = $course->students()
            ->pluck('users.id')
            ->map(fn ($id) => [
                'user_id' => $id,
                'title' => $title,
                'message' => $message,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows->isNotEmpty()) {
            Notification::insert($rows->all());
        }
    }
}
