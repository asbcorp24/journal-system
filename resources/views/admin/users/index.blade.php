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

@endsection

@push('scripts')
    <script>
        let userModal = new bootstrap.Modal(document.getElementById('userModal'));
        let currentPageUrl = "{{ route('admin.users.list') }}";

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
        loadUsers();
    </script>
@endpush
