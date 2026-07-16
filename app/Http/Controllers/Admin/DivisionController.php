<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DivisionController extends Controller
{
    public function index()
    {
        $divisionOptions = $this->getDivisionOptions();

        return view('admin.divisions.index', compact('divisionOptions'));
    }

    public function list(Request $request)
    {
        $query = Division::with('parent')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $divisions = $query->paginate(10);

        return response()->json([
            'success' => true,
            'items' => $divisions->items(),
            'division_options' => $this->getDivisionOptions(),
            'pagination' => [
                'current_page' => $divisions->currentPage(),
                'last_page' => $divisions->lastPage(),
                'per_page' => $divisions->perPage(),
                'total' => $divisions->total(),
                'from' => $divisions->firstItem(),
                'to' => $divisions->lastItem(),
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
                'unique:divisions,name',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'parent_id' => [
                'nullable',
                'exists:divisions,id',
            ],
        ]);

        $this->ensureValidParent(null, $validated['parent_id'] ?? null);

        $division = Division::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
        ])->load('parent');

        return response()->json([
            'success' => true,
            'message' => 'Подразделение создано',
            'division' => $division,
        ]);
    }

    public function show(Division $division)
    {
        $division->load('parent');

        return response()->json([
            'success' => true,
            'division' => $division,
        ]);
    }

    public function update(Request $request, Division $division)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('divisions', 'name')->ignore($division->id),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'parent_id' => [
                'nullable',
                'exists:divisions,id',
            ],
        ]);

        $this->ensureValidParent($division, $validated['parent_id'] ?? null);

        $division->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Подразделение обновлено',
            'division' => $division->load('parent'),
        ]);
    }

    public function destroy(Division $division)
    {
        if ($division->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить подразделение, к которому привязаны пользователи',
            ], 422);
        }

        if ($division->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить подразделение, у него есть дочерние подразделения',
            ], 422);
        }

        $division->delete();

        return response()->json([
            'success' => true,
            'message' => 'Подразделение удалено',
        ]);
    }

    private function ensureValidParent(?Division $division, $parentId): void
    {
        if (!$parentId) {
            return;
        }

        if ($division && (int) $division->id === (int) $parentId) {
            abort(response()->json([
                'success' => false,
                'message' => 'Отдел не может быть родителем самого себя',
            ], 422));
        }

        if (!$division) {
            return;
        }

        $currentParentId = $parentId;

        while ($currentParentId) {
            if ((int) $currentParentId === (int) $division->id) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Нельзя выбрать дочерний отдел в качестве родителя',
                ], 422));
            }

            $currentParentId = Division::whereKey($currentParentId)->value('parent_id');
        }
    }

    private function getDivisionOptions()
    {
        return Division::orderBy('name')
            ->get(['id', 'name', 'parent_id']);
    }
}
