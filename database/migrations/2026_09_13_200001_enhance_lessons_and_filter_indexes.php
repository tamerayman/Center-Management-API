<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('lesson_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('location')->nullable()->after('end_time');
            $table->enum('delivery_type', ['onsite', 'online'])->default('onsite')->after('location');
            $table->index('lesson_date');
        });

        // فهارس لأعمدة الفلترة المتكررة
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['lesson_date']);
            $table->dropColumn(['start_time', 'end_time', 'location', 'delivery_type']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
        });
    }
};
