<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            // snapshot للسعر المتفق عليه مع الطالب وقت الاشتراك — تغيير سعر الكورس بعدين ميلمسوش
            $table->decimal('monthly_fee', 10, 2);
            $table->decimal('monthly_discount', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled', 'completed'])->default('active');
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_subscriptions');
    }
};
