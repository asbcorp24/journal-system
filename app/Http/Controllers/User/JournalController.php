<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalEntry;
use App\Models\JournalTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function show(JournalTemplate $journal)
    {
        $this->checkJournalAccess($journal);

        $journal->load('divisions');

        $schema = $journal->schema ?? [];

        $directoryIds = collect($schema)
            ->filter(function ($field) {
                return in_array($field['type'] ?? '', ['directory', 'directory_text'])
                    && !empty($field['directory_id']);
            })
            ->pluck('directory_id')
            ->unique()
            ->values();

        $directoryValues = DirectoryValue::whereIn('directory_id', $directoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');

        $divisions = Division::orderBy('name')->get();

        return view('user.journals.show', compact(
            'journal',
            'schema',
            'directoryValues',
            'divisions'
        ));
    }
    private function checkCanUpdateEntry(JournalEntry $entry): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');

        if ($role === 'worker') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Нет доступа к записи другого подразделения');
            }

            if ((int)$entry->user_id !== (int)$userId) {
                abort(403, 'Работник может редактировать только свои записи');
            }

            if ($entry->status === 'approved') {
                abort(403, 'Подтверждённую запись нельзя редактировать');
            }

            // submitted и rejected редактировать можно
            return;
        }

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер может редактировать только записи своего подразделения');
            }

            return;
        }

        if ($role === 'admin') {
            return;
        }

        abort(403, 'Нет доступа');
    }

    private function checkCanDeleteEntry(JournalEntry $entry): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');

        if ($role === 'worker') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Нет доступа к записи другого подразделения');
            }

            if ((int)$entry->user_id !== (int)$userId) {
                abort(403, 'Работник может удалять только свои записи');
            }

            if ($entry->status === 'approved') {
                abort(403, 'Подтверждённую запись нельзя удалить');
            }

            if ($entry->status === 'rejected') {
                abort(403, 'Отклонённую запись нельзя удалить, но её можно исправить');
            }

            // submitted удалить можно
            return;
        }

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер может удалять только записи своего подразделения');
            }

            return;
        }

        if ($role === 'admin') {
            return;
        }

        abort(403, 'Нет доступа');
    }
    public function list(Request $request, JournalTemplate $journal)
    {
        $this->checkJournalAccess($journal);

        $query = JournalEntry::with(['user', 'division', 'checker','lastComment.user'])
            ->where('journal_template_id', $journal->id)
            ->orderByDesc('id');

        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');

        if ($role === 'worker') {
            $query->where('division_id', $divisionId)
                ->where('user_id', $userId);
        }

        if ($role === 'foreman') {
            $query->where('division_id', $divisionId);
        }

        if ($role === 'admin') {
            if ($request->filled('division_id')) {
                $query->where('division_id', $request->division_id);
            } else {
                $query->where('division_id', $divisionId);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('data', 'like', "%{$search}%")
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
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'from' => $entries->firstItem(),
                'to' => $entries->lastItem(),
            ],
        ]);
    }

    public function store(Request $request, JournalTemplate $journal)
    {
        $this->checkJournalAccess($journal);

        $validatedData = $this->validateEntryData($request, $journal);

        $role = session('user_role');
        $divisionId = session('user_division_id');

        if ($role === 'admin' && $request->filled('division_id')) {
            $divisionId = $request->division_id;
        }

        $entryDate = $this->detectEntryDate($validatedData, $request);

        $entry = JournalEntry::create([
            'journal_template_id' => $journal->id,
            'division_id' => $divisionId,
            'user_id' => session('user_id'),
            'entry_date' => $entryDate,
            'data' => $validatedData,
            'status' => 'submitted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запись добавлена',
            'entry' => $entry,
        ]);
    }

    public function showEntry(JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkEntryAccess($entry);

        return response()->json([
            'success' => true,
            'entry' => $entry,
        ]);
    }

    public function update(Request $request, JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanUpdateEntry($entry);

        $validatedData = $this->validateEntryData($request, $journal);

        $role = session('user_role');

        $divisionId = $entry->division_id;

        if ($role === 'admin' && $request->filled('division_id')) {
            $divisionId = $request->division_id;
        }

        $entryDate = $this->detectEntryDate($validatedData, $request);
        $newStatus = $entry->status;

        if (session('user_role') === 'worker' && $entry->status === 'rejected') {
            $newStatus = 'submitted';
        }
        $entry->update([
            'division_id' => $divisionId,
            'entry_date' => $entryDate,
            'data' => $validatedData,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запись обновлена',
        ]);
    }

    public function approve(JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanChangeStatus($entry);

        $entry->update([
            'status' => 'approved',
            'checked_by' => session('user_id'),
            'checked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запись подтверждена',
        ]);
    }
    private function checkCanChangeStatus(JournalEntry $entry): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');

        if ($role === 'worker') {
            abort(403, 'Работник не может менять статус записей');
        }

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер может менять статус только записей своего подразделения');
            }

            return;
        }

        if ($role === 'admin') {
            return;
        }

        abort(403, 'Нет доступа');
    }
    public function reject(Request $request, JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanChangeStatus($entry);

        $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $entry->update([
            'status' => 'rejected',
            'checked_by' => session('user_id'),
            'checked_at' => now(),
        ]);

        if ($request->filled('comment')) {
            \App\Models\JournalEntryComment::create([
                'journal_entry_id' => $entry->id,
                'user_id' => session('user_id'),
                'comment' => $request->comment,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Запись отклонена',
        ]);
    }
    public function destroy(JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanDeleteEntry($entry);

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена',
        ]);
    }

    private function checkJournalAccess(JournalTemplate $journal): void
    {
        if (!$journal->is_active) {
            abort(404);
        }

        if (session('user_role') === 'admin') {
            return;
        }

        $divisionId = session('user_division_id');

        $hasAccess = $journal->divisions()
            ->where('divisions.id', $divisionId)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'Нет доступа к этому журналу');
        }
    }

    private function checkEntryBelongsToJournal(JournalTemplate $journal, JournalEntry $entry): void
    {
        if ((int)$entry->journal_template_id !== (int)$journal->id) {
            abort(404);
        }
    }

    private function checkEntryAccess(JournalEntry $entry): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');

        if ($role === 'worker') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Нет доступа к записи другого подразделения');
            }

            if ((int)$entry->user_id !== (int)$userId) {
                abort(403, 'Работник может работать только со своими записями');
            }

            return;
        }

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер может работать только с записями своего подразделения');
            }

            return;
        }

        if ($role === 'admin') {
            return;
        }

        abort(403, 'Нет доступа');
    }

    private function validateEntryData(Request $request, JournalTemplate $journal): array
    {
        $schema = $journal->schema ?? [];
        $data = $request->input('data', []);

        if (!is_array($data)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Некорректные данные формы',
            ], 422));
        }

        $result = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;
            $type = $field['type'] ?? 'string';
            $required = !empty($field['required']);

            if (!$key) {
                continue;
            }

            $value = $data[$key] ?? null;

            if ($required && ($value === null || $value === '')) {
                abort(response()->json([
                    'success' => false,
                    'message' => "Поле «{$label}» обязательно для заполнения",
                ], 422));
            }

            if ($value === null || $value === '') {
                $result[$key] = null;
                continue;
            }

            if ($type === 'number') {
                if (!is_numeric($value)) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Поле «{$label}» должно быть числом",
                    ], 422));
                }

                $result[$key] = $value + 0;
                continue;
            }

            if ($type === 'date') {
                $result[$key] = $value;
                continue;
            }

            if ($type === 'time') {
                $result[$key] = $value;
                continue;
            }

            if ($type === 'list') {
                $options = $field['options'] ?? [];

                if (!in_array($value, $options)) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Некорректное значение поля «{$label}»",
                    ], 422));
                }

                $result[$key] = $value;
                continue;
            }

            if ($type === 'directory') {
                $exists = DirectoryValue::where('id', $value)
                    ->where('directory_id', $field['directory_id'] ?? 0)
                    ->exists();

                if (!$exists) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Некорректное значение справочника «{$label}»",
                    ], 422));
                }

                $result[$key] = (int)$value;
                continue;
            }

            if ($type === 'directory_text') {
                $directoryValue = DirectoryValue::where('id', $value)
                    ->where('directory_id', $field['directory_id'] ?? 0)
                    ->first();

                if (!$directoryValue) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Некорректное значение справочника «{$label}»",
                    ], 422));
                }

                $result[$key] = $directoryValue->value;
                continue;
            }

            if ($type === 'calc') {
                $result[$key] = $value;
                continue;
            }

            $result[$key] = trim((string)$value);
        }

        return $result;
    }

    private function detectEntryDate(array $data, Request $request): ?string
    {
        if ($request->filled('entry_date')) {
            return $request->entry_date;
        }

        foreach ($data as $value) {
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }
        }

        return now()->toDateString();
    }
}
