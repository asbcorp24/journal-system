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
use App\Models\JournalEntryComment;
use App\Models\JournalEntryLog;
use App\Models\Notification;
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
    public function print(Request $request, JournalTemplate $journal)
    {
        $this->checkJournalAccess($journal);

        $schema = $journal->schema ?? [];

        $query = JournalEntry::with([
            'user',
            'division',
            'checker',
            'lastComment.user',
        ])
            ->where('journal_template_id', $journal->id)
            ->orderByDesc('entry_date')
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

        $entries = $query->get();

        $directoryValues = $this->getDirectoryValuesForSchema($schema);

        return view('user.journals.print', compact(
            'journal',
            'schema',
            'entries',
            'directoryValues'
        ));
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

        $query = JournalEntry::with([
            'user',
            'division',
            'checker',
            'lastComment.user',
            'rootComments',
        ])
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
        $watchers = $this->getForemenAndAdminsForDivision($entry->division_id);

        $this->notifyUsers(
            $watchers,
            'Новая запись в журнале',
            'Пользователь ' . session('user_name') . ' добавил новую запись на проверку.',
            'info',
            $this->entryUrl($entry),
            session('user_id')
        );
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
        $oldData = $entry->data;
        $oldStatus = $entry->status;

        $entry->update([
            'division_id' => $divisionId,
            'entry_date' => $entryDate,
            'data' => $validatedData,
        ]);
        if (session('user_role') === 'worker' && $oldStatus === 'rejected') {
            $watchers = $this->getForemenAndAdminsForDivision($entry->division_id);

            $this->notifyUsers(
                $watchers,
                'Запись исправлена',
                'Пользователь ' . session('user_name') . ' исправил отклонённую запись и отправил её на повторную проверку.',
                'warning',
                $this->entryUrl($entry),
                session('user_id')
            );
        }
        $this->writeEntryLog(
            $entry,
            'updated',
            $oldStatus,
            $entry->status,
            $oldData,
            $validatedData,
            $request->input('change_comment'),
            $request
        );
        return response()->json([
            'success' => true,
            'message' => 'Запись обновлена',
        ]);
    }

    public function approve(Request $request, JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanChangeStatus($entry);
        $oldStatus = $entry->status;
        $entry->update([
            'status' => 'approved',
            'checked_by' => session('user_id'),
            'checked_at' => now(),
        ]);
        if ($entry->user_id) {
            $this->createNotification(
                $entry->user_id,
                'Запись подтверждена',
                'Ваша запись была подтверждена пользователем ' . session('user_name') . '.',
                'success',
                $this->entryUrl($entry)
            );
        }
        $this->writeEntryLog(
            $entry,
            'status_changed',
            $oldStatus,
            'approved',
            $entry->data,
            $entry->data,
            $request->comment,
            $request
        );
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
        $oldStatus = $entry->status;
        $entry->update([
            'status' => 'rejected',
            'checked_by' => session('user_id'),
            'checked_at' => now(),
        ]);
        if ($entry->user_id) {
            $this->createNotification(
                $entry->user_id,
                'Запись отклонена',
                'Ваша запись была отклонена. Причина: ' . $request->comment,
                'danger',
                $this->entryUrl($entry)
            );
        }
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
    public function logs(JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanViewEntry($entry);

        $logs = $entry->logs()
            ->with('user')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'logs' => $logs,
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
                $result[$key] = null;
                continue;
            }

            $result[$key] = trim((string)$value);
        }
        $result = $this->calculateCalcFields($schema, $result);
        // ВОТ ЗДЕСЬ ПРОВЕРЯЕМ ОГРАНИЧЕНИЯ ИЗ ШАБЛОНА ЖУРНАЛА
        $this->validateNumericConstraints($schema, $result);

        return $result;
    }
    private function calculateCalcFields(array $schema, array $data): array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? '') !== 'calc') {
                continue;
            }

            $key = $field['key'] ?? null;
            $formula = $field['formula'] ?? '';

            if (!$key || !$formula) {
                continue;
            }

            $value = $this->evaluateFormula($formula, $data);

            $data[$key] = $value;
        }

        return $data;
    }

    private function evaluateFormula(string $formula, array $data)
    {
        $expression = $formula;

        foreach ($data as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                continue;
            }

            if ($value === null || $value === '') {
                $value = 0;
            }

            if (!is_numeric($value)) {
                $value = 0;
            }

            $expression = preg_replace(
                '/\b' . preg_quote($key, '/') . '\b/',
                (string)((float)$value),
                $expression
            );
        }

        /*
         * После подстановки разрешаем только:
         * цифры, точку, пробелы, + - * / ( )
         */
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
            abort(response()->json([
                'success' => false,
                'message' => "Некорректная формула вычисляемого поля: {$formula}",
            ], 422));
        }

        try {
            // Важно: eval используем только после строгой очистки выражения.
            $result = eval('return ' . $expression . ';');
        } catch (\Throwable $e) {
            abort(response()->json([
                'success' => false,
                'message' => "Ошибка расчёта формулы: {$formula}",
            ], 422));
        }

        if (!is_numeric($result) || is_infinite($result) || is_nan($result)) {
            abort(response()->json([
                'success' => false,
                'message' => "Формула вернула некорректное значение: {$formula}",
            ], 422));
        }

        return round((float)$result, 6);
    }
    private function validateNumericConstraints(array $schema, array $data): void
    {
        $fieldsByKey = collect($schema)->keyBy('key');

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;
            $type = $field['type'] ?? 'string';

            if (!$key || !in_array($type, ['number', 'calc'])) {
                continue;
            }

            $validation = $field['validation'] ?? [];

            if (empty($validation)) {
                continue;
            }

            $value = $data[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (!is_numeric($value)) {
                continue;
            }

            $number = (float)$value;

            if (isset($validation['min']) && $validation['min'] !== '') {
                $min = (float)$validation['min'];

                if ($number < $min) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Поле «{$label}» должно быть не меньше {$min}",
                    ], 422));
                }
            }

            if (isset($validation['max']) && $validation['max'] !== '') {
                $max = (float)$validation['max'];

                if ($number > $max) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Поле «{$label}» должно быть не больше {$max}",
                    ], 422));
                }
            }

            if (!empty($validation['greater_than_field'])) {
                $otherKey = $validation['greater_than_field'];
                $otherValue = $data[$otherKey] ?? null;
                $otherField = $fieldsByKey[$otherKey] ?? null;
                $otherLabel = $otherField['label'] ?? $otherKey;

                if ($otherValue !== null && $otherValue !== '' && is_numeric($otherValue)) {
                    if ($number <= (float)$otherValue) {
                        abort(response()->json([
                            'success' => false,
                            'message' => "Поле «{$label}» должно быть больше поля «{$otherLabel}»",
                        ], 422));
                    }
                }
            }

            if (!empty($validation['less_than_field'])) {
                $otherKey = $validation['less_than_field'];
                $otherValue = $data[$otherKey] ?? null;
                $otherField = $fieldsByKey[$otherKey] ?? null;
                $otherLabel = $otherField['label'] ?? $otherKey;

                if ($otherValue !== null && $otherValue !== '' && is_numeric($otherValue)) {
                    if ($number >= (float)$otherValue) {
                        abort(response()->json([
                            'success' => false,
                            'message' => "Поле «{$label}» должно быть меньше поля «{$otherLabel}»",
                        ], 422));
                    }
                }
            }
        }
    }
    public function comments(JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanViewEntry($entry);

        $comments = $entry->rootComments()
            ->with(['user', 'editor', 'replies'])
            ->get();

        return response()->json([
            'success' => true,
            'comments' => $comments,
        ]);
    }

    public function storeComment(Request $request, JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanViewEntry($entry);

        $request->validate([
            'comment' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:journal_entry_comments,id',
        ]);

        if ($request->filled('parent_id')) {
            $parent = JournalEntryComment::findOrFail($request->parent_id);

            if ((int)$parent->journal_entry_id !== (int)$entry->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя ответить на комментарий другой записи',
                ], 422);
            }
        }

        $comment = JournalEntryComment::create([
            'journal_entry_id' => $entry->id,
            'parent_id' => $request->parent_id,
            'user_id' => session('user_id'),
            'comment' => $request->comment,
        ]);
        $notifyUserIds = collect();

        if ($entry->user_id) {
            $notifyUserIds->push($entry->user_id);
        }

        $entry->comments()
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->each(function ($id) use ($notifyUserIds) {
                $notifyUserIds->push($id);
            });

        if ($request->filled('parent_id')) {
            $parent = \App\Models\JournalEntryComment::find($request->parent_id);

            if ($parent && $parent->user_id) {
                $notifyUserIds->push($parent->user_id);
            }
        }

        $notifyUserIds = $notifyUserIds
            ->unique()
            ->filter(function ($id) {
                return (int)$id !== (int)session('user_id');
            });

        foreach ($notifyUserIds as $userId) {
            $this->createNotification(
                $userId,
                'Новый комментарий к записи',
                session('user_name') . ': ' . $request->comment,
                'info',
                $this->entryUrl($entry)
            );
        }
        $this->writeEntryLog(
            $entry,
            'comment_added',
            null,
            null,
            null,
            null,
            'Добавлен комментарий: ' . $request->comment,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Комментарий добавлен',
            'comment' => $comment->load(['user', 'editor', 'replies']),
        ]);
    }

    public function updateComment(Request $request, JournalTemplate $journal, JournalEntry $entry, JournalEntryComment $comment)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanViewEntry($entry);
        $this->checkCommentBelongsToEntry($entry, $comment);
        $this->checkCanEditComment($entry, $comment);

        $request->validate([
            'comment' => 'required|string|max:5000',
        ]);

        $oldComment = $comment->comment;

        $comment->update([
            'comment' => $request->comment,
            'edited_at' => now(),
            'edited_by' => session('user_id'),
        ]);
        if ((int)$comment->user_id !== (int)session('user_id')) {
            $this->createNotification(
                $comment->user_id,
                'Комментарий изменён',
                'Ваш комментарий был изменён пользователем ' . session('user_name') . '.',
                'warning',
                $this->entryUrl($entry)
            );
        }
        $this->writeEntryLog(
            $entry,
            'comment_updated',
            null,
            null,
            null,
            null,
            "Комментарий изменён. Было: {$oldComment}. Стало: {$request->comment}",
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Комментарий обновлён',
            'comment' => $comment->load(['user', 'editor', 'replies']),
        ]);
    }

    private function checkCanViewEntry(JournalEntry $entry): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');

        if ($role === 'worker') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Нет доступа к записи другого подразделения');
            }

            if ((int)$entry->user_id !== (int)$userId) {
                abort(403, 'Работник видит только свои записи');
            }

            return;
        }

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер видит только записи своего подразделения');
            }

            return;
        }

        if ($role === 'admin') {
            return;
        }

        abort(403, 'Нет доступа');
    }

    private function checkCommentBelongsToEntry(JournalEntry $entry, JournalEntryComment $comment): void
    {
        if ((int)$comment->journal_entry_id !== (int)$entry->id) {
            abort(404);
        }
    }

    private function checkCanEditComment(JournalEntry $entry, JournalEntryComment $comment): void
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');

        if ($role === 'worker') {
            if ((int)$comment->user_id !== (int)$userId) {
                abort(403, 'Работник может редактировать только свои комментарии');
            }

            if ((int)$entry->user_id !== (int)$userId) {
                abort(403, 'Нет доступа к этой записи');
            }

            return;
        }

        if ($role === 'foreman') {
            if ((int)$entry->division_id !== (int)$divisionId) {
                abort(403, 'Мастер может редактировать комментарии только в своём подразделении');
            }

            return;
        }

        if ($role === 'admin') {
            return;
        }

        abort(403, 'Нет доступа');
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

    private function notifyUsers(
        $users,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $url = null,
        ?int $excludeUserId = null
    ): void {
        foreach ($users as $user) {
            if ($excludeUserId && (int)$user->id === (int)$excludeUserId) {
                continue;
            }

            $this->createNotification(
                $user->id,
                $title,
                $message,
                $type,
                $url
            );
        }
    }

    private function getForemenAndAdminsForDivision(?int $divisionId)
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', ['foreman', 'admin'])
            ->where('division_id', $divisionId)
            ->get();
    }

    private function entryUrl(JournalEntry $entry): string
    {
        return route('user.journals.show', $entry->journal_template_id);
    }
}
