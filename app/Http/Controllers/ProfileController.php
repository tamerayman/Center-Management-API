<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Requests\UpdateAvatarRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CourseResource;
use App\Models\LessonMaterial;

class ProfileController extends Controller
{


    // دول شويه بيانات زياده عن ال 
    // ونبعتهم لل ProfileResource عشان نعرضهم في ال profile page

    public function show(Request $request)
    {
        $user = $request->user();
        // ده بيبعت profile +user  مع كمان البيانات الزياده اللي هضفها هنا 
        $profile = $user->profile()->with('user')->first();

        if ($user->role === 'student') {

            $totalScore = $user->results()->sum('score');
            $totalMarks = $user->results()->sum('total_marks');
            $attendanceRate = app(\App\Services\ProgressService::class)->attendancePercentageFor($user) ?? 0;
            $statistics = [

                'courses_count'      => $user->enrolledCourses()->count(),

                'completed_exams'    => $user->results()->count(),

                'total_score'        => $totalScore,

                'total_marks'        => $totalMarks,

                'overall_percentage' => $totalMarks > 0
                    ? round(($totalScore / $totalMarks) * 100, 2)
                    : 0,

                'attendance_rate' => $attendanceRate,

                // 'rank'               => null,  بعدين

            ];

            $courses = $user->enrolledCourses()->get();
        } else {
            $totalCourses = $user->courses();

            $statistics = [

                'courses_count' => $totalCourses->count(),

                'students_count' => $totalCourses->withCount('students')->get()->sum('students_count'),

                'lessons_count' => $totalCourses->withCount('lessons')->get()->sum('lessons_count'),
                // 'lessons' => Lesson::whereIn('course_id', $user->courses()->pluck('id'))->count(),

                'materials_count' => LessonMaterial::whereHas('lesson', function ($query) use ($user) {

                    $query->whereIn('course_id', $user->courses()->pluck('id'));
                })->count(),

                'exams_count' => $totalCourses->withCount('exams')->get()->sum('exams_count'),


            ];

            $courses = $user->courses()->get();
        }

        return (new ProfileResource($profile))->additional([

            'statistics' => $statistics,

            'courses' => CourseResource::collection($courses),

        ]);


        //         في حاجه مش فاهما انا دلوقتي عندي
        //  Route::get('/profile', [profileController::class, 'show']);
        //     Route::put('/profile', [ProfileController::class, 'update']);
        // او ما user يدخل profile هيلاقي هيلاقي ايه فيه بيانات وايه مفيش فيه 
        // يعني هو او حاجه هيدخل علي
        // Route::get('/profile', [profileController::class, 'show']);
        // هيلاقي كله فاضي مفيش غير user 
        // هيدخل بعد كده علي 
        //     Route::put('/profile', [ProfileController::class, 'update']);
        // هيبقي عندي user +prfile
        // وبعد كده كل ما يعمل حاجه في الموقع اضافع كورس امتحان  ويدخل علي
        //  Route::get('/profile', [profileController::class, 'show']);
        // هتعمله الكلام ده ويبقي
        // user+profile +statistic+courses 

    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $profile = $user->profile ?? $user->profile()->create([]);
        $profile->update($request->validated());

        $profile = $user->profile()->with('user')->first();

        return new ProfileResource($profile);
    }



    public function uploadAvatar(UpdateAvatarRequest $request)
    {
        $profile = $request->user()->profile;

        if ($profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $profile->update([
            'avatar_path' => $path,
        ]);

        return new ProfileResource($profile->fresh()->load('user'));
    }


    public function deleteAvatar(Request $request)
    {
        $profile = $request->user()->profile;

        if (!$profile->avatar_path) {
            return response()->json([
                'message' => 'No avatar found.'
            ], 404);
        }

        Storage::disk('public')->delete($profile->avatar_path);

        $profile->update([
            'avatar_path' => null,
        ]);

        return response()->json([
            'message' => 'Avatar deleted successfully.'
        ]);
    }
}
