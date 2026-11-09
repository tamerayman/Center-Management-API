<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('guardian_code', 20)->nullable()->unique()->after('role');
        });

        // Backfill لكل الطلاب الموجودين — كود فريد غير قابل للتخمين
        $existing = User::query()->where('role', 'student')->whereNull('guardian_code')->pluck('id');

        foreach ($existing as $id) {
            User::whereKey($id)->update(['guardian_code' => User::generateGuardianCode()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guardian_code');
        });
    }
};
