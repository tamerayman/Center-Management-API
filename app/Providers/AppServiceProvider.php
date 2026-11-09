<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseFee;
use App\Models\CourseSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Observers\CourseFeeObserver;
use App\Observers\CourseSubscriptionObserver;
use App\Observers\PaymentObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Payment::observe(PaymentObserver::class);
        CourseFee::observe(CourseFeeObserver::class);
        CourseSubscription::observe(CourseSubscriptionObserver::class);

        // Listeners بتاعة الـ financial events (PaymentRecorded / PaymentCancelled / ChargesGenerated)
        // بتتسجل أوتوماتيك من app/Listeners — ممنوع نكرر التسجيل هنا

        $this->registerFinanceGates();

        // حد أقصى 5 محاولات في الدقيقة لكل IP + إيميل على مسارات الحساس الحساسة
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip().'|'.strtolower((string) $request->input('email'))
            );
        });

        // تسجيل/دخول ولي الأمر — بالموبايل (هويته) + IP — 5/دقيقة
        RateLimiter::for('guardian-auth', function (Request $request) {
            return Limit::perMinute(5)->by(
                'guardian:'.$request->ip().'|'.(string) $request->input('phone')
            );
        });

        // حد عام لكل الـ API — 120 طلب/دقيقة لكل مستخدم (أو IP للزوار)
        // كافي جداً لأي frontend حقيقي، وبيمنع الـ floods والـ scripts
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            $key = $user
                ? class_basename($user).':'.$user->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });
    }

    /**
     * صلاحيات النظام المالي — اسم الـ route مش مصدر الحماية.
     */
    private function registerFinanceGates(): void
    {
        // مشاهدة البيانات المالية العامة (كل الطلاب/الكورسات/التقارير/المتأخرات)
        // دخول قسم المالية بشكل عام — admin + secretary + teacher
        // (الحماية التفصيلية على كل route/controller)
        Gate::define('finance.access', function (User $user) {
            return $user->isFinancialStaff() || $user->isTeacher();
        });

        Gate::define('finance.viewAny', function (User $user) {
            return $user->isFinancialStaff();
        });

        // مشاهدة البيانات المالية لكورس معين — المدرس لكورساته فقط
        Gate::define('finance.viewCourse', function (User $user, Course $course) {
            if ($user->isFinancialStaff()) {
                return true;
            }

            return $user->isTeacher() && $course->teacher_id === $user->id;
        });

        // تسجيل دفعة على كورس معين — المدرس لكورساته فقط
        Gate::define('finance.recordPayment', function (User $user, Course $course) {
            if ($user->isFinancialStaff()) {
                return true;
            }

            return $user->isTeacher() && $course->teacher_id === $user->id;
        });

        // الملف المالي لطالب معين — المدرس لو الطالب من طلاب كورساته
        Gate::define('finance.viewStudent', function (User $user, User $student) {
            if ($user->isFinancialStaff()) {
                return true;
            }

            if (! $user->isTeacher()) {
                return false;
            }

            return $student->enrolledCourses()
                ->where('teacher_id', $user->id)
                ->exists();
        });

        // العمليات الحساسة: أسعار، اشتراكات (إنشاء/تعديل)، توليد فواتير،
        // تعديل/إلغاء دفعات — للأدمن فقط
        Gate::define('finance.sensitive', function (User $user) {
            return $user->isAdmin();
        });
    }
}
