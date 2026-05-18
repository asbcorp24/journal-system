<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return view('user.notifications.index');
    }

    public function list(Request $request)
    {
        $query = Notification::query()
            ->where('user_id', session('user_id'))
            ->orderByDesc('id');

        if ($request->filled('is_read')) {
            $query->where('is_read', (bool)$request->is_read);
        }

        $notifications = $query->paginate(15);

        return response()->json([
            'success' => true,
            'items' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ]);
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', session('user_id'))
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function markRead(Notification $notification)
    {
        $this->checkAccess($notification);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Уведомление прочитано',
        ]);
    }

    public function markAllRead()
    {
        Notification::where('user_id', session('user_id'))
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Все уведомления прочитаны',
        ]);
    }

    public function open(Notification $notification)
    {
        $this->checkAccess($notification);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        if ($notification->url) {
            return redirect($notification->url);
        }

        return redirect()->route('user.notifications.index');
    }

    private function checkAccess(Notification $notification): void
    {
        if ((int)$notification->user_id !== (int)session('user_id')) {
            abort(403, 'Нет доступа к уведомлению');
        }
    }
}
