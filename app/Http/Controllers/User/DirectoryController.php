<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Support\DirectorySchema;
use App\Support\DivisionTree;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index()
    {
        $directories = $this->getAccessibleDirectoriesQuery()
            ->orderBy('name')
            ->get();

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

        return response()->json([
            'success' => true,
            'items' => $query->get()->map(function (Directory $directory) {
                return [
                    'id' => $directory->id,
                    'name' => $directory->name,
                    'code' => $directory->code,
                    'description' => $directory->description,
                    'schema' => $directory->schema ?? [],
                ];
            })->values(),
        ]);
    }

    public function valuesList(Request $request, Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

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

        return response()->json([
            'success' => true,
            'directory' => [
                'id' => $directory->id,
                'name' => $directory->name,
                'description' => $directory->description,
                'schema' => $directory->schema ?? [],
            ],
            'items' => $query->get()->map(function (DirectoryValue $value) {
                return $this->serializeValue($value);
            })->values(),
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
}
