<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Support\DirectorySchema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DirectoryController extends Controller
{
    public function index()
    {
        $this->authorizePageAccess();

        $divisions = Division::orderBy('name')->get();
        $referenceDirectories = $this->visibleDirectoriesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'schema']);
        $directoryRoutes = $this->directoryRoutes();
        $directoryPageLayout = $this->directoryPageLayout();
        $directoryPageTitle = $this->directoryPageTitle();
        $directoryCanModifyFilled = $this->canModifyFilledDirectory();

        return view('admin.directories.index', compact(
            'divisions',
            'referenceDirectories',
            'directoryRoutes',
            'directoryPageLayout',
            'directoryPageTitle',
            'directoryCanModifyFilled'
        ));
    }

    public function list(Request $request)
    {
        $this->authorizePageAccess();

        $query = $this->visibleDirectoriesQuery()
            ->withCount('values')
            ->with(['divisions', 'creator'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $directories = $query->paginate(10);

        return response()->json([
            'success' => true,
            'items' => $directories->items(),
            'pagination' => [
                'current_page' => $directories->currentPage(),
                'last_page' => $directories->lastPage(),
                'per_page' => $directories->perPage(),
                'total' => $directories->total(),
                'from' => $directories->firstItem(),
                'to' => $directories->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePageAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:directories,name'],
            'code' => ['nullable', 'string', 'max:255', 'unique:directories,code'],
            'description' => ['nullable', 'string'],
            'division_ids' => ['nullable', 'array'],
            'division_ids.*' => ['exists:divisions,id'],
            'schema' => ['nullable', 'array'],
        ]);

        $schema = DirectorySchema::normalizeSchema($validated['schema'] ?? []);

        $directory = DB::transaction(function () use ($validated, $schema) {
            $directory = Directory::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'schema' => $schema,
                'created_by' => $this->currentDirectoryCreatorId(),
            ]);

            $directory->divisions()->sync($validated['division_ids'] ?? []);

            return $directory;
        });

        return response()->json([
            'success' => true,
            'message' => 'Справочник создан',
            'directory' => $directory,
        ]);
    }

    public function show(Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);
        $directory->load('divisions');

        return response()->json([
            'success' => true,
            'directory' => [
                'id' => $directory->id,
                'name' => $directory->name,
                'code' => $directory->code,
                'description' => $directory->description,
                'schema' => $directory->schema ?? [],
                'division_ids' => $directory->divisions->pluck('id')->values(),
            ],
        ]);
    }

    public function update(Request $request, Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        if (!$this->canModifyFilledDirectory() && $directory->values()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя редактировать шаблон справочника, потому что он уже заполнен значениями',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('directories', 'name')->ignore($directory->id)],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('directories', 'code')->ignore($directory->id)],
            'description' => ['nullable', 'string'],
            'division_ids' => ['nullable', 'array'],
            'division_ids.*' => ['exists:divisions,id'],
            'schema' => ['nullable', 'array'],
        ]);

        $schema = DirectorySchema::normalizeSchema($validated['schema'] ?? []);

        DB::transaction(function () use ($directory, $validated, $schema) {
            $directory->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'schema' => $schema,
            ]);

            $directory->divisions()->sync($validated['division_ids'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Справочник обновлён',
        ]);
    }

    public function destroy(Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        if (!$this->canModifyFilledDirectory() && $directory->values()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить справочник, потому что он уже заполнен значениями',
            ], 422);
        }

        $directory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Справочник удалён',
        ]);
    }

    public function exportTemplate(Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);
        $directory->load('divisions');

        $payload = [
            'kind' => 'directory_template',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'template' => [
                'name' => $directory->name,
                'code' => $directory->code,
                'description' => $directory->description,
                'divisions' => $directory->divisions->map(function (Division $division) {
                    return [
                        'name' => $division->name,
                    ];
                })->values()->all(),
                'schema' => $this->exportDirectorySchema($directory->schema ?? []),
            ],
        ];

        $fileName = 'directory_template_' . Str::slug($directory->code ?: $directory->name, '_') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function importTemplate(Request $request)
    {
        $this->authorizePageAccess();

        $request->validate([
            'template_file' => ['required', 'file', 'mimes:json,txt'],
        ]);

        $payload = $this->readImportPayload($request, 'directory_template');
        $template = $payload['template'] ?? [];
        $input = $this->normalizeDirectoryTemplateInput([
            'name' => $this->generateImportedDirectoryName((string) ($template['name'] ?? 'Справочник')),
            'code' => $this->generateImportedDirectoryCode($template['code'] ?? null),
            'description' => $template['description'] ?? null,
            'division_ids' => $this->resolveDivisionIdsFromImport($template['divisions'] ?? []),
            'schema' => $this->importDirectorySchema($template['schema'] ?? []),
        ]);

        $directory = DB::transaction(function () use ($input) {
            $directory = Directory::create([
                'name' => $input['name'],
                'code' => $input['code'] ?? null,
                'description' => $input['description'] ?? null,
                'schema' => $input['schema'],
                'created_by' => $this->currentDirectoryCreatorId(),
            ]);

            $directory->divisions()->sync($input['division_ids'] ?? []);

            return $directory;
        });

        return response()->json([
            'success' => true,
            'message' => 'Шаблон справочника импортирован',
            'directory' => $directory,
        ]);
    }

    public function valuesList(Request $request, Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        $query = $directory->values()
            ->with('directory')
            ->orderBy('sort_order')
            ->orderBy('value');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $schema = $directory->schema ?? [];
        $filters = $this->normalizeValueFilters($request->input('filters', []), $schema);

        if (!empty($filters)) {
            $filteredValues = $query->get()
                ->filter(function (DirectoryValue $value) use ($filters, $schema) {
                    return $this->matchesValueFilters($value, $filters, $schema);
                })
                ->values();

            if ($request->boolean('all')) {
                return response()->json([
                    'success' => true,
                    'directory' => $directory,
                    'items' => $filteredValues->map(function (DirectoryValue $value) {
                        return $this->serializeDirectoryValue($value);
                    })->values(),
                ]);
            }

            $page = max(1, (int) $request->input('page', 1));
            $perPage = 10;
            $values = new LengthAwarePaginator(
                $filteredValues->forPage($page, $perPage)->values(),
                $filteredValues->count(),
                $perPage,
                $page
            );

            return response()->json([
                'success' => true,
                'directory' => $directory,
                'items' => $values->getCollection()->map(function (DirectoryValue $value) {
                    return $this->serializeDirectoryValue($value);
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

        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'directory' => $directory,
                'items' => $query->get()->map(function (DirectoryValue $value) {
                    return $this->serializeDirectoryValue($value);
                })->values(),
            ]);
        }

        $values = $query->paginate(10);

        return response()->json([
            'success' => true,
            'directory' => $directory,
            'items' => collect($values->items())->map(function (DirectoryValue $value) {
                return $this->serializeDirectoryValue($value);
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

    public function print(Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

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
        $this->authorizeDirectoryAccess($directory);

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

    public function valueStore(Request $request, Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        [$recordData, $displayValue] = $this->validateDirectoryValuePayload($request, $directory);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $value = $directory->values()->create([
            'value' => $displayValue,
            'data' => $recordData,
            'code' => $validated['code'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        $value->setRelation('directory', $directory);

        return response()->json([
            'success' => true,
            'message' => 'Значение добавлено',
            'value' => $this->serializeDirectoryValue($value),
        ]);
    }

    public function valueShow(DirectoryValue $value)
    {
        $value->load('directory');
        $this->authorizeDirectoryAccess($value->directory);

        return response()->json([
            'success' => true,
            'value' => $this->serializeDirectoryValue($value),
        ]);
    }

    public function valueUpdate(Request $request, DirectoryValue $value)
    {
        $value->load('directory');
        $this->authorizeDirectoryAccess($value->directory);

        [$recordData, $displayValue] = $this->validateDirectoryValuePayloadForUpdate($request, $value);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $value->update([
            'value' => $displayValue,
            'data' => $recordData,
            'code' => $validated['code'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Значение обновлено',
        ]);
    }

    public function valueDestroy(DirectoryValue $value)
    {
        $value->load('directory');
        $this->authorizeDirectoryAccess($value->directory);

        $value->delete();

        return response()->json([
            'success' => true,
            'message' => 'Значение удалено',
        ]);
    }

    public function importCsv(Request $request, Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'delimiter' => ['required', 'string', 'max:2'],
            'has_header' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('csv_file');
        $delimiter = $request->input('delimiter', ';');
        $hasHeader = $request->boolean('has_header');
        $path = $file->getRealPath();

        if (!$path || !file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'CSV-файл не найден',
            ], 422);
        }

        $handle = fopen($path, 'r');

        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось открыть CSV-файл',
            ], 422);
        }

        $created = 0;
        $skipped = 0;
        $rowNumber = 0;
        $schema = $directory->schema ?? [];
        $header = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($rowNumber === 1 && $hasHeader) {
                    $header = collect($row)->map(fn ($item) => trim((string)$item))->values()->all();
                    continue;
                }

                $recordData = null;
                $displayValue = '';
                $code = '';
                $sortOrder = '';

                if (!empty($schema)) {
                    $input = [];

                    foreach ($schema as $index => $field) {
                        $columnIndex = $hasHeader ? array_search($field['key'], $header, true) : $index;
                        $columnIndex = $columnIndex === false ? $index : $columnIndex;
                        $input[$field['key']] = trim((string)($row[$columnIndex] ?? ''));
                    }

                    $input = $this->applyAutoGeneratedQrFields($schema, $input);
                    $recordData = DirectorySchema::validateRecord($schema, $input);
                    try {
                        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']));
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        $skipped++;
                        continue;
                    }
                    $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);
                    $codeIndex = count($schema);
                    $sortIndex = count($schema) + 1;
                    $code = trim((string)($row[$codeIndex] ?? ''));
                    $sortOrder = trim((string)($row[$sortIndex] ?? ''));
                } else {
                    $displayValue = trim((string)($row[0] ?? ''));
                    $code = trim((string)($row[1] ?? ''));
                    $sortOrder = trim((string)($row[2] ?? ''));
                }

                if ($displayValue === '') {
                    $skipped++;
                    continue;
                }

                $exists = DirectoryValue::where('directory_id', $directory->id)
                    ->where('value', $displayValue)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                DirectoryValue::create([
                    'directory_id' => $directory->id,
                    'value' => $displayValue,
                    'data' => $recordData,
                    'code' => $code !== '' ? $code : null,
                    'sort_order' => is_numeric($sortOrder) ? (int)$sortOrder : 0,
                    'is_active' => true,
                ]);

                $created++;
            }

            fclose($handle);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Импорт завершён. Добавлено: {$created}, пропущено: {$skipped}",
                'created' => $created,
                'skipped' => $skipped,
            ]);
        } catch (\Throwable $e) {
            fclose($handle);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка импорта CSV: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function validateDirectoryValuePayload(Request $request, Directory $directory): array
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

        $input = $this->applyAutoGeneratedQrFields($schema, $request->input('data', []));
        $recordData = DirectorySchema::validateRecord($schema, $input);
        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']));
        $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);

        return [$recordData, $displayValue];
    }

    private function validateDirectoryValuePayloadForUpdate(Request $request, DirectoryValue $value): array
    {
        $directory = $value->directory;
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

        $input = $this->applyAutoGeneratedQrFields($schema, $request->input('data', []), $value->data ?? []);
        $recordData = DirectorySchema::validateRecord($schema, $input);
        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']), $value->id);
        $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);

        return [$recordData, $displayValue];
    }

    private function serializeDirectoryValue(DirectoryValue $value): array
    {
        return [
            'id' => $value->id,
            'directory_id' => $value->directory_id,
            'value' => $value->value,
            'data' => $value->data,
            'code' => $value->code,
            'sort_order' => $value->sort_order,
            'is_active' => (bool)$value->is_active,
            'created_at' => $value->created_at,
            'updated_at' => $value->updated_at,
        ];
    }

    private function applyAutoGeneratedQrFields(array $schema, array $data, array $existingData = []): array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'qr' || empty($field['auto_generate'])) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            if (!empty($data[$key])) {
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

    private function generateQrValue(): string
    {
        return 'QR-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
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

    protected function authorizePageAccess(): void
    {
    }

    protected function authorizeDirectoryAccess(?Directory $directory): void
    {
        if (!$directory) {
            abort(404);
        }
    }

    protected function visibleDirectoriesQuery()
    {
        return Directory::query();
    }

    protected function currentDirectoryCreatorId(): ?int
    {
        return null;
    }

    protected function canModifyFilledDirectory(): bool
    {
        return true;
    }

    protected function directoryPageLayout(): string
    {
        return 'admin.layouts.app';
    }

    protected function directoryPageTitle(): string
    {
        return 'Справочники';
    }

    protected function directoryRoutes(): array
    {
        return [
            'list' => route('admin.directories.list'),
            'store' => route('admin.directories.store'),
            'directory' => url('/admin/directories/__ID__'),
            'directoryExport' => url('/admin/directories/__ID__/export-template'),
            'directoryImport' => route('admin.directories.import-template'),
            'directoryValues' => url('/admin/directories/__ID__/values'),
            'directoryImportCsv' => url('/admin/directories/__ID__/import-csv'),
            'directoryPrint' => url('/admin/directories/__ID__/print'),
            'directoryBarcodes' => url('/admin/directories/__ID__/barcodes'),
            'value' => url('/admin/directory-values/__ID__'),
        ];
    }

    private function normalizeDirectoryTemplateInput(array $input, ?Directory $directory = null): array
    {
        $validated = validator($input, [
            'name' => ['required', 'string', 'max:255', Rule::unique('directories', 'name')->ignore($directory?->id)],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('directories', 'code')->ignore($directory?->id)],
            'description' => ['nullable', 'string'],
            'division_ids' => ['nullable', 'array'],
            'division_ids.*' => ['exists:divisions,id'],
            'schema' => ['nullable', 'array'],
        ])->validate();

        $validated['schema'] = DirectorySchema::normalizeSchema($validated['schema'] ?? []);

        return $validated;
    }

    private function exportDirectorySchema(array $schema): array
    {
        return collect($schema)->map(function ($field) {
            if (($field['type'] ?? '') === 'directory' && !empty($field['directory_id'])) {
                $directory = Directory::find((int) $field['directory_id']);
                $field['directory_ref'] = [
                    'code' => $directory?->code,
                    'name' => $directory?->name,
                ];
                unset($field['directory_id']);
            }

            return $field;
        })->values()->all();
    }

    private function importDirectorySchema(array $schema): array
    {
        return collect($schema)->map(function ($field) {
            if (!is_array($field)) {
                throw ValidationException::withMessages([
                    'template_file' => ['Некорректное описание поля в импортируемом справочнике'],
                ]);
            }

            if (($field['type'] ?? '') === 'directory') {
                $directory = $this->findDirectoryByImportRef($field['directory_ref'] ?? []);

                if (!$directory) {
                    $fieldLabel = $field['label'] ?? ($field['key'] ?? 'поле');
                    throw ValidationException::withMessages([
                        'template_file' => ["Для поля «{$fieldLabel}» не найден связанный справочник при импорте"],
                    ]);
                }

                $field['directory_id'] = $directory->id;
            }

            unset($field['directory_ref']);

            return $field;
        })->values()->all();
    }

    private function findDirectoryByImportRef($ref): ?Directory
    {
        if (!is_array($ref)) {
            return null;
        }

        $code = trim((string) ($ref['code'] ?? ''));
        $name = trim((string) ($ref['name'] ?? ''));

        if ($code !== '') {
            $directory = Directory::where('code', $code)->first();
            if ($directory) {
                return $directory;
            }
        }

        if ($name !== '') {
            return Directory::where('name', $name)->first();
        }

        return null;
    }

    private function resolveDivisionIdsFromImport(array $divisions): array
    {
        return collect($divisions)
            ->map(function ($division) {
                $name = trim((string) ($division['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return Division::where('name', $name)->value('id');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function generateImportedDirectoryName(string $baseName): string
    {
        $baseName = trim($baseName) !== '' ? trim($baseName) : 'Справочник';
        $candidate = $baseName . ' (импорт)';
        $suffix = 2;

        while (Directory::where('name', $candidate)->exists()) {
            $candidate = $baseName . ' (импорт ' . $suffix . ')';
            $suffix++;
        }

        return $candidate;
    }

    private function generateImportedDirectoryCode(?string $baseCode): ?string
    {
        $baseCode = trim((string) $baseCode);

        if ($baseCode === '') {
            return null;
        }

        $candidate = $baseCode . '_import';
        $suffix = 2;

        while (Directory::where('code', $candidate)->exists()) {
            $candidate = $baseCode . '_import_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function readImportPayload(Request $request, string $expectedKind): array
    {
        $file = $request->file('template_file');
        $path = $file?->getRealPath();

        if (!$path || !file_exists($path)) {
            throw ValidationException::withMessages([
                'template_file' => ['Файл импорта не найден'],
            ]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'template_file' => ['Не удалось прочитать JSON-файл'],
            ]);
        }

        if (($decoded['kind'] ?? null) !== $expectedKind) {
            throw ValidationException::withMessages([
                'template_file' => ['Выбран файл другого типа шаблона'],
            ]);
        }

        return $decoded;
    }

}
