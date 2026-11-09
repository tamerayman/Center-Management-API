<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('course_subscriptions')->cascadeOnDelete();
            // denormalized عن قصد للتقارير السريعة — قيمهم دايماً مطابقة للاشتراك
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            // الجزء المطبق من student credits على هذه الفاتورة — ليس مال جديداً
            $table->decimal('credit_applied', 10, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_year', 'period_month']);
            $table->index(['student_id', 'course_id', 'period_year', 'period_month'], 'monthly_charges_reporting_index');
            $table->index(['period_year', 'period_month', 'status'], 'monthly_charges_period_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_charges');
    }
};
