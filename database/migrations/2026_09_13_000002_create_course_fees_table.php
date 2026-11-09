<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->decimal('monthly_fee', 10, 2);
            $table->date('effective_from');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['course_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_fees');
    }
};
