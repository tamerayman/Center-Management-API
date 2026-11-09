<?php

namespace App\Services;

use App\Models\GuardianNotification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * نقطة الإرسال الوحيدة لإشعارات أولياء الأمور — In-App فقط حالياً.
 * أي حدث مهم يخص طالب بينادي عليها، وهي توزع على كل Guardians المرتبطين به.
 * جاهزة للتوسعة (SMS/WhatsApp) من غير تغيير الـ callers.
 */
class GuardianNotifier
{
    /**
     * @return int عدد الإشعارات المرسلة
     */
    public function notifyGuardians(User $student, string $title, string $message): int
    {
        return $this->notifyGuardiansBulk($student, collect([
            ['title' => $title, 'message' => $message],
        ]));
    }

    /**
     * إرسال عدة إشعارات لنفس الطالب دفعة واحدة (bulk insert).
     *
     * @param  Collection<int, array{title: string, message: string}>  $items
     */
    public function notifyGuardiansBulk(User $student, Collection $items): int
    {
        $guardianIds = $student->guardians()->pluck('guardians.id');

        if ($guardianIds->isEmpty() || $items->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = [];

        foreach ($guardianIds as $guardianId) {
            foreach ($items as $item) {
                $rows[] = [
                    'guardian_id' => $guardianId,
                    'student_id' => $student->id,
                    'title' => $item['title'],
                    'message' => $item['message'],
                    'is_read' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        GuardianNotification::insert($rows);

        return count($rows);
    }
}
