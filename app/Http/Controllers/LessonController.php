<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLesson;
use App\Http\Resources\LessonResource;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Notification;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * دروسي — المدرس: دروس كورساته، الطالب: دروس الكورسات المسجل بها،
     * الأدمن: كل الدروس (مع pagination).
     */
    public function myLessons(Request $request)
    {
        $user = $request->user();

        $query = Lesson::with('course')->latest('lesson_date');

        if ($user->isTeacher()) {
            $query->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->whereHas('course.students', fn ($q) => $q->where('student_id', $user->id));
        }

        return LessonResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * الدروس القادمة — role-scoped:
     * student → كورساته المسجل بها، teacher → كورساته، admin/secretary → الكل.
     * فلاتر: course_id / date_from / date_to.
     */
    public function upcoming(Request $request)
    {
        $user = $request->user();

        $query = Lesson::query()
            ->with('course.teacher')
            ->upcoming()
            ->orderBy('lesson_date');

        if ($user->isStudent()) {
            $query->whereHas('course.students', fn ($q) => $q->where('student_id', $user->id));
        } elseif ($user->isTeacher()) {
            $query->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id));
        }

        $query->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->query('course_id')));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('lesson_date', '>=', $request->query('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('lesson_date', '<=', $request->query('date_to')));

        return LessonResource::collection($query->paginate($this->perPage($request)));
    }

    public function myLessonDetails(Request $request, Lesson $lesson)
    {
        $user = $request->user();

        $lesson = Lesson::with(['course', 'course.teacher'])
            ->when($user->isTeacher(), fn ($q) => $q->whereHas(
                'course',
                fn ($c) => $c->where('teacher_id', $user->id)
            ))
            ->when($user->isStudent(), fn ($q) => $q->whereHas(
                'course.students',
                fn ($c) => $c->where('student_id', $user->id)
            ))
            ->findOrFail($lesson->id);

        return new LessonResource($lesson);
    }

    public function store(StoreLessonRequest $request, Course $course)
    {
        $this->authorize('create', [Lesson::class, $course]);

        $lesson = $course->lessons()->create($request->validated());

        $now = now();

        $rows = $course->students()->pluck('users.id')
            ->map(fn ($id) => [
                'user_id' => $id,
                'title' => 'New Lesson',
                'message' => "A new lesson '{$lesson->title}' has been added to {$course->title}.",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows->isNotEmpty()) {
            Notification::insert($rows->all());
        }

        return new LessonResource($lesson);
    }

    public function update(UpdateLesson $request, Lesson $lesson)
    {
        $this->authorize('update', $lesson);

        $lesson->update($request->validated());

        return new LessonResource($lesson);
    }

    public function destroy(Request $request, Lesson $lesson)
    {
        $this->authorize('delete', $lesson);

        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted successfully']);
    }

    public function students(Request $request, Lesson $lesson)
    {
        $this->authorize('update', $lesson); // فقط المدرس المالك أو الأدمن

        $students = $lesson->course->students()->paginate($this->perPage($request, 15));

        return UserResource::collection($students);
    }
}
