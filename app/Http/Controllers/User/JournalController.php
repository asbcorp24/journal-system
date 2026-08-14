<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalEntry;
use App\Models\JournalPrintTemplate;
use App\Models\JournalTemplate;
use App\Models\SavedFilter;
use App\Models\User;
use App\Support\DirectorySchema;
use App\Support\DivisionTree;
use App\Support\UserJournalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\JournalEntryComment;
use App\Models\JournalEntryLog;
use App\Models\Notification;
class JournalController extends Controller
{
    private function managedDivisionIds(): array
    {
        return DivisionTree::managedDivisionIds(session('user_division_id'), session('user_role'));
    }

    private function canAccessDivision(?int $divisionId): bool
    {
        return $divisionId !== null && in_array((int) $divisionId, $this->managedDivisionIds(), true);
    }

    private function journalAccess(JournalTemplate $journal): array
    {
        return UserJournalAccess::resolveForJournal(
            $journal,
            session('user_id'),
            session('user_role'),
            session('user_division_id')
        );
    }

    private function hasExplicitJournalAccessForDivision(array $access, int $divisionId): bool
    {
        return (bool) ($access['access_by_division'][$divisionId]['explicit'] ?? false);
    }

    private function hasFullJournalAccessForDivision(array $access, int $divisionId): bool
    {
        return in_array($divisionId, $access['full_division_ids'] ?? [], true);
    }

    private function canManageJournalDivision(JournalTemplate $journal, ?int $divisionId): bool
    {
        if ($divisionId === null) {
            return false;
        }

        $access = $this->journalAccess($journal);

        return $this->hasFullJournalAccessForDivision($access, (int) $divisionId);
    }

    private function defaultWritableDivisionId(array $access): ?int
    {
        $ownDivisionId = session('user_division_id');

        if ($ownDivisionId !== null && in_array((int) $ownDivisionId, $access['full_division_ids'], true)) {
            return (int) $ownDivisionId;
        }

        return $access['full_division_ids'][0] ?? null;
    }

    private function applyJournalEntryVisibilityScope($query, JournalTemplate $journal, array $access, $requestedDivisionId = null): void
    {
        $role = session('user_role');
        $accessibleDivisionIds = $access['division_ids'];

        if ($requestedDivisionId !== null && $requestedDivisionId !== '' && in_array((int) $requestedDivisionId, $accessibleDivisionIds, true)) {
            $accessibleDivisionIds = [(int) $requestedDivisionId];
        }

        if ($role === 'admin' || $role === 'foreman') {
            $query->whereIn('division_id', $accessibleDivisionIds);
            return;
        }

        if ($role !== 'worker') {
            $query->whereIn('division_id', $accessibleDivisionIds);
            return;
        }

        $query->whereIn('division_id', $accessibleDivisionIds);
    }

