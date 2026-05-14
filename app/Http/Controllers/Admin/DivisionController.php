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
        return view('admin.divisions.index');
    }

    public function list(Request $request)
    {
        $query = Division::query()->orderByDesc('id');

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
        ]);

        $division = Division::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Подразделение создано',
            'division' => $division,
        ]);
    }

    public function show(Division $division)
    {
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
        ]);

        $division->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Подразделение обновлено',
            'division' => $division,
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

        $division->delete();

        return response()->json([
            'success' => true,
            'message' => 'Подразделение удалено',
        ]);
    }
}
