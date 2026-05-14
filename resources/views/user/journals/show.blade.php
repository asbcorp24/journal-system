@extends('user.layouts.app')

@section('title', $journal->name)

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="mb-2">
                <a href="{{ route('user.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Назад
                </a>
            </div>

            <h2 class="fw-bold mb-1">
                {{ $journal->name }}
            </h2>

            <div class="text-secondary">
                {{ $journal->description ?: 'Описание не указано' }}
            </div>
        </div>

        <button class="btn btn-primary" id="addEntryBtn">
            <i class="bi bi-plus-lg"></i>
            Добавить запись
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Дата от</label>
                    <input type="date" id="dateFrom" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Дата до</label>
                    <input type="date" id="dateTo" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Статус</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">Все статусы</option>
                        <option value="submitted">На проверке</option>
                        <option value="approved">Подтверждено</option>
                        <option value="rejected">Отклонено</option>
                    </select>
                </div>
                @if(session('user_role') === 'admin')
                    <div class="col-md-3">
                        <label class="form-label">Подразделение</label>
                        <select id="divisionFilter" class="form-select">
                            <option value="">Моё подразделение</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}">
                                    {{ $division->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Поиск по данным">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-info w-100" id="applyFilters">
                        Применить
                    </button>
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
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                    <tr id="entriesTableHead"></tr>
                    </thead>

                    <tbody id="entriesTableBody">
                    <tr>
                        <td class="text-center text-secondary py-5">
                            Загрузка...
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div class="text-secondary" id="paginationInfo"></div>
                <ul class="pagination mb-0" id="paginationLinks"></ul>
            </div>
        </div>
    </div>

    <div class="modal fade" id="entryModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="entryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="entryModalTitle">
                        Добавить запись
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="entryId">

                    @if(session('user_role') === 'admin')
                        <div class="mb-3">
                            <label class="form-label">Подразделение</label>
                            <select id="entryDivisionId" class="form-select">
                                <option value="">Не выбрано</option>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}">
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="row g-3" id="dynamicForm"></div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Сохранить запись
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        const journalId = {{ $journal->id }};
        const schema = @json($schema);
        const directoryValues = @json($directoryValues);
        const userRole = "{{ session('user_role') }}";

        let entryModal = new bootstrap.Modal(document.getElementById('entryModal'));
        let currentPage = 1;

        function statusBadge(status) {
            if (status === 'approved') {
                return '<span class="badge bg-success">Подтверждено</span>';
            }

            if (status === 'rejected') {
                return '<span class="badge bg-danger">Отклонено</span>';
            }

            return '<span class="badge bg-warning text-dark">На проверке</span>';
        }

        function getFieldLabel(key) {
            let field = schema.find(item => item.key === key);
            return field ? field.label : key;
        }

        function getDirectoryText(directoryId, valueId) {
            if (!directoryId || !valueId) {
                return '';
            }

            let list = directoryValues[directoryId] || [];

            let item = list.find(function (value) {
                return String(value.id) === String(valueId);
            });

            return item ? item.value : valueId;
        }

        function formatValue(field, value) {
            if (value === null || value === undefined || value === '') {
                return '<span class="text-secondary">—</span>';
            }

            if (field.type === 'directory') {
                return escapeHtml(getDirectoryText(field.directory_id, value));
            }

            if (field.type === 'directory_text') {
                return escapeHtml(value);
            }

            if (field.type === 'number') {
                return escapeHtml(value);
            }

            return escapeHtml(value);
        }

        function renderTableHead() {
            let html = `
            <th style="width:80px;">ID</th>
            <th style="width:130px;">Дата</th>
        `;

            schema.forEach(function (field) {
                html += `<th>${escapeHtml(field.label)}</th>`;
            });

            html += `
            <th>Пользователь</th>
            <th>Подразделение</th>
            <th>Статус</th>
  <th>Проверил</th>
   <th>Комментарий</th>
            <th class="text-end">Действия</th>
        `;

            $('#entriesTableHead').html(html);
        }

        function loadEntries(page = 1) {
            currentPage = page;

            $('#entriesTableBody').html(`
            <tr>
                <td colspan="${schema.length + 7}" class="text-center text-secondary py-5">
                    Загрузка...
                </td>
            </tr>
        `);

            $.ajax({
                url: `/journals/${journalId}/entries`,
                method: "GET",
                data: {
                    page: page,
                    date_from: $('#dateFrom').val(),
                    date_to: $('#dateTo').val(),
                    status: $('#statusFilter').val(),
                    search: $('#searchInput').val(),
                    division_id: $('#divisionFilter').length ? $('#divisionFilter').val() : ''
                },
                success: function (response) {
                    renderEntries(response.items);
                    renderPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderEntries(items) {
            let html = '';

            if (!items || items.length === 0) {
                $('#entriesTableBody').html(`
                <tr>
                    <td colspan="${schema.length + 6}" class="text-center text-secondary py-5">
                        Записи не найдены
                    </td>
                </tr>
            `);
                return;
            }

            items.forEach(function (entry) {
                html += `
                <tr>
                    <td>${entry.id}</td>
                    <td>${entry.entry_date ? entry.entry_date.substring(0, 10) : '—'}</td>
            `;

                schema.forEach(function (field) {
                    let value = entry.data ? entry.data[field.key] : null;

                    html += `<td>${formatValue(field, value)}</td>`;
                });

                html += `
                    <td>${entry.user ? escapeHtml(entry.user.name) : '—'}</td>
                    <td>${entry.division ? escapeHtml(entry.division.name) : '—'}</td>
             <td>${statusBadge(entry.status)}</td>

<td>
    ${entry.checker ? escapeHtml(entry.checker.name) : '<span class="text-secondary">—</span>'}
    ${entry.checked_at ? '<div class="text-secondary small">' + escapeHtml(entry.checked_at) + '</div>' : ''}
</td>

<td>
    ${entry.last_comment
                    ? `
            <div>${escapeHtml(entry.last_comment.comment)}</div>
            <div class="text-secondary small">
                ${entry.last_comment.user ? escapeHtml(entry.last_comment.user.name) : ''}
                ${entry.last_comment.created_at ? ' / ' + escapeHtml(entry.last_comment.created_at) : ''}
            </div>
        `
                    : '<span class="text-secondary">—</span>'
                }
</td>

<td class="text-end">
    ${actionButtons(entry)}
</td>
                </tr>
            `;
            });

            $('#entriesTableBody').html(html);
        }
        function canChangeStatusButtons(entry) {
            if (userRole === 'worker') {
                return '';
            }

            if (entry.status === 'approved') {
                return `
            <button class="btn btn-outline-warning reject-entry"
                    data-id="${entry.id}"
                    title="Отклонить">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
            }

            if (entry.status === 'rejected') {
                return `
            <button class="btn btn-outline-success approve-entry"
                    data-id="${entry.id}"
                    title="Подтвердить">
                <i class="bi bi-check-lg"></i>
            </button>
        `;
            }

            return `
        <button class="btn btn-outline-success approve-entry"
                data-id="${entry.id}"
                title="Подтвердить">
            <i class="bi bi-check-lg"></i>
        </button>

        <button class="btn btn-outline-warning reject-entry"
                data-id="${entry.id}"
                title="Отклонить">
            <i class="bi bi-x-lg"></i>
        </button>
    `;
        }
        function renderPagination(pagination) {
            let info = pagination.total > 0
                ? `Показано ${pagination.from}–${pagination.to} из ${pagination.total}`
                : 'Нет записей';

            $('#paginationInfo').text(info);

            if (!pagination || pagination.last_page <= 1) {
                $('#paginationLinks').html('');
                return;
            }

            let current = pagination.current_page;
            let last = pagination.last_page;
            let html = '';

            html += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link entry-page" href="#" data-page="${current - 1}">
                    Назад
                </a>
            </li>
        `;

            let start = Math.max(1, current - 2);
            let end = Math.min(last, current + 2);

            for (let i = start; i <= end; i++) {
                html += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link entry-page" href="#" data-page="${i}">
                        ${i}
                    </a>
                </li>
            `;
            }

            html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <a class="page-link entry-page" href="#" data-page="${current + 1}">
                    Вперёд
                </a>
            </li>
        `;

            $('#paginationLinks').html(html);
        }

        function renderDynamicForm(data = {}) {
            let html = '';

            schema.forEach(function (field) {
                let value = data[field.key] ?? '';
                let requiredMark = field.required ? '<span class="text-danger">*</span>' : '';
                let requiredAttr = field.required ? 'required' : '';

                html += `<div class="col-md-6">`;
                html += `<label class="form-label">${escapeHtml(field.label)} ${requiredMark}</label>`;

                if (field.type === 'string') {
                    html += `
                    <input type="text"
                           class="form-control journal-field"
                           data-key="${field.key}"
                           value="${escapeHtml(value)}"
                           ${requiredAttr}>
                `;
                }

                else if (field.type === 'number') {
                    html += `
                    <input type="number"
                           step="any"
                           class="form-control journal-field"
                           data-key="${field.key}"
                           value="${escapeHtml(value)}"
                           ${requiredAttr}>
                `;
                }

                else if (field.type === 'date') {
                    html += `
                    <input type="date"
                           class="form-control journal-field"
                           data-key="${field.key}"
                           value="${escapeHtml(value)}"
                           ${requiredAttr}>
                `;
                }

                else if (field.type === 'time') {
                    html += `
                    <input type="time"
                           class="form-control journal-field"
                           data-key="${field.key}"
                           value="${escapeHtml(value)}"
                           ${requiredAttr}>
                `;
                }

                else if (field.type === 'list') {
                    html += `
                    <select class="form-select journal-field"
                            data-key="${field.key}"
                            ${requiredAttr}>
                        <option value="">Выберите значение</option>
                `;

                    let options = field.options || [];

                    options.forEach(function (option) {
                        let selected = String(value) === String(option) ? 'selected' : '';

                        html += `
                        <option value="${escapeHtml(option)}" ${selected}>
                            ${escapeHtml(option)}
                        </option>
                    `;
                    });

                    html += `</select>`;
                }

                else if (field.type === 'directory' || field.type === 'directory_text') {
                    html += `
                    <select class="form-select journal-field"
                            data-key="${field.key}"
                            ${requiredAttr}>
                        <option value="">Выберите значение</option>
                `;

                    let values = directoryValues[field.directory_id] || [];

                    values.forEach(function (item) {
                        let selected = '';

                        if (field.type === 'directory') {
                            selected = String(value) === String(item.id) ? 'selected' : '';
                        } else {
                            selected = String(value) === String(item.value) ? 'selected' : '';
                        }

                        html += `
                        <option value="${item.id}" ${selected}>
                            ${escapeHtml(item.value)}
                        </option>
                    `;
                    });

                    html += `</select>`;
                }

                else if (field.type === 'calc') {
                    html += `
                    <input type="text"
                           class="form-control journal-field"
                           data-key="${field.key}"
                           value="${escapeHtml(value)}"
                           placeholder="${escapeHtml(field.formula || '')}">
                `;
                }

                else {
                    html += `
                    <input type="text"
                           class="form-control journal-field"
                           data-key="${field.key}"
                           value="${escapeHtml(value)}"
                           ${requiredAttr}>
                `;
                }

                html += `</div>`;
            });

            $('#dynamicForm').html(html);
        }

        function collectFormData() {
            let data = {};

            $('.journal-field').each(function () {
                let key = $(this).data('key');
                data[key] = $(this).val();
            });

            return data;
        }

        function clearEntryForm() {
            $('#entryId').val('');

            if ($('#entryDivisionId').length) {
                $('#entryDivisionId').val('');
            }

            renderDynamicForm({});
        }

        $('#addEntryBtn').on('click', function () {
            clearEntryForm();

            $('#entryModalTitle').text('Добавить запись');
            entryModal.show();
        });

        $('#entryForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#entryId').val();

            let url = id
                ? `/journals/${journalId}/entries/${id}`
                : `/journals/${journalId}/entries`;

            let payload = {
                data: collectFormData()
            };

            if ($('#entryDivisionId').length) {
                payload.division_id = $('#entryDivisionId').val();
            }

            $.ajax({
                url: url,
                method: "POST",
                data: payload,
                success: function (response) {
                    showToast(response.message, 'success');
                    entryModal.hide();
                    loadEntries(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-entry', function () {
            let id = $(this).data('id');

            clearEntryForm();

            $.ajax({
                url: `/journals/${journalId}/entries/${id}`,
                method: "GET",
                success: function (response) {
                    let entry = response.entry;

                    $('#entryModalTitle').text('Редактировать запись');
                    $('#entryId').val(entry.id);

                    if ($('#entryDivisionId').length) {
                        $('#entryDivisionId').val(entry.division_id);
                    }

                    renderDynamicForm(entry.data || {});
                    entryModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-entry', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить запись журнала?')) {
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/entries/${id}`,
                method: "DELETE",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadEntries(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#applyFilters').on('click', function () {
            loadEntries(1);
        });

        $('#resetFilters').on('click', function () {
            $('#dateFrom').val('');
            $('#dateTo').val('');
            $('#statusFilter').val('');
            $('#searchInput').val('');

            if ($('#divisionFilter').length) {
                $('#divisionFilter').val('');
            }

            loadEntries(1);
        });

        $('#searchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadEntries(1);
            }
        });

        $(document).on('click', '.entry-page', function (e) {
            e.preventDefault();

            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadEntries(page);
            }
        });
        $(document).on('change', '#divisionFilter', function () {
            loadEntries(1);
        });
        $(document).on('click', '.approve-entry', function () {
            let id = $(this).data('id');

            if (!confirm('Подтвердить запись?')) {
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/entries/${id}/approve`,
                method: "POST",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadEntries(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });
        $(document).on('click', '.reject-entry', function () {
            let id = $(this).data('id');

            let comment = prompt('Причина отклонения');

            if (comment === null) {
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/entries/${id}/reject`,
                method: "POST",
                data: {
                    comment: comment
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    loadEntries(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });
        function canEditEntry(entry) {
            if (userRole === 'worker') {
                if (entry.status === 'approved') {
                    return false;
                }

                // submitted и rejected можно редактировать
                return true;
            }

            return true;
        }

        function canDeleteEntry(entry) {
            if (userRole === 'worker') {
                if (entry.status === 'approved') {
                    return false;
                }

                if (entry.status === 'rejected') {
                    return false;
                }

                // submitted можно удалить
                return true;
            }

            return true;
        }

        function actionButtons(entry) {
            let html = `<div class="btn-group btn-group-sm">`;

            if (canEditEntry(entry)) {
                html += `
            <button class="btn btn-outline-info edit-entry"
                    data-id="${entry.id}"
                    title="Редактировать">
                <i class="bi bi-pencil"></i>
            </button>
        `;
            }

            html += canChangeStatusButtons(entry);

            if (canDeleteEntry(entry)) {
                html += `
            <button class="btn btn-outline-danger delete-entry"
                    data-id="${entry.id}"
                    title="Удалить">
                <i class="bi bi-trash"></i>
            </button>
        `;
            }

            html += `</div>`;

            return html;
        }
        renderTableHead();
        renderDynamicForm({});
        loadEntries();

    </script>
@endpush
