<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // الدفعة التي نشأ منها هذا الرصيد (الزيادة عن المتبقي على الفاتورة)
            $table->foreignId('source_payment_id')->nullable()->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'remaining_amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_credits');
    }
};
