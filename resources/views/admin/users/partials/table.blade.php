<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
        <tr>
            <th>ID</th>
            <th>ФИО</th>
            <th>Email</th>
            <th>Пароль</th>
            <th>Роль</th>
            <th>Подразделение</th>
            <th>Статус</th>
            <th class="text-end">Действия</th>
        </tr>
        </thead>

        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->id }}</td>

                <td>{{ $user->name }}</td>

                <td>{{ $user->email }}</td>
                <td>
                    <div class="input-group input-group-sm" style="min-width: 180px;">
                        <input type="password"
                               class="form-control form-control-sm bg-dark text-light border-secondary user-password-view"
                               value="{{ $user->decrypted_password }}"
                               readonly>

                        <button class="btn btn-outline-light btn-sm toggle-password"
                                type="button">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
                <td>
                    @if($user->role === 'admin')
                        <span class="badge bg-primary badge-role">Admin</span>
                    @elseif($user->role === 'foreman')
                        <span class="badge bg-warning text-dark badge-role">Foreman</span>
                    @else
                        <span class="badge bg-secondary badge-role">Worker</span>
                    @endif
                </td>

                <td>
                    {{ $user->division->name ?? '—' }}
                </td>

                <td>
                    @if($user->is_active)
                        <span class="badge bg-success">Активен</span>
                    @else
                        <span class="badge bg-danger">Заблокирован</span>
                    @endif
                </td>

                <td class="text-end">
                    <button class="btn btn-sm btn-outline-warning manage-user-permissions" data-id="{{ $user->id }}" data-name="{{ $user->name }}">
                        <i class="bi bi-shield-lock"></i>
                    </button>

                    <button class="btn btn-sm btn-outline-info edit-user" data-id="{{ $user->id }}">
                        <i class="bi bi-pencil"></i>
                    </button>

                    <button class="btn btn-sm btn-outline-danger delete-user" data-id="{{ $user->id }}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-secondary py-4">
                    Пользователи не найдены
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
