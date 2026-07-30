<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\User;
use App\Support\DivisionTree;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private const GENERAL_CHAT_ID = 'general';

    public function index()
    {
        return view('user.chat.index', [
            'initialUserId' => request()->query('user') ?: self::GENERAL_CHAT_ID,
        ]);
    }

    public function contacts(Request $request)
    {
        $userId = (int) session('user_id');
        $generalLastMessage = ChatMessage::query()
            ->with('sender:id,name')
            ->where('is_global', true)
            ->latest('id')
            ->first();

        $contacts = $this->accessibleUsersQuery()
            ->whereKeyNot($userId)
            ->get()
            ->map(function (User $user) use ($userId) {
                $lastMessage = ChatMessage::query()
                    ->where('is_global', false)
                    ->where(function ($query) use ($userId, $user) {
                        $query->where(function ($directQuery) use ($userId, $user) {
                            $directQuery->where('sender_id', $userId)
                                ->where('recipient_id', $user->id);
                        })->orWhere(function ($directQuery) use ($userId, $user) {
                            $directQuery->where('sender_id', $user->id)
                                ->where('recipient_id', $userId);
                        });
                    })
                    ->latest('id')
                    ->first();

                $unreadCount = ChatMessage::query()
                    ->where('is_global', false)
                    ->where('sender_id', $user->id)
                    ->where('recipient_id', $userId)
                    ->where('is_read', false)
                    ->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'division_name' => $user->division->name ?? null,
                    'last_message' => $lastMessage?->message,
                    'last_message_at' => optional($lastMessage?->created_at)->format('Y-m-d H:i:s'),
                    'last_message_is_mine' => $lastMessage ? (int) $lastMessage->sender_id === $userId : false,
                    'unread_count' => $unreadCount,
                    'is_general' => false,
                ];
            })
            ->sortByDesc(function (array $item) {
                return $item['last_message_at'] ?: '0000-00-00 00:00:00';
            })
            ->values();

        $contacts = $contacts->prepend([
            'id' => self::GENERAL_CHAT_ID,
            'name' => 'Общий чат',
            'role' => 'system',
            'division_name' => 'Все доступные пользователи',
            'last_message' => $generalLastMessage?->message,
            'last_message_at' => optional($generalLastMessage?->created_at)->format('Y-m-d H:i:s'),
            'last_message_is_mine' => $generalLastMessage ? (int) $generalLastMessage->sender_id === $userId : false,
            'unread_count' => 0,
            'is_general' => true,
        ])->values();

        if ($request->filled('search')) {
            $search = mb_strtolower(trim((string) $request->input('search')));

            $contacts = $contacts->filter(function (array $item) use ($search) {
                return str_contains(mb_strtolower($item['name']), $search)
                    || str_contains(mb_strtolower((string) ($item['division_name'] ?? '')), $search)
                    || str_contains(mb_strtolower((string) ($item['last_message'] ?? '')), $search);
            })->values();
        }

        return response()->json([
            'success' => true,
            'items' => $contacts,
            'unread_total' => ChatMessage::query()
                ->where('is_global', false)
                ->where('recipient_id', $userId)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function generalMessages()
    {
        $currentUserId = (int) session('user_id');

        $messages = ChatMessage::query()
            ->with(['sender:id,name'])
            ->where('is_global', true)
            ->orderBy('id')
            ->get()
            ->map(function (ChatMessage $message) use ($currentUserId) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'is_mine' => (int) $message->sender_id === $currentUserId,
                    'is_read' => true,
                    'created_at' => optional($message->created_at)->format('Y-m-d H:i:s'),
                    'sender_name' => $message->sender->name ?? '',
                ];
            });

        return response()->json([
            'success' => true,
            'user' => [
                'id' => self::GENERAL_CHAT_ID,
                'name' => 'Общий чат',
                'role' => 'system',
                'division_name' => 'Все доступные пользователи',
                'is_general' => true,
            ],
            'items' => $messages,
        ]);
    }

    public function messages(User $user)
    {
        $targetUser = $this->resolveChatUser($user);
        $currentUserId = (int) session('user_id');

        ChatMessage::query()
            ->where('is_global', false)
            ->where('sender_id', $targetUser->id)
            ->where('recipient_id', $currentUserId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $messages = ChatMessage::query()
            ->with(['sender:id,name', 'recipient:id,name'])
            ->where('is_global', false)
            ->where(function ($query) use ($currentUserId, $targetUser) {
                $query->where(function ($directQuery) use ($currentUserId, $targetUser) {
                    $directQuery->where('sender_id', $currentUserId)
                        ->where('recipient_id', $targetUser->id);
                })->orWhere(function ($directQuery) use ($currentUserId, $targetUser) {
                    $directQuery->where('sender_id', $targetUser->id)
                        ->where('recipient_id', $currentUserId);
                });
            })
            ->orderBy('id')
            ->get()
            ->map(function (ChatMessage $message) use ($currentUserId) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'is_mine' => (int) $message->sender_id === $currentUserId,
                    'is_read' => (bool) $message->is_read,
                    'created_at' => optional($message->created_at)->format('Y-m-d H:i:s'),
                    'sender_name' => $message->sender->name ?? '',
                ];
            });

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'role' => $targetUser->role,
                'division_name' => $targetUser->division->name ?? null,
                'is_general' => false,
            ],
            'items' => $messages,
        ]);
    }

    public function storeGeneral(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ChatMessage::create([
            'sender_id' => session('user_id'),
            'recipient_id' => null,
            'is_global' => true,
            'message' => trim((string) $request->input('message')),
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Сообщение отправлено в общий чат',
            'item' => [
                'id' => $message->id,
                'message' => $message->message,
                'is_mine' => true,
                'is_read' => true,
                'created_at' => optional($message->created_at)->format('Y-m-d H:i:s'),
                'sender_name' => session('user_name'),
            ],
        ]);
    }

    public function store(Request $request, User $user)
    {
        $targetUser = $this->resolveChatUser($user);
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ChatMessage::create([
            'sender_id' => session('user_id'),
            'recipient_id' => $targetUser->id,
            'is_global' => false,
            'message' => trim((string) $request->input('message')),
        ]);

        Notification::create([
            'user_id' => $targetUser->id,
            'title' => 'Новое сообщение в чате',
            'message' => session('user_name') . ': ' . mb_strimwidth($message->message, 0, 120, '...'),
            'type' => 'info',
            'url' => route('user.chat.index', ['user' => session('user_id')]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Сообщение отправлено',
            'item' => [
                'id' => $message->id,
                'message' => $message->message,
                'is_mine' => true,
                'is_read' => false,
                'created_at' => optional($message->created_at)->format('Y-m-d H:i:s'),
                'sender_name' => session('user_name'),
            ],
        ]);
    }

    private function accessibleUsersQuery()
    {
        $divisionId = session('user_division_id');
        $role = session('user_role');
        $divisionIds = array_values(array_unique(array_merge(
            DivisionTree::managedDivisionIds($divisionId, $role),
            DivisionTree::ancestorAndSelfIds($divisionId)
        )));

        return User::query()
            ->with('division')
            ->where('is_active', true)
            ->where(function ($query) use ($divisionIds) {
                if (!empty($divisionIds)) {
                    $query->whereIn('division_id', $divisionIds);
                } else {
                    $query->whereNull('division_id');
                }
            })
            ->orderBy('name');
    }

    private function resolveChatUser(User $user): User
    {
        $allowedUser = $this->accessibleUsersQuery()
            ->whereKey($user->id)
            ->first();

        abort_unless($allowedUser && (int) $allowedUser->id !== (int) session('user_id'), 403, 'Нет доступа к этому чату');

        return $allowedUser;
    }
}
