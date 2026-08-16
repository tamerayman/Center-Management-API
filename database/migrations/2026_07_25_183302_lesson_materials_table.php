<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('lesson_materials', function (Blueprint $table) {

    $table->id();

    $table->foreignId('lesson_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('title');

    $table->enum('type', [
        'document',
        'presentation',
        'archive',
        'image',
        'youtube',
        'link',
    ]);

    // لو الملف مرفوع على السيرفر
    $table->string('path')->nullable();

    // لو YouTube أو أي لينك خارجي
    $table->string('external_url')->nullable();

    $table->string('mime_type')->nullable();

    // حجم الملف بالبايت
    $table->unsignedBigInteger('file_size')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
