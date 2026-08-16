<?php

use App\Models\User;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\LessonController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Route::apiResource('/courses', CourseController::class);
    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);
    Route::middleware('teacher')->group(function () {

        Route::apiResource('courses', CourseController::class)->only(['store', 'update', 'destroy']);
    });

    Route::get('/my-courses', [CourseController::class, 'myCourses']);
    Route::get('/my-courses/{course}', [CourseController::class, 'myCourseDetails']);


    Route::middleware('student')->group(function () {

        Route::post('/courses/{course}/join', [CourseController::class, 'join']);

        Route::delete('/courses/{course}/leave', [CourseController::class, 'leave']);
    });

    Route::get('/my-lessons',[LessonController::class,'mylessons']);
    Route::get('/my-lessons/{lesson}',[LessonController::class,'mylessonsDetails']);
    Route::middleware('teacher')->group(function () {
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
        Route::put('/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);
    });
});
