<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaUserNotification
{
    public static function send(
        int $userId,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?int $companyId = null,
        string $type = 'general',
        array $metadata = []
    ): void {
        if ($userId <= 0 || ! Schema::hasTable('bexia_notifications')) {
            return;
        }

        DB::table('bexia_notifications')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function unreadCountForUser(?int $userId = null): int
    {
        $userId = $userId ?: (int) auth()->id();

        if ($userId <= 0 || ! Schema::hasTable('bexia_notifications')) {
            return 0;
        }

        return DB::table('bexia_notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public static function markAsRead(int $notificationId, ?int $userId = null): void
    {
        $userId = $userId ?: (int) auth()->id();

        if ($notificationId <= 0 || $userId <= 0 || ! Schema::hasTable('bexia_notifications')) {
            return;
        }

        DB::table('bexia_notifications')
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public static function markAllAsRead(?int $userId = null): void
    {
        $userId = $userId ?: (int) auth()->id();

        if ($userId <= 0 || ! Schema::hasTable('bexia_notifications')) {
            return;
        }

        DB::table('bexia_notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
