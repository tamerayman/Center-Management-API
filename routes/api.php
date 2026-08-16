<?php

use App\Models\User;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentResultController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LessonMaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use App\Http\Controllers\StudentAnswerController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    
    //dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    //profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

    // Route::apiResource('/courses', CourseController::class);
    //Course
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
    // Lessons
    Route::get('/my-lessons', [LessonController::class, 'mylessons']);
    Route::get('/my-lessons/{lesson}', [LessonController::class, 'mylessonsDetails']);
    Route::middleware('teacher')->group(function () {
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
        Route::put('/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);

        Route::get('/courses/{course}/students', [CourseController::class, 'students']);
        Route::get('/{lesson}/students', [LessonController::class, 'student']);
    });
    // Exams
    Route::get('/myExams', [ExamController::class, 'myExams']);
    Route::get('/myExamDetails/{exam}', [ExamController::class, 'myExamDetails']);
    Route::middleware('teacher')->group(function () {
        Route::post('/courses/{course}/exams', [ExamController::class, 'store']);
        Route::put('/exams/{exam}', [ExamController::class, 'update']);
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
    });


    // Questions
    Route::get('/exams/{exam}/questions', [QuestionController::class, 'index']);

    Route::middleware('teacher')->group(function () {
        Route::post('/exams/{exam}/questions', [QuestionController::class, 'store']);
        Route::put('/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);
    });


    // Student Answer and Result Routes
    Route::middleware('student')->group(function () {

        Route::post('/questions/{question}/answer', [StudentAnswerController::class, 'answer']);
        Route::post('/exams/{exam}/submit', [StudentResultController::class, 'submit']);
        Route::get('/myResults', [StudentResultController::class, 'myResults']);
    });
    Route::middleware('teacher')->group(function () {
        Route::get('/exams/{exam}/results', [StudentResultController::class, 'examResults']);
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    //Attendance
    Route::get('/myAttendance', [AttendanceController::class, 'myAttendance']);

    Route::middleware('teacher')->group(function () {

        Route::post('/lessons/{lesson}/attendance', [AttendanceController::class, 'store']);

        Route::get('/lessons/{lesson}/attendance', [AttendanceController::class, 'lessonAttendance']);
    });

    //LessonMaterial
    Route::get('/lessons/{lesson}/materials', [LessonMaterialController::class, 'index']);
    Route::get('/materials/{material}/download', [LessonMaterialController::class, 'download']);
    Route::middleware('teacher')->group(function () {

        Route::post('/lessons/{lesson}/materials', [LessonMaterialController::class, 'store']);

        Route::match(['post', 'put'], '/materials/{material}', [LessonMaterialController::class, 'update']);

        Route::delete('/materials/{material}', [LessonMaterialController::class, 'destroy']);
    });
});
