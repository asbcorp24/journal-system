<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use App\Services\UserPasswordCipher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $divisions = Division::orderBy('name')->get();

        return view('admin.users.index', compact('divisions'));
    }

    public function list(Request $request)
    {
        $query = User::with('division')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        $users = $query->paginate(10);

        return response()->json([
            'success' => true,
            'html' => view('admin.users.partials.table', compact('users'))->render(),
            'pagination' => view('admin.users.partials.pagination', compact('users'))->render(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255|email|unique:users,email',
            'password' => 'required|string|min:3',
            'role' => ['required', Rule::in(['worker', 'foreman', 'admin'])],
            'division_id' => 'nullable|exists:divisions,id',
            'is_active' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => UserPasswordCipher::encryptPassword($validated['password']),
            'role' => $validated['role'],
            'division_id' => $validated['division_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Пользователь создан',
            'user' => $user,
        ]);
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'division_id' => $user->division_id,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'max:255',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:3',
            'role' => ['required', Rule::in(['worker', 'foreman', 'admin'])],
            'division_id' => 'nullable|exists:divisions,id',
            'is_active' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'division_id' => $validated['division_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if (!empty($validated['password'])) {
            $data['password'] = UserPasswordCipher::encryptPassword($validated['password']);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Пользователь обновлён',
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Пользователь удалён',
        ]);
    }
}
