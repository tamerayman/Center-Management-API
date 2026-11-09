<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('monthly_charge_id')->nullable()->constrained('monthly_charges')->cascadeOnDelete();
            // المبلغ الكامل الذي دفعه الطالب فعلياً — الـ allocation للفاتورة منفصل
            $table->decimal('amount', 10, 2);
            // الجزء الذي ذهب فعلياً لفاتورة الشهر — الباقي تحول لـ student credit
            $table->decimal('allocated_amount', 10, 2)->default(0);
            $table->date('paid_at');
            $table->enum('method', ['cash', 'vodafone_cash', 'bank_transfer', 'card', 'other']);
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->string('note')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['monthly_charge_id', 'status']);
            $table->index(['student_id', 'paid_at']);
            $table->index(['method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
