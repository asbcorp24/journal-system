<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\UserFavorite;
use App\Support\DirectorySchema;
use App\Support\DivisionTree;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectoryController extends Controller
{
    public function index()
    {
        $directories = $this->getAccessibleDirectoriesQuery()
            ->orderBy('name')
            ->get();

        $favoriteIds = UserFavorite::query()
            ->where('user_id', (int) session('user_id'))
            ->where('entity_type', UserFavorite::TYPE_DIRECTORY)
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $directories = $directories
            ->map(function (Directory $directory) use ($favoriteIds) {
                $directory->is_favorite = in_array((int) $directory->id, $favoriteIds, true);

                return $directory;
            })
            ->sortByDesc(function (Directory $directory) {
                return $directory->is_favorite ? 1 : 0;
            })
            ->values();

        return view('user.directories.index', compact('directories'));
    }

    public function list(Request $request)
    {
        $query = $this->getAccessibleDirectoriesQuery()->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $favoriteIds = UserFavorite::query()
            ->where('user_id', (int) session('user_id'))
            ->where('entity_type', UserFavorite::TYPE_DIRECTORY)
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'success' => true,
            'items' => $query->get()->map(function (Directory $directory) use ($favoriteIds) {
                return [
                    'id' => $directory->id,
                    'name' => $directory->name,
                    'code' => $directory->code,
                    'description' => $directory->description,
                    'schema' => $directory->schema ?? [],
                    'is_favorite' => in_array((int) $directory->id, $favoriteIds, true),
                ];
            })->sortByDesc('is_favorite')->values(),
        ]);
    }

    public function valuesList(Request $request, Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

        $schema = $directory->schema ?? [];
        $filters = $this->normalizeValueFilters($request->input('filters', []), $schema);
        $items = $this->buildDirectoryValuesCollection($request, $directory, $schema, $filters);

        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'directory' => [
                    'id' => $directory->id,
                    'name' => $directory->name,
                    'description' => $directory->description,
                    'schema' => $schema,
                ],
                'items' => $items->map(function (DirectoryValue $value) {
                    return $this->serializeValue($value);
                })->values(),
            ]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 10;
        $values = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page
        );

        return response()->json([
            'success' => true,
            'directory' => [
                'id' => $directory->id,
                'name' => $directory->name,
                'description' => $directory->description,
                'schema' => $schema,
            ],
            'items' => $values->getCollection()->map(function (DirectoryValue $value) {
                return $this->serializeValue($value);
            })->values(),
            'pagination' => [
                'current_page' => $values->currentPage(),
                'last_page' => $values->lastPage(),
                'per_page' => $values->perPage(),
                'total' => $values->total(),
                'from' => $values->firstItem(),
                'to' => $values->lastItem(),
            ],
        ]);
    }

    public function export(Request $request, Directory $directory, string $format)
    {
        $this->ensureDirectoryAccess($directory);

        $format = mb_strtolower(trim($format));

        if (!in_array($format, ['csv', 'xml'], true)) {
            abort(404);
        }

        $schema = $directory->schema ?? [];
        $filters = $this->normalizeValueFilters($request->input('filters', []), $schema);
        $items = $this->buildDirectoryValuesCollection($request, $directory, $schema, $filters);

        $referenceMap = $this->buildDirectoryExportReferenceMap($schema, $items);

        if ($format === 'csv') {
            return $this->exportDirectoryCsv($directory, $schema, $items, $referenceMap);
        }

        return $this->exportDirectoryXml($directory, $schema, $items, $referenceMap);
    }

    public function import(Request $request, Directory $directory, string $format)
    {
        $this->ensureDirectoryAccess($directory);
        $this->ensureCanManageValues();

        $format = mb_strtolower(trim($format));

        if (!in_array($format, ['csv', 'xml'], true)) {
            abort(404);
        }

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:' . ($format === 'csv' ? 'csv,txt' : 'xml,txt')],
        ]);

        $rows = $format === 'csv'
            ? $this->readDirectoryCsvRows($request)
            : $this->readDirectoryXmlRows($request);

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'import_file' => ['Файл импорта пустой'],
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($rows, $directory, &$created) {
            foreach ($rows as $index => $row) {
                $payload = $this->buildDirectoryImportPayload($directory, $row);
                $rowRequest = new Request($payload);
                [$recordData, $displayValue] = $this->validateDirectoryValuePayload($rowRequest, $directory);

                $validatedMeta = validator([
                    'code' => $row['code'] ?? null,
                    'sort_order' => $row['sort_order'] ?? 0,
                    'is_active' => $this->normalizeImportBoolean($row['is_active'] ?? true),
                ], [
                    'code' => ['nullable', 'string', 'max:255'],
                    'sort_order' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ])->validate();

                $directory->values()->create([
                    'value' => $displayValue,
                    'data' => $recordData,
                    'code' => $validatedMeta['code'] ?? null,
                    'sort_order' => $validatedMeta['sort_order'] ?? 0,
                    'is_active' => $validatedMeta['is_active'] ?? true,
                ]);

                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Импорт завершён. Добавлено записей: {$created}",
        ]);
    }

    public function print(Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

        $directory->load(['values' => function ($query) {
            $query->orderBy('sort_order')->orderBy('value');
        }]);

        return view('admin.directories.print', [
            'directory' => $directory,
            'values' => $directory->values,
            'schema' => $directory->schema ?? [],
        ]);
    }

    public function printBarcodes(Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

        $directory->load(['values' => function ($query) {
            $query->orderBy('sort_order')->orderBy('value');
        }]);

        $schema = $directory->schema ?? [];
        $qrField = collect($schema)->firstWhere('type', 'qr');

        return view('admin.directories.barcodes', [
            'directory' => $directory,
            'values' => $directory->values,
            'schema' => $schema,
            'qrField' => $qrField,
        ]);
    }

    public function storeValue(Request $request, Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);
        $this->ensureCanManageValues();

        [$recordData, $displayValue] = $this->validateDirectoryValuePayload($request, $directory);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $value = $directory->values()->create([
            'value' => $displayValue,
            'data' => $recordData,
            'code' => $validated['code'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Значение добавлено',
            'value' => $this->serializeValue($value),
        ]);
    }

    public function updateValue(Request $request, DirectoryValue $value)
    {
        $value->load('directory');

        $this->ensureDirectoryAccess($value->directory);
        $this->ensureCanManageValues();

        [$recordData, $displayValue] = $this->validateDirectoryValuePayload($request, $value->directory, $value->id);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $value->update([
            'value' => $displayValue,
            'data' => $recordData,
            'code' => $validated['code'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Значение обновлено',
            'value' => $this->serializeValue($value->fresh()),
        ]);
    }

    public function destroyValue(DirectoryValue $value)
    {
        $value->load('directory');

        $this->ensureDirectoryAccess($value->directory);
        $this->ensureCanDeleteValues();

        $value->delete();

        return response()->json([
            'success' => true,
            'message' => 'Значение удалено',
        ]);
    }

    private function getAccessibleDirectoriesQuery()
    {
        $divisionId = session('user_division_id');
        $managedDivisionIds = DivisionTree::managedDivisionIds($divisionId, session('user_role'));

        return Directory::query()->where(function ($query) use ($managedDivisionIds) {
            $query->whereDoesntHave('divisions');

            if (!empty($managedDivisionIds)) {
                $query->orWhereHas('divisions', function ($divisionQuery) use ($managedDivisionIds) {
                    $divisionQuery->whereIn('divisions.id', $managedDivisionIds);
                });
            }
        });
    }

    private function ensureDirectoryAccess(Directory $directory): void
    {
        $divisionId = session('user_division_id');
        $managedDivisionIds = DivisionTree::managedDivisionIds($divisionId, session('user_role'));

        $hasAccess = !$directory->divisions()->exists()
            || (!empty($managedDivisionIds) && $directory->divisions()->whereIn('divisions.id', $managedDivisionIds)->exists());

        abort_unless($hasAccess, 403, 'Нет доступа к этому справочнику');
    }

    private function ensureCanManageValues(): void
    {
        abort_unless(in_array(session('user_role'), ['foreman', 'admin'], true), 403, 'Недостаточно прав для изменения значений');
    }

    private function ensureCanDeleteValues(): void
    {
        abort_unless(session('user_role') === 'admin', 403, 'Недостаточно прав для удаления значений');
    }

    private function validateDirectoryValuePayload(Request $request, Directory $directory, ?int $ignoreId = null): array
    {
        $schema = $directory->schema ?? [];

        if (empty($schema)) {
            $validated = $request->validate([
                'value' => ['required', 'string', 'max:255'],
            ]);

            return [null, trim($validated['value'])];
        }

        $request->validate([
            'data' => ['required', 'array'],
        ]);

        $input = $this->applyAutoGeneratedQrFields($schema, $request->input('data', []), $ignoreId ? $this->findExistingData($directory, $ignoreId) : []);
        $recordData = DirectorySchema::validateRecord($schema, $input);
        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']), $ignoreId);
        $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);

        return [$recordData, $displayValue];
    }

    private function applyAutoGeneratedQrFields(array $schema, array $data, array $existingData = []): array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'qr' || empty($field['auto_generate'])) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (!$key || !empty($data[$key])) {
                continue;
            }

            if (!empty($existingData[$key])) {
                $data[$key] = $existingData[$key];
                continue;
            }

            $data[$key] = $this->generateQrValue();
        }

        return $data;
    }

    private function findExistingData(Directory $directory, int $valueId): array
    {
        $value = $directory->values()->whereKey($valueId)->first();

        return $value && is_array($value->data) ? $value->data : [];
    }

    private function generateQrValue(): string
    {
        return 'QR-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function buildDirectoryValuesCollection(Request $request, Directory $directory, array $schema, array $filters)
    {
        $query = $directory->values()
            ->orderBy('sort_order')
            ->orderBy('value');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $items = $query->get();

        if (!empty($filters)) {
            $items = $items->filter(function (DirectoryValue $value) use ($filters, $schema) {
                return $this->matchesValueFilters($value, $filters, $schema);
            })->values();
        }

        return $items->values();
    }

    private function serializeValue(DirectoryValue $value): array
    {
        return [
            'id' => $value->id,
            'value' => $value->value,
            'data' => $value->data,
            'code' => $value->code,
            'sort_order' => $value->sort_order,
            'is_active' => (bool) $value->is_active,
            'created_at' => optional($value->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function normalizeValueFilters($rawFilters, array $schema): array
    {
        if (!is_array($rawFilters) || empty($schema)) {
            return [];
        }

        $allowedKeys = collect($schema)
            ->pluck('key')
            ->filter()
            ->map(fn ($key) => (string) $key)
            ->all();

        return collect($rawFilters)
            ->filter(fn ($value, $key) => in_array((string) $key, $allowedKeys, true) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->all();
    }

    private function matchesValueFilters(DirectoryValue $value, array $filters, array $schema): bool
    {
        $data = is_array($value->data) ? $value->data : [];
        $fieldsByKey = collect($schema)->keyBy('key');

        foreach ($filters as $key => $expected) {
            $field = $fieldsByKey->get($key, []);
            $actual = $data[$key] ?? '';

            if (!$this->matchesSingleValueFilter($actual, $expected, $field['type'] ?? 'text')) {
                return false;
            }
        }

        return true;
    }

    private function matchesSingleValueFilter($actual, string $expected, string $type): bool
    {
        $actualText = mb_strtolower(trim((string) $actual));
        $expectedText = mb_strtolower(trim($expected));

        if ($expectedText === '') {
            return true;
        }

        if (in_array($type, ['list', 'date', 'time', 'directory', 'number'], true)) {
            return $actualText === $expectedText;
        }

        return mb_strpos($actualText, $expectedText) !== false;
    }

    private function exportDirectoryCsv(Directory $directory, array $schema, $items, array $referenceMap)
    {
        $fileName = 'directory_' . ($directory->code ?: $directory->id) . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($schema, $items, $referenceMap) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->directoryExportHeaders($schema), ';');

            foreach ($items as $item) {
                fputcsv($handle, $this->directoryExportRow($schema, $item, $referenceMap), ';');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportDirectoryXml(Directory $directory, array $schema, $items, array $referenceMap)
    {
        $fileName = 'directory_' . ($directory->code ?: $directory->id) . '_' . now()->format('Ymd_His') . '.xml';

        return response()->streamDownload(function () use ($directory, $schema, $items, $referenceMap) {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><directory_export/>');
            $xml->addChild('directory_code', htmlspecialchars((string) ($directory->code ?: '')));
            $xml->addChild('directory_name', htmlspecialchars((string) $directory->name));
            $rowsNode = $xml->addChild('values');

            foreach ($items as $item) {
                $itemNode = $rowsNode->addChild('value');

                foreach ($this->directoryExportRowMap($schema, $item, $referenceMap) as $key => $value) {
                    $itemNode->addChild($this->xmlSafeNodeName($key), htmlspecialchars((string) $value));
                }
            }

            echo $xml->asXML();
        }, $fileName, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function directoryExportHeaders(array $schema): array
    {
        return array_keys($this->directoryExportBaseRow($schema));
    }

    private function directoryExportRow(array $schema, DirectoryValue $value, array $referenceMap): array
    {
        return array_values($this->directoryExportRowMap($schema, $value, $referenceMap));
    }

    private function directoryExportRowMap(array $schema, DirectoryValue $value, array $referenceMap): array
    {
        $row = $this->directoryExportBaseRow($schema);
        $row['value'] = (string) ($value->value ?? '');
        $row['code'] = (string) ($value->code ?? '');
        $row['sort_order'] = (string) ($value->sort_order ?? 0);
        $row['is_active'] = $value->is_active ? '1' : '0';

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            $fieldValue = $value->data[$key] ?? '';
            $row[$key] = $fieldValue;

            if (($field['type'] ?? null) === 'directory') {
                $row[$key . '_display'] = $this->resolveDirectoryExportReferenceValue($key, $fieldValue, $referenceMap);
            }
        }

        return $row;
    }

    private function directoryExportBaseRow(array $schema): array
    {
        $row = [
            'value' => '',
            'code' => '',
            'sort_order' => '',
            'is_active' => '',
        ];

        foreach ($schema as $field) {
            if (!empty($field['key'])) {
                $row[$field['key']] = '';

                if (($field['type'] ?? null) === 'directory') {
                    $row[$field['key'] . '_display'] = '';
                }
            }
        }

        return $row;
    }

    private function buildDirectoryExportReferenceMap(array $schema, $items): array
    {
        $referenceIds = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (!$key || ($field['type'] ?? null) !== 'directory') {
                continue;
            }

            $referenceIds[$key] = collect($items)
                ->map(function (DirectoryValue $item) use ($key) {
                    return $item->data[$key] ?? null;
                })
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->filter(fn (int $value) => $value > 0)
                ->unique()
                ->values()
                ->all();
        }

        $allIds = collect($referenceIds)->flatten()->unique()->values()->all();

        if (empty($allIds)) {
            return [];
        }

        return DirectoryValue::query()
            ->whereIn('id', $allIds)
            ->get(['id', 'value'])
            ->mapWithKeys(function (DirectoryValue $value) {
                return [(string) $value->id => (string) ($value->value ?? '')];
            })
            ->all();
    }

    private function resolveDirectoryExportReferenceValue(string $fieldKey, $fieldValue, array $referenceMap): string
    {
        if ($fieldValue === null || $fieldValue === '') {
            return '';
        }

        return (string) ($referenceMap[(string) $fieldValue] ?? '');
    }

    private function readDirectoryCsvRows(Request $request): array
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

    private function readDirectoryXmlRows(Request $request): array
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

        if (!$xml || !isset($xml->values)) {
            throw ValidationException::withMessages([
                'import_file' => ['Не удалось прочитать XML-файл'],
            ]);
        }

        $rows = [];

        foreach ($xml->values->value as $valueNode) {
            $row = [];

            foreach ($valueNode->children() as $child) {
                $row[$child->getName()] = (string) $child;
            }

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function buildDirectoryImportPayload(Directory $directory, array $row): array
    {
        $schema = $directory->schema ?? [];

        if (empty($schema)) {
            return [
                'value' => trim((string) ($row['value'] ?? '')),
            ];
        }

        $data = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            $data[$key] = $row[$key] ?? '';
        }

        return [
            'data' => $data,
        ];
    }

    private function normalizeImportBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on', 'да'], true);
    }

    private function xmlSafeNodeName(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

        if ($safe === '' || preg_match('/^[0-9]/', $safe)) {
            $safe = 'field_' . $safe;
        }

        return $safe;
    }
}
