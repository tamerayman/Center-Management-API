<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ضمان عدم تكرار رصيد credit لنفس الدفعة.
     */
    public function up(): void
    {
        Schema::table('student_credits', function (Blueprint $table) {
            $table->unique('source_payment_id', 'sc_source_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_credits', function (Blueprint $table) {
            $table->dropUnique('sc_source_payment_unique');
        });
    }
};
