@extends('admin.layouts.app')

@section('title', 'Пользователи')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Пользователи</h2>
            <div class="text-secondary">
                Создание, редактирование и удаление пользователей системы
            </div>
        </div>

        <button class="btn btn-primary" id="addUserBtn">
            <i class="bi bi-plus-lg"></i>
            Добавить пользователя
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="Поиск по ФИО, email или роли">
                </div>

                <div class="col-md-3">
                    <select id="roleFilter" class="form-select">
                        <option value="">Все роли</option>
                        <option value="worker">Worker</option>
                        <option value="foreman">Foreman</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <select id="divisionFilter" class="form-select">
                        <option value="">Все подразделения</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}">
                                {{ $division->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-light w-100" id="resetFilters">
                        Сбросить
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="usersTable">
                <div class="text-center text-secondary py-5">
                    Загрузка...
                </div>
            </div>

            <div id="usersPagination" class="mt-3"></div>
        </div>
    </div>

    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">
                        Добавить пользователя
                    </h5>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="userId" name="id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ФИО</label>
                            <input type="text" name="name" id="name" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email / логин</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Пароль
                                <span class="text-secondary" id="passwordHint"></span>
                            </label>
                            <input type="password" name="password" id="password" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Роль</label>
                            <select name="role" id="role" class="form-select">
                                <option value="worker">Worker</option>
                                <option value="foreman">Foreman</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Подразделение</label>
                            <select name="division_id" id="division_id" class="form-select">
                                <option value="">Без подразделения</option>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}">
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">
                                    Пользователь активен
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="userPermissionsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Доступ к журналам</h5>
                        <div class="text-secondary small" id="permissionsUserTitle"></div>
                    </div>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="permissionsUserId">

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Подразделение</label>
                                    <select id="permissionDivisionId" class="form-select">
                                        <option value="">Выберите подразделение</option>
                                        @foreach($divisions as $division)
                                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Область доступа</label>
                                    <select id="permissionScope" class="form-select">
                                        <option value="division">Все журналы подразделения</option>
                                        <option value="journal">Только конкретный журнал</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Режим доступа</label>
                                    <select id="permissionAccessLevel" class="form-select">
                                        <option value="view">Только просмотр</option>
                                        <option value="full">Полный доступ</option>
                                    </select>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label">Журнал</label>
                                    <select id="permissionJournalTemplateId" class="form-select" disabled>
                                        <option value="">Выберите журнал</option>
                                        @foreach($journals as $journal)
                                            <option value="{{ $journal->id }}">{{ $journal->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4 d-flex align-items-end">
                                    <button class="btn btn-primary w-100" id="saveUserPermissionBtn">
                                        Добавить доступ
                                    </button>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3 mb-0">
                                Можно выдавать доступ ко всем журналам выбранного подразделения или только к одному журналу.
                                Доступы можно назначать и на другое подразделение.
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div id="userPermissionsTable" class="text-center text-secondary py-4">
                                Загрузка...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        let userModal = new bootstrap.Modal(document.getElementById('userModal'));
        let userPermissionsModal = new bootstrap.Modal(document.getElementById('userPermissionsModal'));
        let currentPageUrl = "{{ route('admin.users.list') }}";
        const journals = @json($journalOptions);

        function loadUsers(url = null) {
            if (!url) {
                url = "{{ route('admin.users.list') }}";
            }

            currentPageUrl = url;

            $.ajax({
                url: url,
                method: "GET",
                data: {
                    search: $('#searchInput').val(),
                    role: $('#roleFilter').val(),
                    division_id: $('#divisionFilter').val()
                },
                success: function (response) {
                    $('#usersTable').html(response.html);
                    $('#usersPagination').html(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function clearForm() {
            $('#userForm')[0].reset();
            $('#userId').val('');
            $('#is_active').prop('checked', true);
            $('#passwordHint').text('');
        }

        function renderUserPermissions(items) {
            if (!items.length) {
                $('#userPermissionsTable').html(`
                    <div class="text-center text-secondary py-4">
                        Для пользователя ещё не настроены персональные доступы к журналам.
                    </div>
                `);
                return;
            }

            let rows = items.map(function (item) {
                let scopeText = item.journal_template
                    ? `Журнал: ${escapeHtml(item.journal_template.name)}`
                    : 'Все журналы подразделения';

                let accessText = item.access_level === 'full'
                    ? '<span class="badge bg-success">Полный доступ</span>'
                    : '<span class="badge bg-secondary">Только просмотр</span>';

                return `
                    <tr>
                        <td>${escapeHtml(item.division ? item.division.name : '—')}</td>
                        <td>${scopeText}</td>
                        <td>${accessText}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger delete-user-permission" data-id="${item.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            $('#userPermissionsTable').html(`
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Подразделение</th>
                                <th>Область</th>
                                <th>Доступ</th>
                                <th class="text-end">Действия</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);
        }

        function loadUserPermissions() {
            let userId = $('#permissionsUserId').val();

            if (!userId) {
                return;
            }

            $.ajax({
                url: `/admin/users/${userId}/permissions`,
                method: 'GET',
                success: function (response) {
                    renderUserPermissions(response.permissions || []);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function syncPermissionJournalState() {
            let isJournalScope = $('#permissionScope').val() === 'journal';
            let divisionId = $('#permissionDivisionId').val();

            $('#permissionJournalTemplateId').prop('disabled', !isJournalScope);

            $('#permissionJournalTemplateId option').each(function () {
                if (!this.value) {
                    this.hidden = false;
                    return;
                }

                let journal = journals.find(item => String(item.id) === String(this.value));
                let visible = !divisionId || (journal && journal.division_ids.map(String).includes(String(divisionId)));
                this.hidden = !visible;
            });

            if (!isJournalScope) {
                $('#permissionJournalTemplateId').val('');
            }
        }

        $('#addUserBtn').on('click', function () {
            clearForm();

            $('#userModalTitle').text('Добавить пользователя');
            $('#passwordHint').text('');
            userModal.show();
        });

        $('#userForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#userId').val();

            let url = id
                ? "/admin/users/" + id
                : "{{ route('admin.users.store') }}";

            $.ajax({
                url: url,
                method: "POST",
                data: $(this).serialize(),
                success: function (response) {
                    showToast(response.message, 'success');
                    userModal.hide();
                    loadUsers(currentPageUrl);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-user', function () {
            let id = $(this).data('id');

            clearForm();

            $.ajax({
                url: "/admin/users/" + id,
                method: "GET",
                success: function (response) {
                    let user = response.user;

                    $('#userModalTitle').text('Редактировать пользователя');

                    $('#userId').val(user.id);
                    $('#name').val(user.name);
                    $('#email').val(user.email);
                    $('#role').val(user.role);
                    $('#division_id').val(user.division_id);
                    $('#is_active').prop('checked', user.is_active);

                    $('#password').val('');
                    $('#passwordHint').text('(оставьте пустым, если не менять)');

                    userModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-user', function () {
            if (!confirm('Удалить пользователя?')) {
                return;
            }

            let id = $(this).data('id');

            $.ajax({
                url: "/admin/users/" + id,
                method: "DELETE",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadUsers(currentPageUrl);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.pagination a', function (e) {
            e.preventDefault();

            let url = $(this).attr('href');

            if (url) {
                loadUsers(url);
            }
        });

        $('#searchInput').on('keyup', function () {
            loadUsers();
        });

        $('#roleFilter, #divisionFilter').on('change', function () {
            loadUsers();
        });

        $('#resetFilters').on('click', function () {
            $('#searchInput').val('');
            $('#roleFilter').val('');
            $('#divisionFilter').val('');
            loadUsers();
        });

        $(document).on('click', '.manage-user-permissions', function () {
            let userId = $(this).data('id');
            let userName = $(this).data('name');

            $('#permissionsUserId').val(userId);
            $('#permissionsUserTitle').text(userName);
            $('#permissionDivisionId').val('');
            $('#permissionScope').val('division');
            $('#permissionAccessLevel').val('view');
            $('#permissionJournalTemplateId').val('');
            syncPermissionJournalState();
            $('#userPermissionsTable').html('<div class="text-center text-secondary py-4">Загрузка...</div>');

            userPermissionsModal.show();
            loadUserPermissions();
        });

        $('#permissionScope, #permissionDivisionId').on('change', function () {
            syncPermissionJournalState();
        });

        $('#saveUserPermissionBtn').on('click', function () {
            let userId = $('#permissionsUserId').val();

            $.ajax({
                url: `/admin/users/${userId}/permissions`,
                method: 'POST',
                data: {
                    division_id: $('#permissionDivisionId').val(),
                    scope: $('#permissionScope').val(),
                    journal_template_id: $('#permissionJournalTemplateId').val(),
                    access_level: $('#permissionAccessLevel').val()
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    loadUserPermissions();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-user-permission', function () {
            if (!confirm('Удалить этот доступ?')) {
                return;
            }

            let userId = $('#permissionsUserId').val();
            let permissionId = $(this).data('id');

            $.ajax({
                url: `/admin/users/${userId}/permissions/${permissionId}`,
                method: 'DELETE',
                success: function (response) {
                    showToast(response.message, 'success');
                    loadUserPermissions();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.toggle-password', function () {
            let input = $(this).closest('.input-group').find('.user-password-view');
            let icon = $(this).find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });

        syncPermissionJournalState();
        loadUsers();
    </script>
@endpush
