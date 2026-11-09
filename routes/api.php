<?php

use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\ResultController as AdminResultController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\Finance\ChargeController;
use App\Http\Controllers\Finance\CourseFeeController;
use App\Http\Controllers\Finance\CourseFinancialController;
use App\Http\Controllers\Finance\OverdueController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ReportController;
use App\Http\Controllers\Finance\StudentFinancialController;
use App\Http\Controllers\Finance\SubscriptionController;
use App\Http\Controllers\Guardian\AuthController as GuardianAuthController;
use App\Http\Controllers\Guardian\ChildController as GuardianChildController;
use App\Http\Controllers\Guardian\DashboardController as GuardianDashboardController;
use App\Http\Controllers\Guardian\NotificationController as GuardianNotificationController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonMaterialController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentAnswerController;
use App\Http\Controllers\StudentResultController;
use App\Http\Middleware\EnsureGuardian;
use App\Http\Middleware\EnsureUserTokenable;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (public) — rate limited ضد التخمين
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::middleware(['auth:sanctum', EnsureUserTokenable::class, 'throttle:api'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // Email verification — الإرسال للمسجل دخوله، لكن التفعيل عبر لينك موقّع من غير login
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:auth')
        ->name('verification.send');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Guardian — Read-Only Parent Portal (كيان منفصل عن users تماماً)
| الحماية: auth:guardian + EnsureGuardian + GuardianPolicy@viewChild
| بره مجموعة auth:sanctum عشان توكنات Guardian ماتورثش middlewares المستخدمين
|--------------------------------------------------------------------------
*/
// limiter مخصوص: بالموبايل (هوية ولي الأمر) — throttle:auth المشترك كان بيتقاسم بين كل الـ guardians
Route::middleware('throttle:guardian-auth')->prefix('guardian')->group(function () {
    Route::post('/register', [GuardianAuthController::class, 'register']);
    Route::post('/login', [GuardianAuthController::class, 'login']);
});

Route::middleware(['auth:guardian', EnsureGuardian::class, 'throttle:api'])->prefix('guardian')->group(function () {
    Route::post('/logout', [GuardianAuthController::class, 'logout']);

    Route::get('/children', [GuardianChildController::class, 'index']);
    Route::post('/children', [GuardianChildController::class, 'store']);
    Route::get('/children/{student}', [GuardianChildController::class, 'show']);
    Route::get('/children/{student}/payments', [GuardianChildController::class, 'payments']);
    Route::get('/children/{student}/progress', [GuardianChildController::class, 'progress']);
    Route::get('/children/{student}/lessons/upcoming', [GuardianChildController::class, 'upcomingLessons']);
    Route::get('/children/{student}/attendance', [GuardianChildController::class, 'attendance']);

    Route::get('/dashboard', [GuardianDashboardController::class, 'index']);

    Route::get('/notifications', [GuardianNotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [GuardianNotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [GuardianNotificationController::class, 'markAllAsRead']);
});

Route::middleware(['auth:sanctum', EnsureUserTokenable::class, 'throttle:api'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Student Progress — الطالب لنفسه، admin/secretary/teacher(طلاب كورساته) لطالب معين
    Route::get('/progress', [ProgressController::class, 'myProgress'])->middleware('student');
    Route::get('/students/{student}/progress', [ProgressController::class, 'studentProgress']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

    /*
    |--------------------------------------------------------------------
    | Courses
    |--------------------------------------------------------------------
    */
    Route::middleware('teacher')->group(function () {
        Route::apiResource('courses', CourseController::class)->only(['store', 'update', 'destroy']);
        Route::get('/courses/{course}/students', [CourseController::class, 'students']);
    });

    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);
    Route::get('/my-courses', [CourseController::class, 'myCourses']);
    Route::get('/my-courses/{course}', [CourseController::class, 'myCourseDetails']);

    Route::middleware('student')->group(function () {
        Route::post('/courses/{course}/join', [CourseController::class, 'join']);
        Route::delete('/courses/{course}/leave', [CourseController::class, 'leave']);
    });

    /*
    |--------------------------------------------------------------------
    | Lessons
    |--------------------------------------------------------------------
    */
    Route::get('/my-lessons', [LessonController::class, 'myLessons']);
    Route::get('/my-lessons/{lesson}', [LessonController::class, 'myLessonDetails']);
    Route::get('/lessons/upcoming', [LessonController::class, 'upcoming']);

    Route::middleware('teacher')->group(function () {
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
        Route::put('/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::get('/lessons/{lesson}/students', [LessonController::class, 'students']);
    });

    /*
    |--------------------------------------------------------------------
    | Lesson Materials
    |--------------------------------------------------------------------
    */
    Route::get('/lessons/{lesson}/materials', [LessonMaterialController::class, 'index']);
    Route::get('/materials/{material}/download', [LessonMaterialController::class, 'download']);

    Route::middleware('teacher')->group(function () {
        Route::post('/lessons/{lesson}/materials', [LessonMaterialController::class, 'store']);
        Route::match(['post', 'put'], '/materials/{material}', [LessonMaterialController::class, 'update']);
        Route::delete('/materials/{material}', [LessonMaterialController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------
    | Exams + Questions
    |--------------------------------------------------------------------
    */
    Route::get('/my-exams', [ExamController::class, 'myExams']);
    Route::get('/my-exams/{exam}', [ExamController::class, 'myExamDetails']);

    Route::middleware('teacher')->group(function () {
        Route::post('/courses/{course}/exams', [ExamController::class, 'store']);
        Route::put('/exams/{exam}', [ExamController::class, 'update']);
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
    });

    Route::get('/exams/{exam}/questions', [QuestionController::class, 'index']);

    Route::middleware('teacher')->group(function () {
        Route::post('/exams/{exam}/questions', [QuestionController::class, 'store']);
        Route::put('/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------
    | Exam Taking (students)
    |--------------------------------------------------------------------
    */
    Route::middleware('student')->group(function () {
        Route::post('/exams/{exam}/start', [ExamController::class, 'start']);
        Route::post('/questions/{question}/answer', [StudentAnswerController::class, 'answer']);
        Route::post('/exams/{exam}/submit', [StudentResultController::class, 'submit']);
        Route::get('/my-results', [StudentResultController::class, 'myResults']);
    });

    // نتايج بـ role-scoping (student → نتايجه، teacher → كورساته، admin/secretary → الكل)
    Route::get('/results', [StudentResultController::class, 'index']);

    Route::middleware('teacher')->group(function () {
        Route::get('/exams/{exam}/results', [StudentResultController::class, 'examResults']);
    });

    /*
    |--------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------
    */
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/my-attendance', [AttendanceController::class, 'myAttendance']);

    Route::middleware('teacher')->group(function () {
        Route::post('/lessons/{lesson}/attendance', [AttendanceController::class, 'store']);
        Route::get('/lessons/{lesson}/attendance', [AttendanceController::class, 'lessonAttendance']);
    });

    /*
    |--------------------------------------------------------------------
    | Admin — نظام التحكم الكامل
    |--------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {

        // إدارة المستخدمين
        Route::apiResource('users', AdminUserController::class);

        // إدارة الكورسات بالنيابة
        Route::post('/courses', [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}/students', [AdminCourseController::class, 'students']);
        Route::post('/courses/{course}/students', [AdminCourseController::class, 'attachStudents']);
        Route::delete('/courses/{course}/students/{student}', [AdminCourseController::class, 'detachStudent']);

        // إدارة النتايج
        Route::get('/results', [AdminResultController::class, 'index']);
        Route::delete('/results/{result}', [AdminResultController::class, 'destroy']);

        // الإشعارات
        Route::post('/notifications', [AdminNotificationController::class, 'send']);
        Route::post('/notifications/broadcast', [AdminNotificationController::class, 'broadcast']);
    });

    /*
    |--------------------------------------------------------------------
    | Finance — الاشتراكات والفواتير والمدفوعات والتقارير
    | الاسم مجرد مسار: الحماية الحقيقية عبر finance.* gates
    | (admin + secretary لكل المشاهدات، teacher لكورساته فقط، الحساس للأدمن)
    |--------------------------------------------------------------------
    */
    Route::prefix('finance')->middleware('can:finance.access')->group(function () {

        // الاشتراكات
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])
            ->middleware('can:finance.sensitive');
        Route::put('/subscriptions/{subscription}', [SubscriptionController::class, 'update'])
            ->middleware('can:finance.sensitive');

        // الفواتير الشهرية
        Route::get('/charges', [ChargeController::class, 'index']);
        Route::post('/charges/generate', [ChargeController::class, 'generate'])
            ->middleware('can:finance.sensitive');

        // المدفوعات
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::put('/payments/{payment}', [PaymentController::class, 'update'])
            ->middleware('can:finance.sensitive');
        Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])
            ->middleware('can:finance.sensitive');

        // المتأخرات
        Route::get('/overdue', [OverdueController::class, 'index'])
            ->middleware('can:finance.viewAny');

        // الملف المالي لطالب / كورس
        Route::get('/students/{student}', [StudentFinancialController::class, 'show']);
        Route::get('/courses/{course}', [CourseFinancialController::class, 'show']);

        // أسعار الكورسات — للأدمن
        Route::get('/courses/{course}/fees', [CourseFeeController::class, 'index'])
            ->middleware('can:finance.sensitive');
        Route::post('/courses/{course}/fees', [CourseFeeController::class, 'store'])
            ->middleware('can:finance.sensitive');

        // التقارير
        Route::get('/reports/monthly', [ReportController::class, 'monthly'])
            ->middleware('can:finance.viewAny');
        Route::get('/reports/collection', [ReportController::class, 'collection'])
            ->middleware('can:finance.viewAny');
        Route::get('/dashboard', [ReportController::class, 'dashboard'])
            ->middleware('can:finance.viewAny');
        Route::get('/audit-logs', [ReportController::class, 'audit'])
            ->middleware('can:finance.sensitive');
    });

    /*
    |--------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------
    */
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
