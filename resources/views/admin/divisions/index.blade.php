@extends('admin.layouts.app')

@section('title', 'Подразделения')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Подразделения</h2>
            <div class="text-secondary">
                Создание, редактирование и удаление подразделений предприятия
            </div>
        </div>

        <button class="btn btn-primary" id="addDivisionBtn">
            <i class="bi bi-plus-lg"></i>
            Добавить подразделение
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Поиск по названию или описанию">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-info w-100" id="searchBtn">
                        Найти
                    </button>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-light w-100" id="resetBtn">
                        Сбросить
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Название</th>
                        <th>Родительский отдел</th>
                        <th>Описание</th>
                        <th style="width: 170px;">Дата создания</th>
                        <th style="width: 130px;" class="text-end">Действия</th>
                    </tr>
                    </thead>

                    <tbody id="divisionsTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-5">
                            Загрузка...
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-secondary" id="paginationInfo"></div>

                <nav>
                    <ul class="pagination mb-0" id="paginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="modal fade" id="divisionModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="divisionForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="divisionModalTitle">
                        Добавить подразделение
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="divisionId" name="id">

                    <div class="mb-3">
                        <label class="form-label">Название подразделения</label>
                        <input type="text"
                               name="name"
                               id="name"
                               class="form-control"
                               placeholder="Например: Цех №1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Родительский отдел</label>
                        <select name="parent_id" id="parent_id" class="form-select">
                            <option value="">Без родителя</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <textarea name="description"
                                  id="description"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Краткое описание подразделения"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
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
        let divisionModal = new bootstrap.Modal(document.getElementById('divisionModal'));
        let currentPage = 1;
        let divisionOptions = @json($divisionOptions);

        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }

            return $('<div>').text(text).html();
        }

        function formatDate(dateString) {
            if (!dateString) {
                return '—';
            }

            let date = new Date(dateString);

            if (isNaN(date.getTime())) {
                return dateString;
            }

            return date.toLocaleString('ru-RU');
        }

        function renderParentOptions(currentId = null, selectedId = null) {
            let html = '<option value="">Без родителя</option>';

            divisionOptions.forEach(function (item) {
                if (currentId && String(item.id) === String(currentId)) {
                    return;
                }

                let selected = selectedId && String(item.id) === String(selectedId) ? 'selected' : '';
                html += `<option value="${item.id}" ${selected}>${escapeHtml(item.name)}</option>`;
            });

            $('#parent_id').html(html).trigger('change');
        }

        function loadDivisions(page = 1) {
            currentPage = page;

            $('#divisionsTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">
                        Загрузка...
                    </td>
                </tr>
            `);

            $.ajax({
                url: "{{ route('admin.divisions.list') }}",
                method: "GET",
                data: {
                    page: page,
                    search: $('#searchInput').val()
                },
                success: function (response) {
                    divisionOptions = response.division_options || [];
                    renderTable(response.items);
                    renderPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderTable(items) {
            let html = '';

            if (!items || items.length === 0) {
                html = `
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-5">
                            Подразделения не найдены
                        </td>
                    </tr>
                `;

                $('#divisionsTableBody').html(html);
                return;
            }

            items.forEach(function (item) {
                html += `
                    <tr>
                        <td>${item.id}</td>

                        <td>
                            <div class="fw-semibold">
                                ${escapeHtml(item.name)}
                            </div>
                        </td>

                        <td>
                            ${item.parent ? escapeHtml(item.parent.name) : '<span class="text-secondary">—</span>'}
                        </td>

                        <td>
                            ${item.description ? escapeHtml(item.description) : '<span class="text-secondary">—</span>'}
                        </td>

                        <td>
                            ${formatDate(item.created_at)}
                        </td>

                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info edit-division"
                                    data-id="${item.id}">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-outline-danger delete-division"
                                    data-id="${item.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            $('#divisionsTableBody').html(html);
        }

        function renderPagination(pagination) {
            let info = '';

            if (pagination.total > 0) {
                info = `Показано ${pagination.from}–${pagination.to} из ${pagination.total}`;
            } else {
                info = 'Нет записей';
            }

            $('#paginationInfo').text(info);

            let html = '';

            if (pagination.last_page <= 1) {
                $('#paginationLinks').html('');
                return;
            }

            let current = pagination.current_page;
            let last = pagination.last_page;

            html += `
                <li class="page-item ${current === 1 ? 'disabled' : ''}">
                    <a class="page-link pagination-page" href="#" data-page="${current - 1}">
                        Назад
                    </a>
                </li>
            `;

            let start = Math.max(1, current - 2);
            let end = Math.min(last, current + 2);

            if (start > 1) {
                html += `
                    <li class="page-item">
                        <a class="page-link pagination-page" href="#" data-page="1">1</a>
                    </li>
                `;

                if (start > 2) {
                    html += `
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    `;
                }
            }

            for (let i = start; i <= end; i++) {
                html += `
                    <li class="page-item ${i === current ? 'active' : ''}">
                        <a class="page-link pagination-page" href="#" data-page="${i}">
                            ${i}
                        </a>
                    </li>
                `;
            }

            if (end < last) {
                if (end < last - 1) {
                    html += `
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    `;
                }

                html += `
                    <li class="page-item">
                        <a class="page-link pagination-page" href="#" data-page="${last}">
                            ${last}
                        </a>
                    </li>
                `;
            }

            html += `
                <li class="page-item ${current === last ? 'disabled' : ''}">
                    <a class="page-link pagination-page" href="#" data-page="${current + 1}">
                        Вперёд
                    </a>
                </li>
            `;

            $('#paginationLinks').html(html);
        }

        function clearForm() {
            $('#divisionForm')[0].reset();
            $('#divisionId').val('');
            renderParentOptions();
        }

        $('#addDivisionBtn').on('click', function () {
            clearForm();
            $('#divisionModalTitle').text('Добавить подразделение');
            divisionModal.show();
        });

        $('#divisionForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#divisionId').val();
            let url = id
                ? "/admin/divisions/" + id
                : "{{ route('admin.divisions.store') }}";

            $.ajax({
                url: url,
                method: "POST",
                data: $(this).serialize(),
                success: function (response) {
                    showToast(response.message, 'success');
                    divisionModal.hide();
                    loadDivisions(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-division', function () {
            let id = $(this).data('id');

            clearForm();

            $.ajax({
                url: "/admin/divisions/" + id,
                method: "GET",
                success: function (response) {
                    let division = response.division;

                    $('#divisionModalTitle').text('Редактировать подразделение');
                    $('#divisionId').val(division.id);
                    $('#name').val(division.name);
                    renderParentOptions(division.id, division.parent_id);
                    $('#description').val(division.description);

                    divisionModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-division', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить подразделение?')) {
                return;
            }

            $.ajax({
                url: "/admin/divisions/" + id,
                method: "DELETE",
                success: function (response) {
                    showToast(response.message, 'success');

                    if ($('#divisionsTableBody tr').length === 1 && currentPage > 1) {
                        loadDivisions(currentPage - 1);
                    } else {
                        loadDivisions(currentPage);
                    }
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.pagination-page', function (e) {
            e.preventDefault();

            let page = parseInt($(this).data('page'));

            if (!page || page < 1) {
                return;
            }

            loadDivisions(page);
        });

        $('#searchBtn').on('click', function () {
            loadDivisions(1);
        });

        $('#searchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadDivisions(1);
            }
        });

        $('#resetBtn').on('click', function () {
            $('#searchInput').val('');
            loadDivisions(1);
        });

        renderParentOptions();
        loadDivisions();
    </script>
@endpush
