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

        <div class="d-flex gap-2">
            <button class="btn btn-outline-light" id="printJournalBtn">
                <i class="bi bi-printer"></i>
                Печать
            </button>

            <button class="btn btn-primary" id="addEntryBtn">
                <i class="bi bi-plus-lg"></i>
                Добавить запись
            </button>
        </div>
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
                    <div class="mt-3">
                        <label class="form-label">Комментарий к изменению</label>
                        <textarea id="changeComment"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Укажите, что было исправлено"></textarea>
                    </div>
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
    <div class="modal fade" id="directoryValueModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="directoryValueForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="directoryValueModalTitle">Add value</h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="directoryValueFieldKey">
                    <input type="hidden" id="directoryValueDirectoryId">

                    <label class="form-label" for="directoryValueInput">Value</label>
                    <input type="text"
                           class="form-control"
                           id="directoryValueInput"
                           maxlength="255"
                           required>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="commentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Комментарии к записи
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="commentsEntryId">
                    <input type="hidden" id="commentParentId">

                    <div id="commentsTree" class="mb-4"></div>

                    <div class="card">
                        <div class="card-body">
                            <div class="mb-2 fw-bold" id="commentFormTitle">
                                Новый комментарий
                            </div>

                            <textarea id="newCommentText"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Введите комментарий"></textarea>

                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-primary" id="sendCommentBtn">
                                    Отправить
                                </button>

                                <button class="btn btn-outline-light d-none" id="cancelReplyBtn">
                                    Отменить ответ
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="logsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        История изменений записи
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="logsEntryId">

                    <div id="logsContainer">
                        <div class="text-center text-secondary py-4">
                            Загрузка...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const journalId = {{ $journal->id }};
        const schema = @json($schema);
        const directoryValues = @json($directoryValues);
        const userRole = "{{ session('user_role') }}";
        const canManageDirectoryValues = userRole !== 'worker';

        let entryModal = new bootstrap.Modal(document.getElementById('entryModal'));
        let directoryValueModal = new bootstrap.Modal(document.getElementById('directoryValueModal'));
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

        function upsertDirectoryValue(directoryId, item) {
            if (!directoryValues[directoryId]) {
                directoryValues[directoryId] = [];
            }

            let index = directoryValues[directoryId].findIndex(function (existingItem) {
                return String(existingItem.id) === String(item.id);
            });

            if (index === -1) {
                directoryValues[directoryId].push(item);
            } else {
                directoryValues[directoryId][index] = item;
            }

            directoryValues[directoryId].sort(function (a, b) {
                let sortA = Number(a.sort_order || 0);
                let sortB = Number(b.sort_order || 0);

                if (sortA !== sortB) {
                    return sortA - sortB;
                }

                return String(a.value || '').localeCompare(String(b.value || ''));
            });
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
                    html += `<div class="input-group">`;
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

                    if (canManageDirectoryValues) {
                        html += `
                        <button type="button"
                                class="btn btn-outline-secondary add-directory-value-btn"
                                data-field-key="${field.key}"
                                data-directory-id="${field.directory_id}"
                                data-field-label="${escapeHtml(field.label)}"
                                title="Add value">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    `;
                    }

                    html += `</div>`;
                }

                else if (field.type === 'calc') {
                    html += `
        <input type="number"
               step="any"
               class="form-control journal-field calc-field"
               data-key="${field.key}"
               data-formula="${escapeHtml(field.formula || '')}"
               value="${escapeHtml(value)}"
               placeholder="${escapeHtml(field.formula || '')}"
               readonly>

        <div class="text-secondary small mt-1">
            Формула: ${escapeHtml(field.formula || '')}
        </div>
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
            recalculateCalcFields();
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
            $('#changeComment').val('');
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
                data: collectFormData(),
                change_comment: $('#changeComment').val()
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

        $(document).on('click', '.add-directory-value-btn', function () {
            $('#directoryValueFieldKey').val($(this).data('field-key'));
            $('#directoryValueDirectoryId').val($(this).data('directory-id'));
            $('#directoryValueInput').val('');

            let fieldLabel = $(this).data('field-label') || 'field';
            $('#directoryValueModalTitle').text(`Add value: ${fieldLabel}`);

            directoryValueModal.show();
        });

        $('#directoryValueForm').on('submit', function (e) {
            e.preventDefault();

            let fieldKey = $('#directoryValueFieldKey').val();
            let directoryId = $('#directoryValueDirectoryId').val();
            let value = $('#directoryValueInput').val().trim();

            if (!fieldKey || !directoryId) {
                showToast('Directory is not selected', 'danger');
                return;
            }

            if (!value) {
                showToast('Enter value', 'warning');
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/directories/${directoryId}/values`,
                method: "POST",
                data: {
                    value: value
                },
                success: function (response) {
                    upsertDirectoryValue(directoryId, response.value);

                    let select = $(`.journal-field[data-key="${fieldKey}"]`);

                    if (select.length) {
                        if (select.find(`option[value="${response.value.id}"]`).length === 0) {
                            select.append(`
                                <option value="${response.value.id}">
                                    ${escapeHtml(response.value.value)}
                                </option>
                            `);
                        } else {
                            select.find(`option[value="${response.value.id}"]`).text(response.value.value);
                        }

                        select.val(String(response.value.id)).trigger('change');
                    }

                    showToast(response.message, 'success');
                    directoryValueModal.hide();
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
            html += `
    <button class="btn btn-outline-light comments-entry"
            data-id="${entry.id}"
            title="Комментарии">
        <i class="bi bi-chat-dots"></i>
    </button>
`;
            html += `
    <button class="btn btn-outline-secondary logs-entry"
            data-id="${entry.id}"
            title="История изменений">
        <i class="bi bi-clock-history"></i>
    </button>
`;

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
        $('#printJournalBtn').on('click', function () {
            let params = new URLSearchParams();

            if ($('#dateFrom').val()) {
                params.append('date_from', $('#dateFrom').val());
            }

            if ($('#dateTo').val()) {
                params.append('date_to', $('#dateTo').val());
            }

            if ($('#statusFilter').val()) {
                params.append('status', $('#statusFilter').val());
            }

            if ($('#searchInput').val()) {
                params.append('search', $('#searchInput').val());
            }

            if ($('#divisionFilter').length && $('#divisionFilter').val()) {
                params.append('division_id', $('#divisionFilter').val());
            }

            let url = `/journals/${journalId}/print`;

            if (params.toString()) {
                url += '?' + params.toString();
            }

            window.open(url, '_blank');
        });
        renderTableHead();
        renderDynamicForm({});
        loadEntries();
        let commentsModal = new bootstrap.Modal(document.getElementById('commentsModal'));

        function renderCommentsTree(comments, level = 0) {
            let html = '';

            if (!comments || comments.length === 0) {
                if (level === 0) {
                    return `
                <div class="text-secondary text-center py-4">
                    Комментариев пока нет
                </div>
            `;
                }

                return '';
            }

            comments.forEach(function (comment) {
                html += `
            <div class="border rounded p-3 mb-2" style="margin-left:${level * 24}px; border-color:#334155 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold">
                            ${comment.user ? escapeHtml(comment.user.name) : 'Пользователь'}
                        </div>

                        <div class="text-secondary small">
                            ${escapeHtml(comment.created_at || '')}
                            ${comment.edited_at ? ' / изменено: ' + escapeHtml(comment.edited_at) : ''}
                        </div>
                    </div>

                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-light reply-comment"
                                data-id="${comment.id}"
                                data-user="${comment.user ? escapeHtml(comment.user.name) : ''}">
                            Ответить
                        </button>

                        <button class="btn btn-outline-info edit-comment"
                                data-id="${comment.id}"
                                data-text="${escapeHtml(comment.comment)}">
                            Редактировать
                        </button>
                    </div>
                </div>

                <div class="mt-2">
                    ${escapeHtml(comment.comment)}
                </div>

                ${comment.editor ? `
                    <div class="text-secondary small mt-2">
                        Редактировал: ${escapeHtml(comment.editor.name)}
                    </div>
                ` : ''}

                <div class="mt-3">
                    ${renderCommentsTree(comment.replies || [], level + 1)}
                </div>
            </div>
        `;
            });

            return html;
        }

        function loadEntryComments(entryId) {
            $('#commentsTree').html(`
        <div class="text-center text-secondary py-4">
            Загрузка...
        </div>
    `);

            $.ajax({
                url: `/journals/${journalId}/entries/${entryId}/comments`,
                method: "GET",
                success: function (response) {
                    $('#commentsTree').html(renderCommentsTree(response.comments));
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        $(document).on('click', '.comments-entry', function () {
            let entryId = $(this).data('id');

            $('#commentsEntryId').val(entryId);
            $('#commentParentId').val('');
            $('#newCommentText').val('');
            $('#commentFormTitle').text('Новый комментарий');
            $('#cancelReplyBtn').addClass('d-none');

            commentsModal.show();

            loadEntryComments(entryId);
        });

        $(document).on('click', '.reply-comment', function () {
            let commentId = $(this).data('id');
            let userName = $(this).data('user');

            $('#commentParentId').val(commentId);
            $('#newCommentText').val('');
            $('#commentFormTitle').text('Ответ на комментарий: ' + userName);
            $('#cancelReplyBtn').removeClass('d-none');
        });

        $('#cancelReplyBtn').on('click', function () {
            $('#commentParentId').val('');
            $('#newCommentText').val('');
            $('#commentFormTitle').text('Новый комментарий');
            $('#cancelReplyBtn').addClass('d-none');
        });

        $('#sendCommentBtn').on('click', function () {
            let entryId = $('#commentsEntryId').val();
            let comment = $('#newCommentText').val().trim();
            let parentId = $('#commentParentId').val();

            if (!comment) {
                showToast('Введите комментарий', 'warning');
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/entries/${entryId}/comments`,
                method: "POST",
                data: {
                    comment: comment,
                    parent_id: parentId
                },
                success: function (response) {
                    showToast(response.message, 'success');

                    $('#newCommentText').val('');
                    $('#commentParentId').val('');
                    $('#commentFormTitle').text('Новый комментарий');
                    $('#cancelReplyBtn').addClass('d-none');

                    loadEntryComments(entryId);
                    loadEntries(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });
        $(document).on('click', '.edit-comment', function () {
            let commentId = $(this).data('id');
            let oldText = $(this).data('text');
            let entryId = $('#commentsEntryId').val();

            let newText = prompt('Изменить комментарий', oldText);

            if (newText === null) {
                return;
            }

            newText = newText.trim();

            if (!newText) {
                showToast('Комментарий не может быть пустым', 'warning');
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/entries/${entryId}/comments/${commentId}`,
                method: "POST",
                data: {
                    comment: newText
                },
                success: function (response) {
                    showToast(response.message, 'success');

                    loadEntryComments(entryId);
                    loadEntries(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });
        let logsModal = new bootstrap.Modal(document.getElementById('logsModal'));

        function actionLabel(action) {
            const map = {
                created: 'Создание записи',
                updated: 'Изменение записи',
                deleted: 'Удаление записи',
                status_changed: 'Смена статуса',
                comment_added: 'Добавление комментария',
                comment_updated: 'Редактирование комментария'
            };

            return map[action] || action;
        }

        function statusLabel(status) {
            const map = {
                submitted: 'На проверке',
                approved: 'Подтверждено',
                rejected: 'Отклонено'
            };

            return map[status] || status || '—';
        }

        function formatLogValue(value) {
            if (value === null || value === undefined || value === '') {
                return '<span class="text-secondary">—</span>';
            }

            if (typeof value === 'object') {
                return escapeHtml(JSON.stringify(value, null, 2));
            }

            return escapeHtml(value);
        }

        function renderLogDataDiff(oldData, newData) {
            oldData = oldData || {};
            newData = newData || {};

            let keys = [];

            Object.keys(oldData).forEach(function (key) {
                if (!keys.includes(key)) {
                    keys.push(key);
                }
            });

            Object.keys(newData).forEach(function (key) {
                if (!keys.includes(key)) {
                    keys.push(key);
                }
            });

            if (keys.length === 0) {
                return '';
            }

            let html = `
        <div class="table-responsive mt-3">
            <table class="table table-dark table-sm table-bordered align-middle">
                <thead>
                    <tr>
                        <th style="width: 25%;">Поле</th>
                        <th>Было</th>
                        <th>Стало</th>
                    </tr>
                </thead>
                <tbody>
    `;

            keys.forEach(function (key) {
                let oldValue = oldData[key];
                let newValue = newData[key];

                let changed = JSON.stringify(oldValue) !== JSON.stringify(newValue);

                html += `
            <tr class="${changed ? 'table-warning' : ''}">
                <td>${escapeHtml(getFieldLabel(key))}</td>
                <td><pre class="mb-0 text-light small">${formatLogValue(oldValue)}</pre></td>
                <td><pre class="mb-0 text-light small">${formatLogValue(newValue)}</pre></td>
            </tr>
        `;
            });

            html += `
                </tbody>
            </table>
        </div>
    `;

            return html;
        }

        function renderLogs(logs) {
            if (!logs || logs.length === 0) {
                return `
            <div class="text-center text-secondary py-5">
                История изменений пока пустая
            </div>
        `;
            }

            let html = '';

            logs.forEach(function (log) {
                html += `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold">
                                ${escapeHtml(actionLabel(log.action))}
                            </div>

                            <div class="text-secondary small">
                                ${log.user ? escapeHtml(log.user.name) : 'Система'}
                                ${log.created_at ? ' / ' + escapeHtml(log.created_at) : ''}
                                ${log.ip_address ? ' / IP: ' + escapeHtml(log.ip_address) : ''}
                            </div>
                        </div>

                        <span class="badge bg-secondary">
                            #${log.id}
                        </span>
                    </div>
        `;

                if (log.old_status || log.new_status) {
                    html += `
                <div class="mb-2">
                    <span class="text-secondary">Статус:</span>
                    <span class="badge bg-dark">${escapeHtml(statusLabel(log.old_status))}</span>
                    →
                    <span class="badge bg-info text-dark">${escapeHtml(statusLabel(log.new_status))}</span>
                </div>
            `;
                }

                if (log.comment) {
                    html += `
                <div class="alert alert-secondary py-2 mb-2">
                    ${escapeHtml(log.comment)}
                </div>
            `;
                }

                html += renderLogDataDiff(log.old_data, log.new_data);

                html += `
                </div>
            </div>
        `;
            });

            return html;
        }

        function loadEntryLogs(entryId) {
            $('#logsContainer').html(`
        <div class="text-center text-secondary py-4">
            Загрузка...
        </div>
    `);

            $.ajax({
                url: `/journals/${journalId}/entries/${entryId}/logs`,
                method: "GET",
                success: function (response) {
                    $('#logsContainer').html(renderLogs(response.logs));
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        $(document).on('click', '.logs-entry', function () {
            let entryId = $(this).data('id');

            $('#logsEntryId').val(entryId);

            logsModal.show();

            loadEntryLogs(entryId);
        });

        function getFormNumericValue(key) {
            let input = $(`.journal-field[data-key="${key}"]`);
            let value = input.val();

            if (value === null || value === undefined || value === '') {
                return 0;
            }

            value = String(value).replace(',', '.');

            if (!$.isNumeric(value)) {
                return 0;
            }

            return parseFloat(value);
        }

        function safeCalculateFormula(formula) {
            if (!formula) {
                return '';
            }

            let expression = formula;

            schema.forEach(function (field) {
                let key = field.key;

                let value = getFormNumericValue(key);

                let regex = new RegExp('\\b' + key + '\\b', 'g');

                expression = expression.replace(regex, value);
            });

            /*
             * Разрешаем только цифры, точки, пробелы и математические операторы.
             * Это защита от выполнения произвольного JS.
             */
            if (!/^[0-9+\-*/().\s]+$/.test(expression)) {
                return '';
            }

            try {
                let result = Function('"use strict"; return (' + expression + ')')();

                if (!isFinite(result)) {
                    return '';
                }

                return Math.round(result * 1000000) / 1000000;
            } catch (e) {
                return '';
            }
        }

        function recalculateCalcFields() {
            schema.forEach(function (field) {
                if (field.type !== 'calc') {
                    return;
                }

                let result = safeCalculateFormula(field.formula || '');

                $(`.journal-field[data-key="${field.key}"]`).val(result);
            });
        }
        $(document).on('input change', '.journal-field', function () {
            recalculateCalcFields();
        });
    </script>
@endpush
