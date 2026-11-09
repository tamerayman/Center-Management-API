<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * منع الاشتراك النشط المزدوج — تسريع الفحص تحت قفل.
     */
    public function up(): void
    {
        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->index(['student_id', 'course_id', 'status'], 'cs_student_course_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->dropIndex('cs_student_course_status_idx');
        });
    }
};
