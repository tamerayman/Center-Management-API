<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// إشعار أولياء الأمور بامتحانات الأبناء غداً — يومياً 8 صباحاً
Schedule::command('guardian:send-exam-reminders')->dailyAt('08:00');

// الملخص الشهري لكل طفل — أول يوم في الشهر 8 صباحاً
Schedule::command('guardian:send-monthly-summaries')->monthlyOn(1, '08:00');
