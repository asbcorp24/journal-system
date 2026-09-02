<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Directory;
use App\Models\DirectoryScript;
use App\Models\DirectoryTemplateList;
use App\Models\DirectoryValue;
use App\Models\SavedFilter;
use App\Models\UserFavorite;
use App\Support\DirectoryAccessScope;
use App\Support\DirectorySchema;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $templateLists = DirectorySchema::normalizeTemplateLists(
            DirectoryTemplateList::query()->with('creator')->orderBy('name')->get()
        );

        return view('user.directories.index', compact('directories', 'templateLists'));
    }

    public function list(Request $request)
    {
        $query = $this->getAccessibleDirectoriesQuery()->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $needle = '%' . $this->lowerSearchValue($search) . '%';

            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$needle]);
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
                    'schema' => $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []),
                    'table_settings' => $this->normalizeDirectoryTableSettings($directory->table_settings ?? []),
                    'scripts' => $this->serializeDirectoryScripts($directory->scripts()->where('is_active', true)->get()),
                    'is_favorite' => in_array((int) $directory->id, $favoriteIds, true),
                ];
            })->sortByDesc('is_favorite')->values(),
        ]);
    }

    public function valuesList(Request $request, Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

        $schema = $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []);
        $filters = $this->normalizeValueFilters($request->input('filters', []), $schema);
        $items = $this->buildDirectoryValuesCollection(
            $request,
            $directory,
            $schema,
            $filters,
            $request->boolean('show_deleted')
        );

        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'directory' => [
                    'id' => $directory->id,
                    'name' => $directory->name,
                'description' => $directory->description,
                'schema' => $schema,
                'table_settings' => $this->normalizeDirectoryTableSettings($directory->table_settings ?? []),
                'scripts' => $this->serializeDirectoryScripts($directory->scripts()->where('is_active', true)->get()),
                'filter_presets' => $this->serializeSavedFilters($directory),
                'template_lists' => DirectorySchema::normalizeTemplateLists(
                    DirectoryTemplateList::query()->with('creator')->orderBy('name')->get()
                ),
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
                'table_settings' => $this->normalizeDirectoryTableSettings($directory->table_settings ?? []),
                'scripts' => $this->serializeDirectoryScripts($directory->scripts()->where('is_active', true)->get()),
                'filter_presets' => $this->serializeSavedFilters($directory),
                'template_lists' => DirectorySchema::normalizeTemplateLists(
                    DirectoryTemplateList::query()->with('creator')->orderBy('name')->get()
                ),
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

        $schema = $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []);
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
                    'created_by' => $this->currentActorId(),
                    'updated_by' => $this->currentActorId(),
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

        $schema = $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []);
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

        [$recordData, $displayValue, $pendingUploads] = $this->validateDirectoryValuePayload($request, $directory);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            $this->deleteDirectoryImageFiles(array_keys($pendingUploads));
            throw $e;
        }

        $value->load(['directory', 'creator', 'updater', 'deleter']);
        $this->logDirectoryValueActivity('directory_value_created', $value, 'Добавлено значение справочника');

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

        [$recordData, $displayValue, $pendingUploads, $replacedFiles] = $this->validateDirectoryValuePayload($request, $value->directory, $value->id);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->storePendingDirectoryImages($pendingUploads);

        try {
            $value->update([
                'value' => $displayValue,
                'data' => $recordData,
                'code' => $validated['code'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
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
            'value' => $this->serializeValue($value->fresh()),
        ]);
    }

    public function destroyValue(DirectoryValue $value)
    {
        $value->load('directory');

        $this->ensureDirectoryAccess($value->directory);
        $this->ensureCanDeleteValues();

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

    public function restoreValue(DirectoryValue $value)
    {
        $value->load('directory');

        $this->ensureDirectoryAccess($value->directory);
        $this->ensureCanDeleteValues();

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
            'value' => $this->serializeValue($value),
        ]);
    }

    private function getAccessibleDirectoriesQuery()
    {
        $directoryIds = DirectoryAccessScope::accessibleDirectoryIdsForUser(
            (int) session('user_id'),
            session('user_role'),
            session('user_division_id') !== null ? (int) session('user_division_id') : null
        );

        return Directory::query()->whereIn('id', $directoryIds);
    }

    private function ensureDirectoryAccess(Directory $directory): void
    {
        $hasAccess = DirectoryAccessScope::userCanAccessDirectory(
            $directory,
            (int) session('user_id'),
            session('user_role'),
            session('user_division_id') !== null ? (int) session('user_division_id') : null
        );

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

    private function normalizeDirectoryTableSettings($settings): array
    {
        $settings = is_array($settings) ? $settings : [];

        return [
            'show_code' => !array_key_exists('show_code', $settings) || (bool) $settings['show_code'],
            'show_sort_order' => !array_key_exists('show_sort_order', $settings) || (bool) $settings['show_sort_order'],
            'show_status' => !array_key_exists('show_status', $settings) || (bool) $settings['show_status'],
        ];
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

    private function validateDirectoryValuePayload(Request $request, Directory $directory, ?int $ignoreId = null): array
    {
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

        $existingData = $ignoreId ? $this->findExistingData($directory, $ignoreId) : [];
        [$input, $pendingUploads, $replacedFiles] = $this->prepareDirectoryImageFields($request, $directory, $schema, $request->input('data', []), $existingData);
        $input = $this->applyAutoGeneratedQrFields($schema, $input, $existingData);
        $input = DirectorySchema::applyTemplateListValues($schema, $input, $existingData);
        $recordData = DirectorySchema::validateRecord($schema, $input);
        $this->validateParentSelfReference($schema, $recordData, $ignoreId);
        DirectorySchema::validateUniqueFields($schema, $recordData, $directory->values()->get(['id', 'data']), $ignoreId);
        $displayValue = DirectorySchema::resolveDisplayValue($schema, $recordData);

        return [$recordData, $displayValue, $pendingUploads, $replacedFiles];
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

    private function buildDirectoryValuesCollection(Request $request, Directory $directory, array $schema, array $filters, bool $showDeleted = false)
    {
        $query = $directory->values()
            ->with(['directory', 'creator', 'updater', 'deleter']);

        $this->applyValueSorting($query, $request->input('sort'));

        if ($showDeleted) {
            $query->withTrashed()->whereNotNull('deleted_at');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $needle = '%' . $this->lowerSearchValue($search) . '%';

            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(value) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
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
        $directory = $value->relationLoaded('directory') && $value->directory
            ? $value->directory
            : $value->directory()->first();
        $schema = $directory ? $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []) : [];
        $data = is_array($value->data) ? $value->data : [];

        return [
            'id' => $value->id,
            'value' => $value->value,
            'data' => $data,
            'image_urls' => $this->buildDirectoryImageUrls($schema, $data),
            'code' => $value->code,
            'sort_order' => $value->sort_order,
            'is_active' => (bool) $value->is_active,
            'created_at' => optional($value->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($value->updated_at)->format('Y-m-d H:i:s'),
            'deleted_at' => optional($value->deleted_at)->format('Y-m-d H:i:s'),
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
                $urls[$key] = $this->directoryImageUrl($fileName);
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

    private function directoryImageUrl(string $fileName): string
    {
        return asset('uploads/directory-images/' . ltrim($fileName, '/'));
    }

    private function serializeDirectoryScripts($scripts): array
    {
        return collect($scripts)->map(function (DirectoryScript $script) {
            return [
                'id' => $script->id,
                'name' => $script->name,
                'description' => $script->description,
                'code' => $script->code,
                'sort_order' => (int) $script->sort_order,
            ];
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

        foreach (DirectorySchema::expandSchema($schema) as $field) {
            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            $fieldValue = $value->data[$key] ?? '';
            $row[$key] = $fieldValue;

            if (($field['type'] ?? null) === 'directory') {
                $row[$key . '_display'] = $this->resolveDirectoryExportReferenceValue($key, $fieldValue, $referenceMap);
            } elseif (($field['type'] ?? null) === 'template_list') {
                $row[$key . '_display'] = DirectorySchema::formatFieldValue($field, $fieldValue);
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

        foreach (DirectorySchema::expandSchema($schema) as $field) {
            if (!empty($field['key'])) {
                $row[$field['key']] = '';

                if (($field['type'] ?? null) === 'directory') {
                    $row[$field['key'] . '_display'] = '';
                } elseif (($field['type'] ?? null) === 'template_list') {
                    $row[$field['key'] . '_display'] = '';
                }
            }
        }

        return $row;
    }

    private function buildDirectoryExportReferenceMap(array $schema, $items): array
    {
        $referenceIds = [];

        foreach (DirectorySchema::expandSchema($schema) as $field) {
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
        $schema = $this->prepareDirectorySchemaForRuntime($directory, $directory->schema ?? []);

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

    private function applyValueSorting($query, ?string $sort): void
    {
        switch ($sort) {
            case 'created_at_asc':
                $query->orderBy('created_at')->orderBy('id');
                break;
            case 'value_asc':
                $query->orderBy('value')->orderByDesc('id');
                break;
            case 'value_desc':
                $query->orderByDesc('value')->orderByDesc('id');
                break;
            case 'code_asc':
                $query->orderBy('code')->orderByDesc('id');
                break;
            case 'code_desc':
                $query->orderByDesc('code')->orderByDesc('id');
                break;
            case 'sort_order_asc':
                $query->orderBy('sort_order')->orderByDesc('id');
                break;
            case 'sort_order_desc':
                $query->orderByDesc('sort_order')->orderByDesc('id');
                break;
            default:
                // Keep the latest additions at the top until the user selects another order.
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
        }
    }

    private function lowerSearchValue(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
