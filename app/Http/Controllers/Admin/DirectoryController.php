<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
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
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:directories,name',
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                'unique:directories,code',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'division_ids' => [
                'nullable',
                'array',
            ],
            'division_ids.*' => [
                'exists:divisions,id',
            ],
        ]);

        $directory = DB::transaction(function () use ($validated) {
            $directory = Directory::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
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
                'division_ids' => $directory->divisions->pluck('id')->values(),
            ],
        ]);
    }

    public function update(Request $request, Directory $directory)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('directories', 'name')->ignore($directory->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('directories', 'code')->ignore($directory->id),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'division_ids' => [
                'nullable',
                'array',
            ],
            'division_ids.*' => [
                'exists:divisions,id',
            ],
        ]);

        DB::transaction(function () use ($directory, $validated) {
            $directory->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
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
            'items' => $values->items(),
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

    public function valueStore(Request $request, Directory $directory)
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $value = $directory->values()->create([
            'value' => $validated['value'],
            'code' => $validated['code'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Значение добавлено',
            'value' => $value,
        ]);
    }

    public function valueShow(DirectoryValue $value)
    {
        return response()->json([
            'success' => true,
            'value' => $value,
        ]);
    }

    public function valueUpdate(Request $request, DirectoryValue $value)
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $value->update([
            'value' => $validated['value'],
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
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
            ],
            'delimiter' => [
                'required',
                'string',
                'max:2',
            ],
            'has_header' => [
                'nullable',
                'boolean',
            ],
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

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($rowNumber === 1 && $hasHeader) {
                    continue;
                }

                $value = trim($row[0] ?? '');
                $code = trim($row[1] ?? '');
                $sortOrder = trim($row[2] ?? '');

                if ($value === '') {
                    $skipped++;
                    continue;
                }

                $exists = DirectoryValue::where('directory_id', $directory->id)
                    ->where('value', $value)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                DirectoryValue::create([
                    'directory_id' => $directory->id,
                    'value' => $value,
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
}
