<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Directory;
use App\Models\DirectoryScript;
use App\Models\DirectoryTemplateList;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\SavedFilter;
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
        $templateLists = DirectorySchema::normalizeTemplateLists(
            DirectoryTemplateList::query()->with('creator')->orderBy('name')->get()
        );

        return view('admin.directories.index', compact(
            'divisions',
            'referenceDirectories',
            'directoryRoutes',
            'directoryPageLayout',
            'directoryPageTitle',
            'directoryCanModifyFilled',
            'templateLists'
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
            $this->logDirectoryActivity('directory_created', $directory, 'Создан справочник');

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
                'scripts' => $this->serializeDirectoryScripts($directory->scripts),
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
            $this->logDirectoryActivity('directory_updated', $directory, 'Обновлён справочник');
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
        $this->logDirectoryActivity('directory_deleted', $directory, 'Удалён справочник');

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

        $showDeleted = $request->boolean('show_deleted');

        $query = $directory->values()
            ->with(['directory', 'creator', 'updater', 'deleter'])
            ->orderBy('sort_order')
            ->orderBy('value');

        if ($showDeleted) {
            $query->withTrashed()->whereNotNull('deleted_at');
        }

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
                    'directory' => $this->serializeDirectoryForValues($directory),
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
                'directory' => $this->serializeDirectoryForValues($directory),
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
                'directory' => $this->serializeDirectoryForValues($directory),
                'items' => $query->get()->map(function (DirectoryValue $value) {
                    return $this->serializeDirectoryValue($value);
                })->values(),
            ]);
        }

        $values = $query->paginate(10);

        return response()->json([
            'success' => true,
            'directory' => $this->serializeDirectoryForValues($directory),
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

        [$recordData, $displayValue, $pendingUploads] = $this->validateDirectoryValuePayload($request, $directory);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->storePendingDirectoryImages($pendingUploads);

        try {
            $value = $directory->values()->create([
                'created_by' => $this->currentActorId(),
                'updated_by' => $this->currentActorId(),
                'value' => $displayValue,
                'data' => $recordData,
                'code' => $validated['code'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);
        } catch (\Throwable $e) {
            $this->deleteDirectoryImageFiles(array_keys($pendingUploads));
            throw $e;
        }

        $value->setRelation('directory', $directory);
        $value->load(['creator', 'updater', 'deleter']);
        $this->logDirectoryValueActivity('directory_value_created', $value, 'Добавлено значение справочника');

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
        $value->loadMissing(['creator', 'updater', 'deleter']);

        return response()->json([
            'success' => true,
            'value' => $this->serializeDirectoryValue($value),
        ]);
    }

    public function valueUpdate(Request $request, DirectoryValue $value)
    {
        $value->load('directory');
        $this->authorizeDirectoryAccess($value->directory);

        [$recordData, $displayValue, $pendingUploads, $replacedFiles] = $this->validateDirectoryValuePayloadForUpdate($request, $value);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->storePendingDirectoryImages($pendingUploads);

        try {
            $value->update([
                'value' => $displayValue,
                'data' => $recordData,
                'code' => $validated['code'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
                'updated_by' => $this->currentActorId(),
            ]);
        } catch (\Throwable $e) {
            $this->deleteDirectoryImageFiles(array_keys($pendingUploads));
            throw $e;
        }

        $this->deleteDirectoryImageFiles($replacedFiles);
        $value->load(['directory', 'creator', 'updater', 'deleter']);
        $this->logDirectoryValueActivity('directory_value_updated', $value, 'Обновлено значение справочника');

        return response()->json([
            'success' => true,
            'message' => 'Значение обновлено',
        ]);
    }

    public function valueDestroy(DirectoryValue $value)
    {
        $value->load('directory');
        $this->authorizeDirectoryAccess($value->directory);

        $value->forceFill([
            'deleted_by' => $this->currentActorId(),
        ])->save();
        $value->delete();
        $this->logDirectoryValueActivity('directory_value_deleted', $value, 'Значение справочника помечено как удалённое');

        return response()->json([
            'success' => true,
            'message' => 'Значение удалено',
        ]);
    }

    public function valueRestore(DirectoryValue $value)
    {
        $value->load('directory');
        $this->authorizeDirectoryAccess($value->directory);

        if (!$value->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Запись не находится в удалённых',
            ], 422);
        }

        $value->restore();
        $value->forceFill([
            'deleted_by' => null,
            'updated_by' => $this->currentActorId(),
        ])->save();
        $value->load(['directory', 'creator', 'updater', 'deleter']);
        $this->logDirectoryValueActivity('directory_value_restored', $value, 'Значение справочника восстановлено');

        return response()->json([
            'success' => true,
            'message' => 'Запись восстановлена',
            'value' => $this->serializeDirectoryValue($value),
        ]);
    }

    public function scriptsList(Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        return response()->json([
            'success' => true,
            'items' => $this->serializeDirectoryScripts($directory->scripts()->get()),
        ]);
    }

    public function templateListsList()
    {
        $this->authorizePageAccess();

        return response()->json([
            'success' => true,
            'items' => DirectorySchema::normalizeTemplateLists(
                DirectoryTemplateList::query()->with('creator')->orderBy('name')->get()
            ),
        ]);
    }

    public function templateListShow(DirectoryTemplateList $templateList)
    {
        $this->authorizePageAccess();
        $templateList->load('creator');

        return response()->json([
            'success' => true,
            'item' => DirectorySchema::serializeTemplateList($templateList),
        ]);
    }

    public function templateListStore(Request $request)
    {
        $this->authorizePageAccess();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'unique:directory_template_lists,code'],
            'description' => ['nullable', 'string'],
        ]);

        $payload = DirectorySchema::validateTemplateListDefinition($request->all());

        $templateList = DirectoryTemplateList::query()->create([
            'name' => $payload['name'],
            'code' => $payload['code'],
            'description' => $payload['description'],
            'items' => $payload['items'],
            'created_by' => session('user_id'),
        ]);

        $templateList->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Список шаблонов добавлен',
            'item' => DirectorySchema::serializeTemplateList($templateList),
        ]);
    }

    public function templateListUpdate(Request $request, DirectoryTemplateList $templateList)
    {
        $this->authorizePageAccess();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('directory_template_lists', 'code')->ignore($templateList->id)],
            'description' => ['nullable', 'string'],
        ]);

        $payload = DirectorySchema::validateTemplateListDefinition($request->all());

        $templateList->update([
            'name' => $payload['name'],
            'code' => $payload['code'],
            'description' => $payload['description'],
            'items' => $payload['items'],
        ]);

        $templateList->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Список шаблонов обновлён',
            'item' => DirectorySchema::serializeTemplateList($templateList),
        ]);
    }

    public function templateListDestroy(DirectoryTemplateList $templateList)
    {
        $this->authorizePageAccess();

        $isUsed = Directory::query()
            ->get(['id', 'schema'])
            ->contains(function (Directory $directory) use ($templateList) {
                return collect($directory->schema ?? [])->contains(function ($field) use ($templateList) {
                    return ($field['type'] ?? null) === 'template_list'
                        && (int) ($field['template_list_id'] ?? 0) === (int) $templateList->id;
                });
            });

        if ($isUsed) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить список шаблонов, пока он используется в справочниках',
            ], 422);
        }

        $templateList->delete();

        return response()->json([
            'success' => true,
            'message' => 'Список шаблонов удалён',
        ]);
    }

    public function scriptStore(Request $request, Directory $directory)
    {
        $this->authorizeDirectoryAccess($directory);

        $validated = $this->validateDirectoryScriptPayload($request);

        $script = $directory->scripts()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'JS-код добавлен',
            'script' => $this->serializeDirectoryScript($script),
        ]);
    }

    public function scriptShow(DirectoryScript $script)
    {
        $script->load('directory');
        $this->authorizeDirectoryAccess($script->directory);

        return response()->json([
            'success' => true,
            'script' => $this->serializeDirectoryScript($script),
        ]);
    }

    public function scriptUpdate(Request $request, DirectoryScript $script)
    {
        $script->load('directory');
        $this->authorizeDirectoryAccess($script->directory);

        $validated = $this->validateDirectoryScriptPayload($request);
        $script->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'JS-код обновлён',
            'script' => $this->serializeDirectoryScript($script->fresh()),
        ]);
    }

    public function scriptDestroy(DirectoryScript $script)
    {
        $script->load('directory');
        $this->authorizeDirectoryAccess($script->directory);
        $script->delete();

        return response()->json([
            'success' => true,
            'message' => 'JS-код удалён',
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
        $schema = $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []);

        if (empty($schema)) {
            $validated = $request->validate([
                'value' => ['required', 'string', 'max:255'],
            ]);

            return [null, trim($validated['value']), []];
        }

        $request->validate([
            'data' => ['required', 'array'],
        ]);

        [$input, $pendingUploads] = $this->prepareDirectoryImageFields($request, $directory, $schema, $request->input('data', []));
        $input = $this->applyAutoGeneratedQrFields($schema, $input);
        $input = DirectorySchema::applyTemplateListValues($schema, $input);
        $recordData = DirectorySchema::validateRecord($schema, $input);
        $this->validateParentSelfReference($schema, $recordData);
        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']));
        $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);

        return [$recordData, $displayValue, $pendingUploads];
    }

    private function validateDirectoryValuePayloadForUpdate(Request $request, DirectoryValue $value): array
    {
        $directory = $value->directory;
        $schema = $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []);

        if (empty($schema)) {
            $validated = $request->validate([
                'value' => ['required', 'string', 'max:255'],
            ]);

            return [null, trim($validated['value']), [], []];
        }

        $request->validate([
            'data' => ['required', 'array'],
        ]);

        [$input, $pendingUploads, $replacedFiles] = $this->prepareDirectoryImageFields($request, $directory, $schema, $request->input('data', []), is_array($value->data) ? $value->data : []);
        $input = $this->applyAutoGeneratedQrFields($schema, $input, $value->data ?? []);
        $input = DirectorySchema::applyTemplateListValues($schema, $input, is_array($value->data) ? $value->data : []);
        $recordData = DirectorySchema::validateRecord($schema, $input);
        $this->validateParentSelfReference($schema, $recordData, $value->id);
        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']), $value->id);
        $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);

        return [$recordData, $displayValue, $pendingUploads, $replacedFiles];
    }

    private function serializeDirectoryValue(DirectoryValue $value): array
    {
        $directory = $value->relationLoaded('directory') && $value->directory
            ? $value->directory
            : $value->directory()->first();
        $schema = $directory ? ($directory->schema ?? []) : [];
        $data = is_array($value->data) ? $value->data : [];

        return [
            'id' => $value->id,
            'directory_id' => $value->directory_id,
            'value' => $value->value,
            'data' => $data,
            'image_urls' => $this->buildDirectoryImageUrls($schema, $data),
            'code' => $value->code,
            'sort_order' => $value->sort_order,
            'is_active' => (bool)$value->is_active,
            'created_at' => $value->created_at,
            'updated_at' => $value->updated_at,
            'deleted_at' => $value->deleted_at,
            'created_by' => $value->created_by,
            'updated_by' => $value->updated_by,
            'deleted_by' => $value->deleted_by,
            'created_by_name' => optional($value->creator)->name,
            'updated_by_name' => optional($value->updater)->name,
            'deleted_by_name' => optional($value->deleter)->name,
        ];
    }

    private function currentActorId(): ?int
    {
        $userId = session('user_id');

        return $userId ? (int) $userId : null;
    }

    private function logDirectoryActivity(string $action, Directory $directory, string $description): void
    {
        ActivityLog::create([
            'user_id' => $this->currentActorId(),
            'action' => $action,
            'entity_type' => 'directory',
            'entity_id' => $directory->id,
            'description' => $description . ': ' . $directory->name,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function logDirectoryValueActivity(string $action, DirectoryValue $value, string $description): void
    {
        $directory = $value->relationLoaded('directory') && $value->directory
            ? $value->directory
            : $value->directory()->first();

        $directoryName = $directory?->name ? ' [' . $directory->name . ']' : '';
        $valueLabel = trim((string) ($value->value ?? ''));

        ActivityLog::create([
            'user_id' => $this->currentActorId(),
            'action' => $action,
            'entity_type' => 'directory_value',
            'entity_id' => $value->id,
            'description' => $description . $directoryName . ($valueLabel !== '' ? ': ' . $valueLabel : ''),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function prepareDirectoryImageFields(Request $request, Directory $directory, array $schema, array $input, array $existingData = []): array
    {
        $pendingUploads = [];
        $replacedFiles = [];

        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'image') {
                continue;
            }

            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $request->validate([
                "data.{$key}" => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            ]);

            $uploadedFile = $request->file("data.{$key}");

            if ($uploadedFile) {
                $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
                $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                $fileName = $this->generateDirectoryImageFileName($directory, $key, $extension);
                $input[$key] = $fileName;
                $pendingUploads[$fileName] = $uploadedFile;

                $oldFile = (string) ($existingData[$key] ?? '');
                if ($oldFile !== '' && $oldFile !== $fileName) {
                    $replacedFiles[] = $oldFile;
                }

                continue;
            }

            if ($request->boolean("image_remove.{$key}")) {
                $oldFile = (string) ($existingData[$key] ?? '');
                $input[$key] = null;

                if ($oldFile !== '') {
                    $replacedFiles[] = $oldFile;
                }

                continue;
            }

            if (!empty($existingData[$key])) {
                $input[$key] = $existingData[$key];
            }
        }

        return [$input, $pendingUploads, array_values(array_unique($replacedFiles))];
    }

    private function storePendingDirectoryImages(array $pendingUploads): void
    {
        if (empty($pendingUploads)) {
            return;
        }

        $directory = $this->directoryImagesPath();
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        foreach ($pendingUploads as $fileName => $uploadedFile) {
            $uploadedFile->move($directory, $fileName);
        }
    }

    private function deleteDirectoryImageFiles(array $fileNames): void
    {
        foreach (array_unique(array_filter($fileNames)) as $fileName) {
            $path = $this->directoryImagePath((string) $fileName);

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function extractDirectoryImageFiles(array $schema, array $data): array
    {
        $files = [];

        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'image') {
                continue;
            }

            $key = $field['key'] ?? null;
            $fileName = $key ? ($data[$key] ?? null) : null;

            if (is_string($fileName) && $fileName !== '') {
                $files[] = $fileName;
            }
        }

        return $files;
    }

    private function buildDirectoryImageUrls(array $schema, array $data): array
    {
        $urls = [];

        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'image') {
                continue;
            }

            $key = $field['key'] ?? null;
            $fileName = $key ? ($data[$key] ?? null) : null;

            if ($key && is_string($fileName) && $fileName !== '') {
                $urls[$key] = asset('uploads/directory-images/' . ltrim($fileName, '/'));
            }
        }

        return $urls;
    }

    private function generateDirectoryImageFileName(Directory $directory, string $fieldKey, string $extension): string
    {
        return 'directory_' . $directory->id . '_' . $fieldKey . '_' . Str::lower((string) Str::uuid()) . '.' . $extension;
    }

    private function directoryImagesPath(): string
    {
        return public_path('uploads/directory-images');
    }

    private function directoryImagePath(string $fileName): string
    {
        return $this->directoryImagesPath() . DIRECTORY_SEPARATOR . $fileName;
    }

    private function serializeDirectoryForValues(Directory $directory): array
    {
        return [
            'id' => $directory->id,
            'name' => $directory->name,
            'code' => $directory->code,
            'description' => $directory->description,
            'schema' => $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []),
            'scripts' => $this->serializeDirectoryScripts($directory->scripts()->get()),
            'filter_presets' => $this->serializeSavedFilters($directory),
            'template_lists' => DirectorySchema::normalizeTemplateLists(
                DirectoryTemplateList::query()->with('creator')->orderBy('name')->get()
            ),
        ];
    }

    private function prepareDirectorySchemaForRuntime(Directory $directory, array $schema): array
    {
        return collect($schema)->map(function ($field) use ($directory) {
            if (!is_array($field) || ($field['type'] ?? null) !== 'parent') {
                return $field;
            }

            $field['directory_id'] = $directory->id;
            $field['directory_display_field'] = $field['parent_display_field'] ?? null;

            return $field;
        })->values()->all();
    }

    private function validateParentSelfReference(array $schema, array $recordData, ?int $currentValueId = null): void
    {
        if (!$currentValueId) {
            return;
        }

        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'parent') {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $value = $recordData[$key] ?? null;

            if ($key !== '' && $value !== null && (int) $value === $currentValueId) {
                throw ValidationException::withMessages([
                    "data.{$key}" => ["Поле «{$label}» не может ссылаться на текущую запись"],
                ]);
            }
        }
    }

    private function validateDirectoryScriptPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'code' => (string) $validated['code'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function serializeDirectoryScript(DirectoryScript $script): array
    {
        return [
            'id' => $script->id,
            'directory_id' => $script->directory_id,
            'name' => $script->name,
            'description' => $script->description,
            'code' => $script->code,
            'sort_order' => (int) $script->sort_order,
            'is_active' => (bool) $script->is_active,
        ];
    }

    private function serializeDirectoryScripts($scripts): array
    {
        return collect($scripts)->map(function (DirectoryScript $script) {
            return $this->serializeDirectoryScript($script);
        })->values()->all();
    }

    private function serializeSavedFilters(Directory $directory): array
    {
        return SavedFilter::query()
            ->where('user_id', (int) session('user_id'))
            ->where('entity_type', SavedFilter::ENTITY_DIRECTORY)
            ->where('entity_id', $directory->id)
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

        $allowedKeys = collect(DirectorySchema::expandSchema($schema))
            ->pluck('key')
            ->filter()
            ->map(fn ($key) => (string) $key)
            ->all();

        return collect($rawFilters)
            ->filter(function ($value, $key) use ($allowedKeys) {
                if (!in_array((string) $key, $allowedKeys, true)) {
                    return false;
                }

                if (is_array($value)) {
                    return trim((string) ($value['value'] ?? '')) !== '';
                }

                return trim((string) $value) !== '';
            })
            ->map(function ($value) {
                if (!is_array($value)) {
                    return [
                        'operator' => 'eq',
                        'value' => trim((string) $value),
                        'value_to' => '',
                    ];
                }

                $operator = trim((string) ($value['operator'] ?? 'eq'));
                if (!in_array($operator, ['eq', 'gt', 'lt', 'neq', 'between', 'contains'], true)) {
                    $operator = 'eq';
                }

                return [
                    'operator' => $operator,
                    'value' => trim((string) ($value['value'] ?? '')),
                    'value_to' => trim((string) ($value['value_to'] ?? '')),
                ];
            })
            ->all();
    }

    private function matchesValueFilters(DirectoryValue $value, array $filters, array $schema): bool
    {
        $data = is_array($value->data) ? $value->data : [];
        $fieldsByKey = collect(DirectorySchema::expandSchema($schema))->keyBy('key');

        foreach ($filters as $key => $expected) {
            $field = $fieldsByKey->get($key, []);
            $actual = $data[$key] ?? '';

            if (!$this->matchesSingleValueFilter(
                $actual,
                (string) ($expected['value'] ?? ''),
                $field['type'] ?? 'text',
                (string) ($expected['operator'] ?? 'eq'),
                (string) ($expected['value_to'] ?? '')
            )) {
                return false;
            }
        }

        return true;
    }

    private function matchesSingleValueFilter($actual, string $expected, string $type, string $operator = 'eq', string $expectedTo = ''): bool
    {
        $actualText = mb_strtolower(trim((string) $actual));
        $expectedText = mb_strtolower(trim($expected));
        $expectedToText = mb_strtolower(trim($expectedTo));

        if ($expectedText === '') {
            return true;
        }

        if ($operator === 'between' && $expectedToText !== '') {
            if ($type === 'number') {
                return is_numeric($actual) && (float) $actual >= (float) $expected && (float) $actual <= (float) $expectedTo;
            }

            return $actualText >= $expectedText && $actualText <= $expectedToText;
        }

        if ($operator === 'gt') {
            return $type === 'number'
                ? is_numeric($actual) && (float) $actual > (float) $expected
                : $actualText > $expectedText;
        }

        if ($operator === 'lt') {
            return $type === 'number'
                ? is_numeric($actual) && (float) $actual < (float) $expected
                : $actualText < $expectedText;
        }

        if ($operator === 'neq') {
            return $actualText !== $expectedText;
        }

        if ($operator === 'contains' && !in_array($type, ['list', 'template_list', 'date', 'time', 'directory', 'parent', 'number'], true)) {
            return mb_strpos($actualText, $expectedText) !== false;
        }

        if (in_array($type, ['list', 'template_list', 'date', 'time', 'directory', 'parent', 'number'], true)) {
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
            'directoryScripts' => url('/admin/directories/__ID__/scripts'),
            'templateLists' => route('admin.directories.template-lists.list'),
            'directoryImportCsv' => url('/admin/directories/__ID__/import-csv'),
            'directoryPrint' => url('/admin/directories/__ID__/print'),
            'directoryBarcodes' => url('/admin/directories/__ID__/barcodes'),
            'value' => url('/admin/directory-values/__ID__'),
            'valueRestore' => url('/admin/directory-values/__ID__/restore'),
            'script' => url('/admin/directory-scripts/__ID__'),
            'templateList' => url('/admin/directory-template-lists/__ID__'),
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

            if (($field['type'] ?? '') === 'parent') {
                unset($field['directory_id']);
                unset($field['directory_display_field']);
            }

            if (($field['type'] ?? '') === 'template_list' && !empty($field['template_list_id'])) {
                $templateList = DirectoryTemplateList::find((int) $field['template_list_id']);
                $field['template_list_ref'] = [
                    'code' => $templateList?->code,
                    'name' => $templateList?->name,
                ];
                unset($field['template_list_id']);
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

            if (($field['type'] ?? '') === 'parent') {
                unset($field['directory_id']);
                unset($field['directory_display_field']);
            }

            if (($field['type'] ?? '') === 'template_list') {
                $templateList = $this->findTemplateListByImportRef($field['template_list_ref'] ?? []);

                if (!$templateList) {
                    $fieldLabel = $field['label'] ?? ($field['key'] ?? 'поле');
                    throw ValidationException::withMessages([
                        'template_file' => ["Для поля «{$fieldLabel}» не найден связанный список шаблонов при импорте"],
                    ]);
                }

                $field['template_list_id'] = $templateList->id;
            }

            unset($field['directory_ref']);
            unset($field['template_list_ref']);

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

    private function findTemplateListByImportRef($ref): ?DirectoryTemplateList
    {
        if (!is_array($ref)) {
            return null;
        }

        $code = trim((string) ($ref['code'] ?? ''));
        $name = trim((string) ($ref['name'] ?? ''));

        if ($code !== '') {
            $templateList = DirectoryTemplateList::where('code', $code)->first();
            if ($templateList) {
                return $templateList;
            }
        }

        if ($name !== '') {
            return DirectoryTemplateList::where('name', $name)->first();
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