    private function isEntryEditable(JournalTemplate $journal, JournalEntry $entry): bool
    {
        try {
            $this->checkEntryBelongsToJournal($journal, $entry);
            $this->checkCanUpdateEntry($entry);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isEntryDeletable(JournalTemplate $journal, JournalEntry $entry): bool
    {
        try {
            $this->checkEntryBelongsToJournal($journal, $entry);
            $this->checkCanDeleteEntry($entry);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function canChangeEntryStatus(JournalTemplate $journal, JournalEntry $entry): bool
    {
        try {
            $this->checkEntryBelongsToJournal($journal, $entry);
            $this->checkCanChangeStatus($entry);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function canRestoreEntry(JournalTemplate $journal, JournalEntry $entry): bool
    {
        if (!method_exists($entry, 'trashed') || !$entry->trashed()) {
            return false;
        }

        if (session('user_role') !== 'admin') {
            return false;
        }

        $access = $this->journalAccess($journal);

        return $this->hasFullJournalAccessForDivision($access, (int) $entry->division_id);
    }

    private function canWriteEntry(JournalTemplate $journal, JournalEntry $entry): bool
    {
        return $this->isEntryEditable($journal, $entry) || $this->canChangeEntryStatus($journal, $entry);
    }

    private function resolveEntryJournal(JournalEntry $entry): JournalTemplate
    {
        if ($entry->relationLoaded('template') && $entry->template) {
            $entry->template->loadMissing('divisions');
            return $entry->template;
        }

        return JournalTemplate::with('divisions')->findOrFail($entry->journal_template_id);
    }

    private function getDirectoryDisplayValue(DirectoryValue $directoryValue, array $field): string
    {
        $displayField = $field['directory_display_field'] ?? null;

        if ($displayField && is_array($directoryValue->data ?? null)) {
            $value = $directoryValue->data[$displayField] ?? null;

            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        return (string)$directoryValue->value;
    }

    private function applySchemaFilters($query, Request $request, array $schema): void
    {
        foreach ($schema as $field) {
            if (empty($field['filterable'])) {
                continue;
            }

            $key = $field['key'] ?? null;
            $type = $field['type'] ?? 'string';

            if (!$key) {
                continue;
            }

            $filterPayload = $request->input("field_filters.{$key}");
            [$operator, $filterValue, $filterValueTo] = $this->normalizeAdvancedFilterPayload($filterPayload);

            if ($filterValue === null || $filterValue === '') {
                continue;
            }

            $jsonPath = '$.' . $key;

            if (in_array($type, ['number', 'directory'], true) && is_numeric($filterValue)) {
                $this->applyNumericJsonFilter($query, $jsonPath, $operator, $filterValue, $filterValueTo);
                continue;
            }

            if (in_array($type, ['date', 'time', 'list', 'directory_text'], true)) {
                $this->applyTextJsonFilter($query, $jsonPath, $operator, $filterValue, $filterValueTo, false);
                continue;
            }

            $this->applyTextJsonFilter($query, $jsonPath, $operator, $filterValue, $filterValueTo, true);
        }
    }

    private function normalizeAdvancedFilterPayload($payload): array
    {
        if (!is_array($payload)) {
            $value = trim((string) $payload);
            return ['eq', $value, null];
        }

        $operator = trim((string) ($payload['operator'] ?? 'eq'));
        $value = trim((string) ($payload['value'] ?? ''));
        $valueTo = trim((string) ($payload['value_to'] ?? ''));

        if (!in_array($operator, ['eq', 'gt', 'lt', 'neq', 'between', 'contains'], true)) {
            $operator = 'eq';
        }

        return [$operator, $value, $valueTo];
    }

    private function applyNumericJsonFilter($query, string $jsonPath, string $operator, $value, $valueTo = null): void
    {
        $column = 'CAST(json_extract(data, ?) AS NUMERIC)';

        if ($operator === 'gt') {
            $query->whereRaw($column . ' > ?', [$jsonPath, $value + 0]);
            return;
        }

        if ($operator === 'lt') {
            $query->whereRaw($column . ' < ?', [$jsonPath, $value + 0]);
            return;
        }

        if ($operator === 'neq') {
            $query->whereRaw($column . ' != ?', [$jsonPath, $value + 0]);
            return;
        }

        if ($operator === 'between' && $valueTo !== null && $valueTo !== '' && is_numeric($valueTo)) {
            $query->whereRaw($column . ' BETWEEN ? AND ?', [$jsonPath, $value + 0, $valueTo + 0]);
            return;
        }

        $query->whereRaw($column . ' = ?', [$jsonPath, $value + 0]);
    }

    private function applyTextJsonFilter($query, string $jsonPath, string $operator, string $value, ?string $valueTo = null, bool $supportsContains = false): void
    {
        if ($operator === 'contains' && $supportsContains) {
            $query->whereRaw('LOWER(COALESCE(json_extract(data, ?), \'\')) LIKE ?', [
                $jsonPath,
                '%' . mb_strtolower($value) . '%',
            ]);
            return;
        }

        if ($operator === 'gt') {
            $query->whereRaw('json_extract(data, ?) > ?', [$jsonPath, $value]);
            return;
        }

        if ($operator === 'lt') {
            $query->whereRaw('json_extract(data, ?) < ?', [$jsonPath, $value]);
            return;
        }

        if ($operator === 'neq') {
            $query->whereRaw('json_extract(data, ?) != ?', [$jsonPath, $value]);
            return;
        }

        if ($operator === 'between' && $valueTo !== null && $valueTo !== '') {
            $query->whereRaw('json_extract(data, ?) BETWEEN ? AND ?', [$jsonPath, $value, $valueTo]);
            return;
        }

        if ($supportsContains) {
            $query->whereRaw('LOWER(COALESCE(json_extract(data, ?), \'\')) LIKE ?', [
                $jsonPath,
                '%' . mb_strtolower($value) . '%',
            ]);
            return;
        }

        $query->whereRaw('json_extract(data, ?) = ?', [$jsonPath, $value]);
    }

    public function show(JournalTemplate $journal)
    {
        $access = $this->checkJournalAccess($journal);

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

        $directories = Directory::whereIn('id', $directoryIds)
            ->get()
            ->keyBy('id');

        $directoryValues = DirectoryValue::whereIn('directory_id', $directoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');

        $qrDirectoryIds = $directories
            ->filter(function (Directory $directory) {
                return collect($directory->schema ?? [])->contains(function ($field) {
                    return ($field['type'] ?? null) === 'qr';
                });
            })
            ->keys()
            ->values();

        $directoryQrValues = DirectoryValue::whereIn('directory_id', $qrDirectoryIds)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');

        $divisions = Division::whereIn('id', $access['division_ids'])->orderBy('name')->get();
        $entryDivisions = Division::whereIn('id', $access['full_division_ids'])->orderBy('name')->get();
        $canManageJournal = !empty($access['full_division_ids']);
        $canShowDeleted = session('user_role') === 'admin';
        $showDivisionFilter = count($access['division_ids']) > 1;
        $showEntryDivisionSelector = count($access['full_division_ids']) > 1
            || (
                count($access['full_division_ids']) === 1
                && (int) ($access['full_division_ids'][0] ?? 0) !== (int) session('user_division_id')
            );
        $printTemplates = JournalPrintTemplate::where('journal_template_id', $journal->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $savedFilters = SavedFilter::query()
            ->where('user_id', (int) session('user_id'))
            ->where('entity_type', SavedFilter::ENTITY_JOURNAL)
            ->where('entity_id', $journal->id)
            ->orderBy('name')
            ->get()
            ->map(function (SavedFilter $filter) {
                return [
                    'id' => $filter->id,
                    'name' => $filter->name,
                    'description' => $filter->description,
                    'visible_fields' => $filter->visible_fields ?? [],
                    'values' => $filter->values ?? [],
                ];
            })
            ->values()
            ->all();

        return view('user.journals.show', compact(
            'journal',
            'schema',
            'directoryValues',
            'directoryQrValues',
            'directories',
            'divisions',
            'entryDivisions',
            'canManageJournal',
            'canShowDeleted',
            'showDivisionFilter',
            'showEntryDivisionSelector',
            'printTemplates',
            'savedFilters'
        ));
    }
    public function print(Request $request, JournalTemplate $journal)
    {
        $access = $this->checkJournalAccess($journal);
        $schema = $journal->schema ?? [];
        $entries = $this->buildJournalEntriesQuery($request, $journal, $access, [
            'user',
            'division',
            'checker',
            'lastComment.user',
        ], 'print')->get();

        $directoryValues = $this->getDirectoryValuesForSchema($schema);
        $printTemplate = $this->resolvePrintTemplate($request, $journal);
        $printColumns = $this->resolvePrintColumns($journal, $printTemplate);
        $printSettings = $printTemplate->settings ?? [
            'orientation' => 'landscape',
            'show_signatures' => true,
        ];
        $printHtmlEntries = $this->renderPrintHtmlEntries(
            $journal,
            $schema,
            $entries,
            $directoryValues,
            $printTemplate
        );

        return view('user.journals.print', compact(
            'journal',
            'schema',
            'entries',
            'directoryValues',
            'printTemplate',
            'printColumns',
            'printSettings',
            'printHtmlEntries'
        ));
    }

    public function export(Request $request, JournalTemplate $journal, string $format)
    {
        $access = $this->checkJournalAccess($journal);
        $format = mb_strtolower(trim($format));

        if (!in_array($format, ['csv', 'xml'], true)) {
            abort(404);
        }

        $schema = $journal->schema ?? [];
        $entries = $this->buildJournalEntriesQuery($request, $journal, $access, [
            'user',
            'division',
        ], 'export')->get();

        if ($format === 'csv') {
            return $this->exportJournalCsv($journal, $schema, $entries);
        }

        return $this->exportJournalXml($journal, $schema, $entries);
    }

    public function import(Request $request, JournalTemplate $journal, string $format)
    {
        $access = $this->checkJournalAccess($journal);

        if (empty($access['full_division_ids'])) {
            abort(403, 'Нет прав на импорт в этот журнал');
        }

        $format = mb_strtolower(trim($format));

        if (!in_array($format, ['csv', 'xml'], true)) {
            abort(404);
        }

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:' . ($format === 'csv' ? 'csv,txt' : 'xml,txt')],
        ]);

        $rows = $format === 'csv'
            ? $this->readJournalCsvRows($request)
            : $this->readJournalXmlRows($request);

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'import_file' => ['Файл импорта пустой'],
            ]);
        }

        $created = 0;
        DB::transaction(function () use ($rows, $journal, $access, &$created) {
            foreach ($rows as $index => $row) {
                $divisionId = $this->resolveImportDivisionId($journal, $access, $row);
                $requestData = new Request([
                    'data' => $this->extractImportDataRow($journal->schema ?? [], $row),
                    'entry_date' => $row['entry_date'] ?? null,
                    'division_id' => $divisionId,
                ]);

                $validatedData = $this->validateEntryData($requestData, $journal, [], $divisionId, (int) session('user_id'));
                $entryDate = $this->detectEntryDate($validatedData, $requestData);

                JournalEntry::create([
                    'journal_template_id' => $journal->id,
                    'division_id' => $divisionId,
                    'user_id' => session('user_id'),
                    'entry_date' => $entryDate,
                    'data' => $validatedData,
                    'status' => 'submitted',
                ]);

                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Импорт завершён. Добавлено записей: {$created}",
        ]);
    }

    private function buildJournalEntriesQuery(Request $request, JournalTemplate $journal, array $access, array $with = [], string $mode = 'list')
    {
        $query = JournalEntry::with($with)
            ->where('journal_template_id', $journal->id);

        if ($mode === 'list') {
            $query->orderByDesc('id');
        } else {
            $query->orderByDesc('entry_date')->orderByDesc('id');
        }

        if ($request->boolean('show_deleted')) {
            $query->onlyTrashed();
        }

        $this->applyJournalEntryVisibilityScope($query, $journal, $access, $request->input('division_id'));

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

        $this->applySchemaFilters($query, $request, $journal->schema ?? []);

        return $query;
    }

    private function exportJournalCsv(JournalTemplate $journal, array $schema, $entries)
    {
        $fileName = 'journal_' . ($journal->code ?: $journal->id) . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($schema, $entries) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->journalExportHeaders($schema), ';');

            foreach ($entries as $entry) {
                fputcsv($handle, $this->journalExportRow($schema, $entry), ';');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportJournalXml(JournalTemplate $journal, array $schema, $entries)
    {
        $fileName = 'journal_' . ($journal->code ?: $journal->id) . '_' . now()->format('Ymd_His') . '.xml';

        return response()->streamDownload(function () use ($journal, $schema, $entries) {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><journal_export/>');
            $xml->addChild('journal_code', htmlspecialchars((string) ($journal->code ?: '')));
            $xml->addChild('journal_name', htmlspecialchars((string) $journal->name));
            $rowsNode = $xml->addChild('entries');

            foreach ($entries as $entry) {
                $entryNode = $rowsNode->addChild('entry');
                foreach ($this->journalExportRowMap($schema, $entry) as $key => $value) {
                    $entryNode->addChild($this->xmlSafeNodeName($key), htmlspecialchars((string) $value));
                }
            }

            echo $xml->asXML();
        }, $fileName, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function journalExportHeaders(array $schema): array
    {
        return array_keys($this->journalExportBaseRow($schema));
    }

    private function journalExportRow(array $schema, JournalEntry $entry): array
    {
        return array_values($this->journalExportRowMap($schema, $entry));
    }

    private function journalExportRowMap(array $schema, JournalEntry $entry): array
    {
        $row = $this->journalExportBaseRow($schema);
        $row['entry_date'] = $entry->entry_date ? $entry->entry_date->format('Y-m-d') : '';
        $row['division_id'] = (string) ($entry->division_id ?? '');
        $row['division_name'] = (string) ($entry->division->name ?? '');
        $row['status'] = (string) ($entry->status ?? '');

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            $row[$key] = $entry->data[$key] ?? '';
        }

        return $row;
    }

    private function journalExportBaseRow(array $schema): array
    {
        $row = [
            'entry_date' => '',
            'division_id' => '',
            'division_name' => '',
            'status' => '',
        ];

        foreach ($schema as $field) {
            if (!empty($field['key'])) {
                $row[$field['key']] = '';
            }
        }

        return $row;
    }

    private function readJournalCsvRows(Request $request): array
    {
        $file = $request->file('import_file');
        $path = $file?->getRealPath();

        if (!$path || !file_exists($path)) {
            throw ValidationException::withMessages([
                'import_file' => ['CSV-файл не найден'],
            ]);
        }

        $handle = fopen($path, 'r');

        if (!$handle) {
            throw ValidationException::withMessages([
                'import_file' => ['Не удалось открыть CSV-файл'],
            ]);
        }

        $header = null;
        $rows = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($header === null) {
                $header = array_map(fn ($item) => trim((string) $item), $row);
                continue;
            }

            if (count(array_filter($row, fn ($item) => trim((string) $item) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($row, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    private function readJournalXmlRows(Request $request): array
    {
        $file = $request->file('import_file');
        $path = $file?->getRealPath();

        if (!$path || !file_exists($path)) {
            throw ValidationException::withMessages([
                'import_file' => ['XML-файл не найден'],
            ]);
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);

        if (!$xml || !isset($xml->entries)) {
            throw ValidationException::withMessages([
                'import_file' => ['Не удалось прочитать XML-файл'],
            ]);
        }

        $rows = [];

        foreach ($xml->entries->entry as $entryNode) {
            $row = [];

            foreach ($entryNode->children() as $child) {
                $row[$child->getName()] = (string) $child;
            }

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function resolveImportDivisionId(JournalTemplate $journal, array $access, array $row): int
    {
        $defaultDivisionId = $this->defaultWritableDivisionId($access);

        if ($defaultDivisionId === null) {
            abort(403, 'Не удалось определить подразделение для импорта');
        }

        if (!empty($row['division_id']) && is_numeric($row['division_id'])) {
            $divisionId = (int) $row['division_id'];

            if ($this->canManageJournalDivision($journal, $divisionId)) {
                return $divisionId;
            }
        }

        $divisionName = trim((string) ($row['division_name'] ?? ''));

        if ($divisionName !== '') {
            $divisionId = Division::where('name', $divisionName)->value('id');
            if ($divisionId && $this->canManageJournalDivision($journal, (int) $divisionId)) {
                return (int) $divisionId;
            }
        }

        return (int) $defaultDivisionId;
    }

    private function extractImportDataRow(array $schema, array $row): array
    {
        $data = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            if (array_key_exists($key, $row)) {
                $data[$key] = $row[$key];
            }
        }

        return $data;
    }

    private function xmlSafeNodeName(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

        if ($safe === '' || preg_match('/^[0-9]/', $safe)) {
            $safe = 'field_' . $safe;
        }

        return $safe;
    }

    private function resolvePrintTemplate(Request $request, JournalTemplate $journal): ?JournalPrintTemplate
    {
        $templateId = $request->input('print_template_id');

        if (!$templateId) {
            return null;
        }

        return JournalPrintTemplate::where('journal_template_id', $journal->id)
            ->where('is_active', true)
            ->findOrFail($templateId);
    }

    private function resolvePrintColumns(JournalTemplate $journal, ?JournalPrintTemplate $printTemplate): array
    {
        $schema = $journal->schema ?? [];

        if ($printTemplate && !empty($printTemplate->settings['columns'])) {
            return $printTemplate->settings['columns'];
        }

        $columns = [
            ['type' => 'system', 'key' => 'number', 'label' => '№'],
            ['type' => 'system', 'key' => 'entry_date', 'label' => 'Дата'],
        ];

        foreach ($schema as $field) {
            if (!empty($field['key'])) {
                $columns[] = [
                    'type' => 'field',
                    'key' => $field['key'],
                    'label' => $field['label'] ?? $field['key'],
                ];
            }
        }

        return array_merge($columns, [
            ['type' => 'system', 'key' => 'created_by', 'label' => 'Добавил'],
            ['type' => 'system', 'key' => 'division', 'label' => 'Подразделение'],
            ['type' => 'system', 'key' => 'status', 'label' => 'Статус'],
            ['type' => 'system', 'key' => 'checked_by', 'label' => 'Проверил'],
            ['type' => 'system', 'key' => 'last_comment', 'label' => 'Комментарий'],
        ]);
    }

    private function renderPrintHtmlEntries(
        JournalTemplate $journal,
        array $schema,
        $entries,
        $directoryValues,
        ?JournalPrintTemplate $printTemplate
    ): array {
        $template = trim($printTemplate->settings['body_html'] ?? '');

        if ($template === '') {
            return [];
        }

        $template = $this->sanitizePrintTemplateHtml($template);
        $tableHtml = null;

        if (preg_match('/\{\{#entries\}\}(.*?)\{\{\/entries\}\}/s', $template)) {
            $html = preg_replace_callback('/\{\{#entries\}\}(.*?)\{\{\/entries\}\}/s', function ($matches) use ($journal, $schema, $entries, $directoryValues) {
                $entryTemplate = $matches[1] ?? '';
                $rowsHtml = '';

                foreach ($entries as $index => $entry) {
                    $values = $this->printTemplateValues($journal, $schema, $entry, $directoryValues, $index + 1);
                    $rowsHtml .= $this->replacePrintTemplateTokens($entryTemplate, $values);
                }

                return $rowsHtml;
            }, $template);

            return [
                $this->replacePrintTemplateTokens(
                    $html,
                    $this->globalPrintTemplateValues($journal),
                    $this->buildPrintEntriesTableHtml($journal, $schema, $entries, $directoryValues, $printTemplate)
                ),
            ];
        }

        if (preg_match('/\{\{\s*table\s*\}\}/', $template)) {
            $tableHtml = $this->buildPrintEntriesTableHtml($journal, $schema, $entries, $directoryValues, $printTemplate);

            return [
                $this->replacePrintTemplateTokens($template, $this->globalPrintTemplateValues($journal), $tableHtml),
            ];
        }

        $rendered = [];

        foreach ($entries as $index => $entry) {
            $values = $this->printTemplateValues($journal, $schema, $entry, $directoryValues, $index + 1);

            $rendered[] = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($matches) use ($values) {
                $key = $matches[1] ?? '';

                return e($values[$key] ?? '');
            }, $template);
        }

        return $rendered;
    }

    private function replacePrintTemplateTokens(string $template, array $values, ?string $tableHtml = null): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($matches) use ($values, $tableHtml) {
            $key = $matches[1] ?? '';

            if ($key === 'table') {
                return $tableHtml ?? '';
            }

            return e($values[$key] ?? '');
        }, $template);
    }

    private function globalPrintTemplateValues(JournalTemplate $journal): array
    {
        return [
            'journal.name' => $journal->name,
            'journal.description' => $journal->description ?? '',
            'print.date' => now()->format('d.m.Y H:i'),
        ];
    }

    private function buildPrintEntriesTableHtml(
        JournalTemplate $journal,
        array $schema,
        $entries,
        $directoryValues,
        ?JournalPrintTemplate $printTemplate
    ): string {
        $columns = $this->resolvePrintColumns($journal, $printTemplate);
        $html = '<table><thead><tr>';

        foreach ($columns as $column) {
            $html .= '<th>' . e($column['label'] ?? $column['key'] ?? 'Поле') . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        if ($entries->count() === 0) {
            $html .= '<tr><td colspan="' . max(1, count($columns)) . '" style="text-align:center;">Записи не найдены</td></tr>';
        }

        foreach ($entries as $index => $entry) {
            $html .= '<tr>';

            foreach ($columns as $column) {
                $html .= '<td>' . e($this->printColumnValue($column, $schema, $entry, $directoryValues, $index + 1)) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function printColumnValue(array $column, array $schema, JournalEntry $entry, $directoryValues, int $number): string
    {
        $columnType = $column['type'] ?? 'field';
        $columnKey = $column['key'] ?? null;

        if ($columnType === 'system') {
            if ($columnKey === 'number') {
                return (string) $number;
            }

            if ($columnKey === 'entry_date') {
                return $entry->entry_date ? $entry->entry_date->format('d.m.Y') : '';
            }

            if ($columnKey === 'created_by') {
                return $entry->user->name ?? '';
            }

            if ($columnKey === 'division') {
                return $entry->division->name ?? '';
            }

            if ($columnKey === 'status') {
                return $entry->status === 'approved'
                    ? 'Подтверждено'
                    : ($entry->status === 'rejected' ? 'Отклонено' : 'На проверке');
            }

            if ($columnKey === 'checked_by') {
                return $entry->checker->name ?? '';
            }

            if ($columnKey === 'last_comment') {
                return $entry->lastComment ? $entry->lastComment->comment : '';
            }

            return '';
        }

        $field = collect($schema)->firstWhere('key', $columnKey) ?? [];

        return $this->formatPrintFieldValue($field, $entry, $directoryValues);
    }

    private function printTemplateValues(JournalTemplate $journal, array $schema, JournalEntry $entry, $directoryValues, int $number): array
    {
        $values = [
            'entry.number' => $number,
            'entry.date' => $entry->entry_date ? $entry->entry_date->format('d.m.Y') : '',
            'entry.created_by' => $entry->user->name ?? '',
            'entry.division' => $entry->division->name ?? '',
            'entry.status' => $entry->status === 'approved'
                ? 'Подтверждено'
                : ($entry->status === 'rejected' ? 'Отклонено' : 'На проверке'),
            'entry.checked_by' => $entry->checker->name ?? '',
            'entry.comment' => $entry->lastComment ? $entry->lastComment->comment : '',
            'journal.name' => $journal->name,
            'journal.description' => $journal->description ?? '',
            'print.date' => now()->format('d.m.Y H:i'),
        ];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            $values[$key] = $this->formatPrintFieldValue($field, $entry, $directoryValues);
            $values['fields.' . $key] = $values[$key];
        }

        return $values;
    }

    private function formatPrintFieldValue(array $field, JournalEntry $entry, $directoryValues): string
    {
        $key = $field['key'] ?? null;
        $type = $field['type'] ?? 'string';
        $value = $key && is_array($entry->data) ? ($entry->data[$key] ?? null) : null;

        if ($value === null || $value === '') {
            return '';
        }

        if ($type === 'directory') {
            $list = $directoryValues[$field['directory_id'] ?? 0] ?? collect();
            $directoryItem = $list->firstWhere('id', (int) $value);
            $displayField = $field['directory_display_field'] ?? null;

            if ($directoryItem) {
                return ($displayField && !empty($directoryItem->data[$displayField]))
                    ? (string) $directoryItem->data[$displayField]
                    : (string) $directoryItem->value;
            }

            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function sanitizePrintTemplateHtml(string $html): string
    {
        $allowedTags = '<div><section><article><header><footer><main><p><br><span><strong><b><em><i><u><small><h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><ul><ol><li><hr>';
        $html = strip_tags($html, $allowedTags);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s(?:href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);

        return $html;
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
        $journal = $this->resolveEntryJournal($entry);
        $access = $this->journalAccess($journal);

        if ($this->hasFullJournalAccessForDivision($access, (int) $entry->division_id)
            && $this->hasExplicitJournalAccessForDivision($access, (int) $entry->division_id)) {
            return;
        }

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
            if (!$this->canAccessDivision((int) $entry->division_id)) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
            }

            return;
        }

        abort(403, 'Нет доступа');
    }

    private function checkCanDeleteEntry(JournalEntry $entry): void
    {
        $journal = $this->resolveEntryJournal($entry);
        $access = $this->journalAccess($journal);

        if ($this->hasFullJournalAccessForDivision($access, (int) $entry->division_id)
            && $this->hasExplicitJournalAccessForDivision($access, (int) $entry->division_id)) {
            return;
        }

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
            if (!$this->canAccessDivision((int) $entry->division_id)) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
            }

            return;
        }

        abort(403, 'Нет доступа');
    }
    public function list(Request $request, JournalTemplate $journal)
    {
        $access = $this->checkJournalAccess($journal);

        $query = $this->buildJournalEntriesQuery($request, $journal, $access, [
            'user',
            'division',
            'checker',
            'lastComment.user',
            'rootComments',
        ], 'list');

        $entries = $query->paginate(10);
        $entries->getCollection()->transform(function (JournalEntry $entry) use ($journal) {
            $entry->can_edit = $this->isEntryEditable($journal, $entry);
            $entry->can_delete = $this->isEntryDeletable($journal, $entry);
            $entry->can_change_status = $this->canChangeEntryStatus($journal, $entry);
            $entry->can_restore = $this->canRestoreEntry($journal, $entry);
            $entry->can_comment = $this->canWriteEntry($journal, $entry);

            return $entry;
        });

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
        $access = $this->checkJournalAccess($journal);

        if (empty($access['full_division_ids'])) {
            abort(403, 'Нет прав на заполнение этого журнала');
        }

        $divisionId = $this->defaultWritableDivisionId($access);

        if ($request->filled('division_id') && $this->canManageJournalDivision($journal, (int) $request->division_id)) {
            $divisionId = (int) $request->division_id;
        }

        if ($divisionId === null) {
            abort(403, 'Не удалось определить подразделение для записи');
        }

        $validatedData = $this->validateEntryData($request, $journal, [], $divisionId, (int) session('user_id'));

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

        $role = session('user_role');

        $divisionId = $entry->division_id;

        if ($role === 'admin' && $request->filled('division_id') && $this->canAccessDivision((int) $request->division_id)) {
            $divisionId = $request->division_id;
        }

        $validatedData = $this->validateEntryData($request, $journal, $entry->data ?? [], (int) $divisionId, (int) $entry->user_id);

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
        $journal = $this->resolveEntryJournal($entry);
        $access = $this->journalAccess($journal);

        if ($this->hasFullJournalAccessForDivision($access, (int) $entry->division_id)
            && $this->hasExplicitJournalAccessForDivision($access, (int) $entry->division_id)) {
            return;
        }

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
            if (!$this->canAccessDivision((int) $entry->division_id)) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
            }

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

        $oldData = $entry->data;
        $oldStatus = $entry->status;

        $entry->delete();

        $this->writeEntryLog(
            $entry,
            'deleted',
            $oldStatus,
            $oldStatus,
            $oldData,
            $oldData,
            null,
            request()
        );

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена',
        ]);
    }
    public function restore(JournalTemplate $journal, JournalEntry $entry)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);

        if (session('user_role') !== 'admin') {
            abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
        }

        if (!$this->canAccessDivision((int) $entry->division_id)) {
            abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
        }

        if (!$entry->trashed()) {
            return response()->json([
                'success' => true,
                'message' => 'Запись уже активна',
            ]);
        }

        $oldData = $entry->data;
        $oldStatus = $entry->status;

        $entry->restore();

        $this->writeEntryLog(
            $entry,
            'restored',
            $oldStatus,
            $oldStatus,
            $oldData,
            $oldData,
            null,
            request()
        );

        return response()->json([
            'success' => true,
            'message' => 'Запись восстановлена',
        ]);
    }

    public function recalculateSqlField(Request $request, JournalTemplate $journal, JournalEntry $entry, string $fieldKey)
    {
        $this->checkJournalAccess($journal);
        $this->checkEntryBelongsToJournal($journal, $entry);
        $this->checkCanUpdateEntry($entry);

        $schema = $journal->schema ?? [];
        $field = collect($schema)->first(function ($schemaField) use ($fieldKey) {
            return ($schemaField['key'] ?? null) === $fieldKey
                && ($schemaField['type'] ?? null) === 'sql';
        });

        if (!$field) {
            abort(404, 'SQL-поле не найдено');
        }

        $data = is_array($entry->data) ? $entry->data : [];
        $oldData = $data;
        $value = $this->executeSqlFieldQuery($field, $data, $journal, $entry);
        $data[$fieldKey] = $value;

        $entry->update([
            'data' => $data,
        ]);

        $this->writeEntryLog(
            $entry,
            'sql_recalculate',
            null,
            null,
            $oldData,
            $data,
            "Пересчитано SQL-поле «" . ($field['label'] ?? $fieldKey) . "»",
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Поле пересчитано',
            'value' => $value,
            'entry' => $entry->fresh(),
        ]);
    }

    public function storeDirectoryValue(Request $request, JournalTemplate $journal, Directory $directory)
    {
        $this->checkJournalAccess($journal);
        $this->checkCanManageDirectoryValues($journal, $directory);

        $schema = $directory->schema ?? [];

        if (empty($schema)) {
            $validated = $request->validate([
                'value' => ['required', 'string', 'max:255'],
            ]);

            $value = trim($validated['value']);
            $recordData = null;
        } else {
            $recordData = $request->input('data');

            if (!is_array($recordData) && $request->filled('value')) {
                $recordData = DirectorySchema::buildDataFromLegacyValue($schema, trim((string)$request->input('value')));
            }

            $request->merge([
                'data' => $recordData,
            ]);

            $request->validate([
                'data' => ['required', 'array'],
            ]);

            $recordData = DirectorySchema::validateRecord($schema, $recordData);
            DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']));
            $value = DirectorySchema::resolveDisplayValue($schema, $recordData);
        }

        $existingValue = DirectoryValue::query()
            ->where('directory_id', $directory->id)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])
            ->first();

        if ($existingValue) {
            return response()->json([
                'success' => true,
                'message' => 'Р—РЅР°С‡РµРЅРёРµ СѓР¶Рµ СЃСѓС‰РµСЃС‚РІСѓРµС‚',
                'value' => $existingValue,
            ]);
        }

        $newValue = $directory->values()->create([
            'value' => $value,
            'data' => $recordData,
            'code' => trim((string) $request->input('code', '')) ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Р—РЅР°С‡РµРЅРёРµ РґРѕР±Р°РІР»РµРЅРѕ',
            'value' => $newValue,
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
    private function checkJournalAccess(JournalTemplate $journal): array
    {
        $access = $this->journalAccess($journal);

        if (!$journal->is_active) {
            abort(404);
        }

        if ($access['can_view']) {
            return $access;
        }

        if (!$journal->is_active) {
            abort(404);
        }

        if (session('user_role') === 'admin') {
            $hasAccess = $journal->divisions()
                ->whereIn('divisions.id', $this->managedDivisionIds())
                ->exists();

            if (!$hasAccess) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР° Рє СЌС‚РѕРјСѓ Р¶СѓСЂРЅР°Р»Сѓ');
            }

            return $access;
        }

        $divisionId = session('user_division_id');

        $hasAccess = $journal->divisions()
            ->where('divisions.id', $divisionId)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'Нет доступа к этому журналу');
        }

        return $access;
    }

    private function checkEntryBelongsToJournal(JournalTemplate $journal, JournalEntry $entry): void
    {
        if ((int)$entry->journal_template_id !== (int)$journal->id) {
            abort(404);
        }
    }

    private function checkCanManageDirectoryValues(JournalTemplate $journal, Directory $directory): void
    {
        $role = session('user_role');

        if ($role === 'worker') {
            abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
        }

        $directoryIds = collect($journal->schema ?? [])
            ->filter(function ($field) {
                return in_array($field['type'] ?? '', ['directory', 'directory_text'], true)
                    && !empty($field['directory_id']);
            })
            ->pluck('directory_id')
            ->map(function ($id) {
                return (int)$id;
            })
            ->unique()
            ->values();

        if (!$directoryIds->contains((int)$directory->id)) {
            abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР° Рє СЌС‚РѕРјСѓ СЃРїСЂР°РІРѕС‡РЅРёРєСѓ');
        }

        $divisionId = session('user_division_id');
        $managedDivisionIds = $this->managedDivisionIds();

        if ($role === 'admin' && empty($managedDivisionIds)) {
            abort(403, 'Р СњР ВµРЎвЂљ Р Т‘Р С•РЎРѓРЎвЂљРЎС“Р С—Р В°');
        }

        $hasDivisionAccess = !$directory->divisions()->exists()
            || (!empty($managedDivisionIds)
                ? $directory->divisions()->whereIn('divisions.id', $managedDivisionIds)->exists()
                : $directory->divisions()->where('divisions.id', $divisionId)->exists());

        if (!$hasDivisionAccess) {
            abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР° Рє СЌС‚РѕРјСѓ СЃРїСЂР°РІРѕС‡РЅРёРєСѓ');
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
            if (!$this->canAccessDivision((int) $entry->division_id)) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
            }

            return;
        }

        abort(403, 'Нет доступа');
    }

    private function validateEntryData(
        Request $request,
        JournalTemplate $journal,
        array $existingData = [],
        ?int $divisionId = null,
        ?int $userId = null
    ): array
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

            if ($type === 'sql') {
                $result[$key] = $existingData[$key] ?? null;
                continue;
            }

            if ($type === 'hidden') {
                if (array_key_exists($key, $existingData) && $existingData[$key] !== null && $existingData[$key] !== '') {
                    $result[$key] = $existingData[$key];
                } else {
                    $result[$key] = $this->resolveHiddenDefaultValue($field);
                }

                if ($required && ($result[$key] === null || $result[$key] === '')) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Скрытое поле «{$label}» должно иметь значение по умолчанию",
                    ], 422));
                }

                continue;
            }

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

                $result[$key] = $this->getDirectoryDisplayValue($directoryValue, $field);
                continue;
            }

            if ($type === 'calc') {
                $result[$key] = null;
                continue;
            }

            $result[$key] = trim((string)$value);
        }
        $result = $this->calculateCalcFields($schema, $result);
        $result = $this->calculateMissingSqlFields($schema, $result, $journal, null, $divisionId, $userId);
        $this->validateRequiredSqlFields($schema, $result);
        // ВОТ ЗДЕСЬ ПРОВЕРЯЕМ ОГРАНИЧЕНИЯ ИЗ ШАБЛОНА ЖУРНАЛА
        $this->validateComparableNumericConstraints($schema, $result);

        return $result;
    }

    private function resolveHiddenDefaultValue(array $field): ?string
    {
        $value = trim((string) ($field['default_value'] ?? ''));

        return $value === '' ? null : $value;
    }

    private function calculateMissingSqlFields(
        array $schema,
        array $data,
        JournalTemplate $journal,
        ?JournalEntry $entry = null,
        ?int $divisionId = null,
        ?int $userId = null
    ): array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? '') !== 'sql') {
                continue;
            }

            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                continue;
            }

            $data[$key] = $this->executeSqlFieldQuery($field, $data, $journal, $entry, $divisionId, $userId);
        }

        return $data;
    }

    private function validateRequiredSqlFields(array $schema, array $data): void
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? '') !== 'sql' || empty($field['required'])) {
                continue;
            }

            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;
            $value = $key ? ($data[$key] ?? null) : null;

            if ($value === null || $value === '') {
                abort(response()->json([
                    'success' => false,
                    'message' => "SQL-поле «{$label}» не вернуло значение",
                ], 422));
            }
        }
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

    private function executeSqlFieldQuery(
        array $field,
        array $data,
        JournalTemplate $journal,
        ?JournalEntry $entry = null,
        ?int $divisionId = null,
        ?int $userId = null
    )
    {
        $label = $field['label'] ?? ($field['key'] ?? 'SQL');
        $sql = trim((string)($field['sql_query'] ?? ''));

        if ($sql === '') {
            abort(response()->json([
                'success' => false,
                'message' => "Для SQL-поля «{$label}» не задан запрос",
            ], 422));
        }

        if (!$this->isSafeSqlFieldQuery($sql)) {
            abort(response()->json([
                'success' => false,
                'message' => "SQL-поле «{$label}» должно содержать один SELECT-запрос без дополнительных команд",
            ], 422));
        }

        $sql = $this->normalizeSqlFieldTemplateCodeReferences($sql);
        $bindings = $this->buildSqlFieldBindings($sql, $data, $journal, $entry, $divisionId, $userId);

        try {
            $row = DB::selectOne($sql, $bindings);
        } catch (\Throwable $e) {
            abort(response()->json([
                'success' => false,
                'message' => "Ошибка выполнения SQL-поля «{$label}»: " . $e->getMessage(),
            ], 422));
        }

        if (!$row) {
            return null;
        }

        $values = array_values((array)$row);

        if (count($values) !== 1) {
            abort(response()->json([
                'success' => false,
                'message' => "SQL-поле «{$label}» должно возвращать ровно одну колонку",
            ], 422));
        }

        $value = $values[0];

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function isSafeSqlFieldQuery(string $sql): bool
    {
        $normalized = trim($sql);

        if (str_contains($normalized, ';')) {
            return false;
        }

        return (bool)preg_match('/^select\b/i', $normalized);
    }

    private function normalizeSqlFieldTemplateCodeReferences(string $sql): string
    {
        $normalized = preg_replace_callback(
            "/SELECT\\s+id\\s+FROM\\s+journal_templates\\s+WHERE\\s+code\\s*=\\s*'([^']+)'\\s+LIMIT\\s+1/i",
            function (array $matches) {
                $code = $matches[1] ?? '';

                if ($code === '' || JournalTemplate::query()->where('code', $code)->exists()) {
                    return $matches[0];
                }

                $importedCode = JournalTemplate::query()
                    ->where('code', 'like', $code . '_import%')
                    ->orderBy('id')
                    ->value('code');

                if (!$importedCode) {
                    return $matches[0];
                }

                return "SELECT id FROM journal_templates WHERE code = '" . str_replace("'", "''", $importedCode) . "' LIMIT 1";
            },
            $sql
        );

        return is_string($normalized) ? $normalized : $sql;
    }

    private function buildSqlFieldBindings(
        string $sql,
        array $data,
        JournalTemplate $journal,
        ?JournalEntry $entry = null,
        ?int $divisionId = null,
        ?int $userId = null
    ): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $parameterNames = array_values(array_unique($matches[1] ?? []));
        $systemValues = [
            'entry_id' => $entry?->id,
            'journal_id' => $journal->id,
            'division_id' => $entry?->division_id ?? $divisionId ?? request()->input('division_id') ?? session('user_division_id'),
            'user_id' => $entry?->user_id ?? $userId ?? session('user_id'),
        ];
        $bindings = [];

        foreach ($parameterNames as $name) {
            if (array_key_exists($name, $data)) {
                $bindings[$name] = $data[$name];
                continue;
            }

            if (array_key_exists($name, $systemValues)) {
                $bindings[$name] = $systemValues[$name];
                continue;
            }

            $bindings[$name] = null;
        }

        return $bindings;
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
    private function validateComparableNumericConstraints(array $schema, array $data): void
    {
        $fieldsByKey = collect($schema)->keyBy('key');
        $directoryValuesCache = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;
            $type = $field['type'] ?? 'string';

            if (!$key || !in_array($type, ['number', 'calc'], true)) {
                continue;
            }

            $validation = $field['validation'] ?? [];

            if (empty($validation)) {
                continue;
            }

            $number = $this->resolveComparableNumericConstraintValue($field, $data[$key] ?? null, $directoryValuesCache);

            if ($number === null) {
                continue;
            }

            if (isset($validation['min']) && $validation['min'] !== '' && $number < (float) $validation['min']) {
                abort(response()->json([
                    'success' => false,
                    'message' => "Поле «{$label}» должно быть не меньше {$validation['min']}",
                ], 422));
            }

            if (isset($validation['max']) && $validation['max'] !== '' && $number > (float) $validation['max']) {
                abort(response()->json([
                    'success' => false,
                    'message' => "Поле «{$label}» должно быть не больше {$validation['max']}",
                ], 422));
            }

            if (!empty($validation['greater_than_field'])) {
                $otherKey = $validation['greater_than_field'];
                $otherField = $fieldsByKey->get($otherKey);
                $otherLabel = $otherField['label'] ?? $otherKey;
                $otherNumber = $otherField
                    ? $this->resolveComparableNumericConstraintValue($otherField, $data[$otherKey] ?? null, $directoryValuesCache)
                    : null;

                if ($otherNumber !== null && $number <= $otherNumber) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Поле «{$label}» должно быть больше поля «{$otherLabel}»",
                    ], 422));
                }
            }

            if (!empty($validation['less_than_field'])) {
                $otherKey = $validation['less_than_field'];
                $otherField = $fieldsByKey->get($otherKey);
                $otherLabel = $otherField['label'] ?? $otherKey;
                $otherNumber = $otherField
                    ? $this->resolveComparableNumericConstraintValue($otherField, $data[$otherKey] ?? null, $directoryValuesCache)
                    : null;

                if ($otherNumber !== null && $number >= $otherNumber) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Поле «{$label}» должно быть меньше поля «{$otherLabel}»",
                    ], 422));
                }
            }
        }
    }

    private function resolveComparableNumericConstraintValue(array $field, $value, array &$directoryValuesCache): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (($field['type'] ?? 'string') !== 'directory') {
            return null;
        }

        $directoryId = (int) ($field['directory_id'] ?? 0);
        $selectedId = (int) $value;

        if ($directoryId <= 0 || $selectedId <= 0) {
            return null;
        }

        $cacheKey = $directoryId . ':' . $selectedId;

        if (!array_key_exists($cacheKey, $directoryValuesCache)) {
            $directoryValuesCache[$cacheKey] = DirectoryValue::query()
                ->whereKey($selectedId)
                ->where('directory_id', $directoryId)
                ->first();
        }

        $directoryValue = $directoryValuesCache[$cacheKey];

        if (!$directoryValue) {
            return null;
        }

        $displayValue = $this->getDirectoryDisplayValue($directoryValue, $field);

        if (is_numeric($displayValue)) {
            return (float) $displayValue;
        }

        if (is_numeric($directoryValue->value)) {
            return (float) $directoryValue->value;
        }

        return null;
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

        if (!$this->canWriteEntry($journal, $entry)) {
            abort(403, 'Нет прав на изменение этого журнала');
        }

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

        if (!$this->canWriteEntry($journal, $entry)) {
            abort(403, 'Нет прав на изменение этого журнала');
        }

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
        $journal = $this->resolveEntryJournal($entry);
        $access = $this->journalAccess($journal);
        $entryDivisionId = (int) $entry->division_id;

        if (!in_array($entryDivisionId, $access['division_ids'], true)) {
            abort(403, 'Нет доступа к записи этого подразделения');
        }

        if ($this->hasExplicitJournalAccessForDivision($access, $entryDivisionId)) {
            return;
        }

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
            if (!$this->canAccessDivision((int) $entry->division_id)) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
            }

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
        $journal = $this->resolveEntryJournal($entry);
        $access = $this->journalAccess($journal);

        if ($this->hasFullJournalAccessForDivision($access, (int) $entry->division_id)
            && $this->hasExplicitJournalAccessForDivision($access, (int) $entry->division_id)) {
            return;
        }

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
            if (!$this->canAccessDivision((int) $entry->division_id)) {
                abort(403, 'РќРµС‚ РґРѕСЃС‚СѓРїР°');
            }

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
        $ancestorDivisionIds = DivisionTree::ancestorAndSelfIds($divisionId);

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($divisionId, $ancestorDivisionIds) {
                $query->where(function ($roleQuery) use ($divisionId) {
                    $roleQuery->where('role', 'foreman')
                        ->where('division_id', $divisionId);
                });

                $query->orWhere(function ($roleQuery) use ($ancestorDivisionIds) {
                    $roleQuery->where('role', 'admin')
                        ->whereIn('division_id', $ancestorDivisionIds);
                });
            })
            ->get();
    }

    private function entryUrl(JournalEntry $entry): string
    {
        return route('user.journals.show', $entry->journal_template_id);
    }
}
