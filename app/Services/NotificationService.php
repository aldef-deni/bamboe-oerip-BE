<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Helper pembuatan notifikasi. Dibungkus try/catch agar kegagalan
 * membuat notifikasi tidak pernah menggagalkan alur utama (booking, dll).
 */
class NotificationService
{
    /** Notifikasi untuk seluruh admin/pengelola. */
    public static function admin(string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        static::create('admin', null, $type, $title, $body, $link);
    }

    /** Notifikasi untuk satu pelanggan (user). */
    public static function user(?int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        if (! $userId) {
            return;
        }
        static::create('user', $userId, $type, $title, $body, $link);
    }

    protected static function create(string $audience, ?int $userId, string $type, string $title, ?string $body, ?string $link): void
    {
        try {
            Notification::create([
                'audience' => $audience,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'link' => $link,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat notifikasi: ' . $e->getMessage());
        }
    }
}
