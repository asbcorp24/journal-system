<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalEntry;
use App\Models\JournalTemplate;
use App\Models\JournalEntryComment;
use App\Models\Notification;
use App\Models\JournalEntryLog;
use App\Support\DivisionTree;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        if (!in_array(session('user_role'), ['foreman', 'admin'])) {
            abort(403, 'Страница проверки доступна только мастеру и администратору');
        }

        $divisions = Division::whereIn(
            'id',
            DivisionTree::managedDivisionIds(session('user_division_id'), session('user_role'))
        )->orderBy('name')->get();

        return view('user.review.index', compact('divisions'));
    }

    public function list(Request $request)
    {
        if (!in_array(session('user_role'), ['foreman', 'admin'])) {
            abort(403, 'Нет доступа');
        }

        $role = session('user_role');
        $divisionId = session('user_division_id');
        $managedDivisionIds = DivisionTree::managedDivisionIds($divisionId, $role);

        $query = JournalEntry::with([
            'template',
            'user',
            'division',
            'lastComment.user',
        ])
            ->where('status', 'submitted')
            ->orderByDesc('id');

        if ($role === 'foreman') {
            $query->where('division_id', $divisionId);
        }

        if ($role === 'admin') {
            if ($request->filled('division_id') && in_array((int) $request->division_id, $managedDivisionIds, true)) {
                $query->where('division_id', $request->division_id);
            } else {
                $query->whereIn('division_id', $managedDivisionIds);
            }
        }

        if ($request->filled('journal_template_id')) {
            $query->where('journal_template_id', $request->journal_template_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('data', 'like', "%{$search}%")
                    ->orWhereHas('template', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('division', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $entries = $query->paginate(10);

        return response()->json([
            'success' => true,
            'items' => $entries->items(),
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
                'from' => $entries->firstItem(),
                'to' => $entries->lastItem(),
            ],
        ]);
    }

    public function showEntry(JournalEntry $entry)
    {
        if (!in_array(session('user_role'), ['foreman', 'admin'])) {
            abort(403, 'Нет доступа');
        }

        $this->checkEntryAccessForReview($entry);

        $entry->load([
            'template',
            'user',
            'division',
            'lastComment.user',
        ]);

        $schema = $entry->template->schema ?? [];
        $directoryValues = $this->getDirectoryValuesForSchema($schema);

        return response()->json([
            'success' => true,
            'entry' => $entry,
            'schema' => $schema,
            'directory_values' => $directoryValues,
        ]);
    }

    public function approve(Request $request, JournalEntry $entry)
    {
        if (!in_array(session('user_role'), ['foreman', 'admin'])) {
            abort(403, 'Нет доступа');
        }

        $this->checkEntryAccessForReview($entry);

        $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $oldStatus = $entry->status;

        $entry->update([
            'status' => 'approved',
            'checked_by' => session('user_id'),
            'checked_at' => now(),
        ]);

        if ($request->filled('comment')) {
            JournalEntryComment::create([
                'journal_entry_id' => $entry->id,
                'user_id' => session('user_id'),
                'comment' => $request->comment,
            ]);
        }

        $this->writeEntryLog(
            $entry,
            'status_changed',
            $oldStatus,
            'approved',
            $entry->data,
            $entry->data,
            $request->input('comment'),
            $request
        );

        if ($entry->user_id && (int)$entry->user_id !== (int)session('user_id')) {
            $this->createNotification(
                $entry->user_id,
                'Запись подтверждена',
                'Ваша запись была подтверждена пользователем ' . session('user_name') . '.',
                'success',
                route('user.journals.show', $entry->journal_template_id)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Запись подтверждена',
        ]);
    }

    public function reject(Request $request, JournalEntry $entry)
    {
        if (!in_array(session('user_role'), ['foreman', 'admin'])) {
            abort(403, 'Нет доступа');
        }

        $this->checkEntryAccessForReview($entry);

        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $oldStatus = $entry->status;

        $entry->update([
            'status' => 'rejected',
            'checked_by' => session('user_id'),
            'checked_at' => now(),
        ]);

        JournalEntryComment::create([
            'journal_entry_id' => $entry->id,
            'user_id' => session('user_id'),
            'comment' => $request->comment,
        ]);

        $this->writeEntryLog(
            $entry,
            'status_changed',
            $oldStatus,
            'rejected',
            $entry->data,
            $entry->data,
            $request->comment,
            $request
        );

        if ($entry->user_id && (int)$entry->user_id !== (int)session('user_id')) {
            $this->createNotification(
                $entry->user_id,
                'Запись отклонена',
                'Ваша запись была отклонена. Причина: ' . $request->comment,
                'danger',
                route('user.journals.show', $entry->journal_template_id)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Запись отклонена',
        ]);
    }

    private function checkEntryAccessForReview(JournalEntry $entry): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер может проверять только записи своего подразделения');
            }

            return;
        }

        if ($role === 'admin') {
            abort_unless(
                in_array((int) $entry->division_id, DivisionTree::managedDivisionIds($divisionId, $role), true),
                403,
                'РќРµС‚ РґРѕСЃС‚СѓРїР°'
            );
            return;
        }

        abort(403, 'Нет доступа');
    }

    private function getDirectoryValuesForSchema(array $schema)
    {
        $directoryIds = collect($schema)
            ->filter(function ($field) {
                return in_array($field['type'] ?? '', ['directory', 'directory_text'])
                    && !empty($field['directory_id']);
            })
            ->pluck('directory_id')
            ->unique()
            ->values();

        return DirectoryValue::whereIn('directory_id', $directoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');
    }

    private function createNotification(
        int $userId,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $url = null
    ): void {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'url' => $url,
        ]);
    }

    private function writeEntryLog(
        JournalEntry $entry,
        string $action,
                     $oldStatus = null,
                     $newStatus = null,
                     $oldData = null,
                     $newData = null,
        ?string $comment = null,
        ?Request $request = null
    ): void {
        JournalEntryLog::create([
            'journal_entry_id' => $entry->id,
            'user_id' => session('user_id'),
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_data' => $oldData,
            'new_data' => $newData,
            'comment' => $comment,
            'ip_address' => $request ? $request->ip() : null,
        ]);
    }
}
