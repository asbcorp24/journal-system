<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\JournalTemplate;
use App\Models\User;
use App\Models\UserJournalPermission;
use App\Services\UserPasswordCipher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $divisions = Division::orderBy('name')->get();
        $journals = JournalTemplate::query()
            ->with('divisions')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $journalOptions = $journals->map(function (JournalTemplate $journal) {
            return [
                'id' => $journal->id,
                'name' => $journal->name,
                'division_ids' => $journal->divisions->pluck('id')->values()->all(),
            ];
        })->values()->all();

        return view('admin.users.index', compact('divisions', 'journals', 'journalOptions'));
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
        $users->getCollection()->transform(function ($user) {
            $user->decrypted_password = UserPasswordCipher::decryptPassword($user->password);
            return $user;
        });

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
                'decrypted_password' => UserPasswordCipher::decryptPassword($user->password),
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

    public function permissions(User $user)
    {
        $permissions = $user->journalPermissions()
            ->with(['division', 'journalTemplate'])
            ->orderBy('division_id')
            ->orderBy('journal_template_id')
            ->get();

        return response()->json([
            'success' => true,
            'permissions' => $permissions,
        ]);
    }

    public function storePermission(Request $request, User $user)
    {
        $validated = $request->validate([
            'division_id' => ['required', 'exists:divisions,id'],
            'scope' => ['required', Rule::in(['division', 'journal'])],
            'journal_template_id' => ['nullable', 'exists:journal_templates,id'],
            'access_level' => ['required', Rule::in([
                UserJournalPermission::ACCESS_VIEW,
                UserJournalPermission::ACCESS_FULL,
            ])],
        ]);

        $journalTemplateId = $validated['scope'] === 'journal'
            ? (int) ($validated['journal_template_id'] ?? 0)
            : null;

        if ($validated['scope'] === 'journal' && !$journalTemplateId) {
            return response()->json([
                'success' => false,
                'message' => 'Выберите конкретный журнал.',
            ], 422);
        }

        if ($journalTemplateId) {
            $isAttached = JournalTemplate::query()
                ->whereKey($journalTemplateId)
                ->whereHas('divisions', function ($query) use ($validated) {
                    $query->where('divisions.id', $validated['division_id']);
                })
                ->exists();

            if (!$isAttached) {
                return response()->json([
                    'success' => false,
                    'message' => 'Выбранный журнал не привязан к указанному подразделению.',
                ], 422);
            }
        }

        $permission = UserJournalPermission::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'division_id' => $validated['division_id'],
                'journal_template_id' => $journalTemplateId,
            ],
            [
                'access_level' => $validated['access_level'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Доступ к журналам сохранён.',
            'permission' => $permission->load(['division', 'journalTemplate']),
        ]);
    }

    public function destroyPermission(User $user, UserJournalPermission $permission)
    {
        if ((int) $permission->user_id !== (int) $user->id) {
            abort(404);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Доступ удалён.',
        ]);
    }
}
