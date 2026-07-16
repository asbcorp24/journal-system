<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Support\DirectorySchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DirectoryController extends Controller
{
    public function index()
    {
        $divisions = Division::orderBy('name')->get();

        return view('admin.directories.index', compact('divisions'));
    }

    public function list(Request $request)
    {
        $query = Directory::withCount('values')
            ->with('divisions')
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
        $directory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Справочник удалён',
        ]);
    }

    public function valuesList(Request $request, Directory $directory)
    {
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

        return response()->json([
            'success' => true,
            'value' => $this->serializeDirectoryValue($value),
        ]);
    }

    public function valueUpdate(Request $request, DirectoryValue $value)
    {
        $value->load('directory');
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
        $value->delete();

        return response()->json([
            'success' => true,
            'message' => 'Значение удалено',
        ]);
    }

    public function importCsv(Request $request, Directory $directory)
    {
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
}
