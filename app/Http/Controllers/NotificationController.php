<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ================= Customer =================

    public function index(Request $request)
    {
        return $this->list($this->userQuery($request));
    }

    public function markAllRead(Request $request)
    {
        $this->userQuery($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca.']);
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless($notification->audience === 'user' && (int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifikasi ditandai dibaca.']);
    }

    // ================= Admin =================

    public function adminIndex()
    {
        return $this->list(Notification::where('audience', 'admin'));
    }

    public function adminMarkAllRead()
    {
        Notification::where('audience', 'admin')->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca.']);
    }

    public function adminMarkRead(Notification $notification)
    {
        abort_unless($notification->audience === 'admin', 403);
        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifikasi ditandai dibaca.']);
    }

    // ================= Helpers =================

    protected function userQuery(Request $request)
    {
        return Notification::where('audience', 'user')->where('user_id', $request->user()->id);
    }

    protected function list($query)
    {
        $unread = (clone $query)->whereNull('read_at')->count();
        $items = $query->latest()->limit(40)->get();

        return response()->json([
            'items' => $items,
            'unread' => $unread,
        ]);
    }
}
