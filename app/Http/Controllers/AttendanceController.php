<?php

namespace App\Http\Controllers;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Lesson;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
public function store(StoreAttendanceRequest $request, Lesson $lesson)
{
    $this->authorize('create', [Attendance::class, $lesson]);

    $validated = $request->validated();

    foreach ($validated['students'] as $student) {

        Attendance::updateOrCreate(
            [
                'student_id' => $student['student_id'],
                'lesson_id' => $lesson->id,
            ],
            [
                'status' => $student['status'],
            ]
        );
    }

    return response()->json([
        'message' => 'Attendance saved successfully.'
    ]);
}

public function lessonAttendance(Lesson $lesson)
{
  $this->authorize('viewAttendance', [Attendance::class, $lesson]);

    $attendance = $lesson->attendances()->with('student')->get();

    return AttendanceResource::collection($attendance);
}

public function myAttendance(Request $request)
{
     $attendance = $request->user()->attendances()->with('lesson.course')->get();

    return AttendanceResource::collection($attendance);

}
// هي دي هتعدل طالب لواحده بس ملهاش لزمه لان اول واحده بتعدل اصلا سواء طالب واحد او عدد
// public function update(StoreAttendanceRequest $request, Attendance $attendance)
// {
//     $this->authorize('update', $attendance);

//     $attendance->update([
//         'status' => $request->validated()['status'],
//     ]);

//     return new AttendanceResource($attendance);
// }
}
