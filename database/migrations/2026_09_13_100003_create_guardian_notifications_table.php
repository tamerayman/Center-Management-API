<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            // الطفل الذي يخصه الإشعار — Guardian ممكن يكون عنده أكثر من طفل
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['guardian_id', 'is_read']);
            $table->index(['guardian_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_notifications');
    }
};
