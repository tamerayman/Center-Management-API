<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('relationship', ['father', 'mother', 'guardian']);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
    }
};
