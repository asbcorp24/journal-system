@extends('user.layouts.app')

@section('title', $journal->name)

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4" id="journalHeaderBar">
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

        <div class="d-flex gap-2" id="journalActionButtons">
            <button class="btn btn-outline-info" id="toggleJournalFullscreenBtn">
                <i class="bi bi-arrows-fullscreen"></i>
                На весь экран
            </button>
            <button class="btn btn-outline-light" id="printJournalBtn">
                <i class="bi bi-printer"></i>
                Печать
            </button>

            <button class="btn btn-primary{{ $canManageJournal ? '' : ' d-none' }}" id="addEntryBtn">
                <i class="bi bi-plus-lg"></i>
                Добавить запись
            </button>
        </div>
    </div>

    <div id="journalWorkspace">
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
                @if($showDivisionFilter)
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

                <div class="col-12">
                    <div id="fieldFiltersContainer" class="row g-3"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Поиск по данным">
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="showDeletedFilter">
                        <label class="form-check-label" for="showDeletedFilter">Показать удалённые</label>
                    </div>
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

    </div>

    <div class="modal fade" id="entryModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" id="entryModalDialog">
            <form class="modal-content" id="entryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="entryModalTitle">
                        Добавить запись
                    </h5>

                    <button type="button"
                            class="btn btn-outline-light btn-sm me-2"
                            id="toggleEntryModalFullscreenBtn">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="entryId">

                    @if($showEntryDivisionSelector)
                        <div class="mb-3">
                            <label class="form-label">Подразделение</label>
                            <select id="entryDivisionId" class="form-select">
                                <option value="">Не выбрано</option>
                                @foreach($entryDivisions as $division)
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
                    <div id="directoryValueFields"></div>
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

@push('styles')
    <style>
        #journalWorkspace.journal-fullscreen {
            position: fixed;
            inset: 12px;
            z-index: 1040;
            overflow: auto;
            background: #0f172a;
            padding: 12px;
            border-radius: 16px;
        }

        #journalWorkspace.journal-fullscreen .card {
            margin-bottom: 16px !important;
        }

        body.journal-fullscreen-active {
            overflow: hidden;
        }

        body.journal-fullscreen-active #journalHeaderBar {
            position: fixed;
            top: 12px;
            left: 24px;
            right: 24px;
            z-index: 1041;
            padding: 12px 16px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.96);
            backdrop-filter: blur(8px);
        }

        body.journal-fullscreen-active #journalWorkspace.journal-fullscreen {
            inset: 108px 12px 12px;
        }

        body.journal-fullscreen-active #journalActionButtons {
            flex-wrap: wrap;
        }

        #entryModalDialog.modal-dialog-fullscreen {
            max-width: none;
            width: calc(100vw - 1rem);
            height: calc(100vh - 1rem);
            margin: .5rem;
        }

        #entryModalDialog.modal-dialog-fullscreen .modal-content {
            height: 100%;
        }

        #entryModalDialog.modal-dialog-fullscreen .modal-body {
            overflow-y: auto;
        }
    </style>
@endpush

@push('scripts')
    <script>
        const journalId = {{ $journal->id }};
        const schema = @json($schema);
        const directoryValues = @json($directoryValues);
        const directoryQrValues = @json($directoryQrValues);
        const directoryDefinitions = @json($directories);
        const userRole = "{{ session('user_role') }}";
        const canManageJournal = @json($canManageJournal);
        const canManageDirectoryValues = userRole !== 'worker';

        let entryModal = new bootstrap.Modal(document.getElementById('entryModal'));
        let directoryValueModal = new bootstrap.Modal(document.getElementById('directoryValueModal'));
        let currentPage = 1;
        let journalFullscreen = false;
        let entryModalFullscreen = false;

        function statusBadge(status) {
            if (status === 'approved') {
                return '<span class="badge bg-success">Подтверждено</span>';
            }

            if (status === 'rejected') {
                return '<span class="badge bg-danger">Отклонено</span>';
            }

            return '<span class="badge bg-warning text-dark">На проверке</span>';
        }

        function statusBadge(status) {
            if (status === 'approved') {
                return '<span class="badge bg-success" title="Подтверждено">OK</span>';
            }

            if (status === 'rejected') {
                return '<span class="badge bg-danger" title="Отклонено">!</span>';
            }

            return '<span class="badge bg-warning text-dark" title="На проверке">...</span>';
        }

        function compactChecker(entry) {
            if (!entry.checker) {
                return '<span class="text-secondary" title="Не проверено">—</span>';
            }

            let title = entry.checker.name || '';

            if (entry.checked_at) {
                title += ' / ' + entry.checked_at;
            }

            return `<span class="badge bg-secondary" title="${escapeHtml(title)}">✓</span>`;
        }

        function compactComment(entry) {
            if (!entry.last_comment) {
                return '<span class="text-secondary" title="Комментария нет">—</span>';
            }

            let title = entry.last_comment.comment || '';

            if (entry.last_comment.user && entry.last_comment.user.name) {
                title += ' / ' + entry.last_comment.user.name;
            }

            if (entry.last_comment.created_at) {
                title += ' / ' + entry.last_comment.created_at;
            }

            return `<span class="badge bg-info text-dark" title="${escapeHtml(title)}">...</span>`;
        }

        function updateJournalFullscreenButton() {
            $('#toggleJournalFullscreenBtn').html(journalFullscreen
                ? '<i class="bi bi-fullscreen-exit"></i> Свернуть'
                : '<i class="bi bi-arrows-fullscreen"></i> На весь экран');
        }

        function updateEntryModalFullscreenButton() {
            $('#toggleEntryModalFullscreenBtn').html(entryModalFullscreen
                ? '<i class="bi bi-fullscreen-exit"></i>'
                : '<i class="bi bi-arrows-fullscreen"></i>');
        }

        function getFieldLabel(key) {
            let field = schema.find(item => item.key === key);
            return field ? field.label : key;
        }

        function getDirectoryOptionLabel(field, item) {
            if (!item) {
                return '';
            }

            let displayField = field.directory_display_field || '';

            if (displayField && item.data && item.data[displayField] !== undefined && item.data[displayField] !== null && item.data[displayField] !== '') {
                return item.data[displayField];
            }

            return item.value || '';
        }

        function getDirectoryText(field, valueId) {
            let directoryId = field.directory_id;

            if (!directoryId || !valueId) {
                return '';
            }

            let list = directoryValues[directoryId] || [];

            let item = list.find(function (value) {
                return String(value.id) === String(valueId);
            });

            return item ? getDirectoryOptionLabel(field, item) : valueId;
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

        function getDirectoryDefinition(directoryId) {
            return directoryDefinitions[directoryId] || null;
        }

        function getDirectoryQrKey(field) {
            let directory = getDirectoryDefinition(field.directory_id);
            let directorySchema = directory && Array.isArray(directory.schema) ? directory.schema : [];
            let qrField = directorySchema.find(function (item) {
                return item.type === 'qr';
            });

            return qrField ? qrField.key : null;
        }

        function findDirectoryValueByQr(field, scannedValue) {
            let qrKey = getDirectoryQrKey(field);
            let needle = String(scannedValue || '').trim();

            if (!qrKey || !needle) {
                return null;
            }

            return (directoryQrValues[field.directory_id] || directoryValues[field.directory_id] || []).find(function (item) {
                let data = item.data || {};

                return String(data[qrKey] || '').trim() === needle;
            }) || null;
        }

        function generateQrClientValue() {
            return 'QR-' + new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14)
                + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        }

        function renderDirectoryValueModalForm(directoryId, data = {}) {
            let directory = getDirectoryDefinition(directoryId);
            let schema = directory && Array.isArray(directory.schema) ? directory.schema : [];
            let html = '';

            if (!schema.length) {
                html = `
                    <label class="form-label" for="directoryValueInput">Значение</label>
                    <input type="text"
                           class="form-control"
                           id="directoryValueInput"
                           maxlength="255"
                           value="${escapeHtml(data.value || '')}"
                           required>
                `;

                $('#directoryValueFields').html(html);
                return;
            }

            schema.forEach(function (field) {
                let value = data[field.key] ?? '';
                let required = field.required ? 'required' : '';
                let requiredMark = field.required ? '<span class="text-danger">*</span>' : '';

                html += `<div class="mb-3">`;
                html += `<label class="form-label">${escapeHtml(field.label)} ${requiredMark}</label>`;

                if (field.type === 'number') {
                    html += `<input type="number" step="any" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
                } else if (field.type === 'date') {
                    html += `<input type="date" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
                } else if (field.type === 'time') {
                    html += `<input type="time" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(String(value || '').substring(0, 5))}" ${required}>`;
                } else if (field.type === 'qr') {
                    let qrRequired = field.auto_generate ? '' : required;
                    let placeholder = field.auto_generate ? 'Оставьте пустым для автогенерации' : 'Введите QR/штрихкод';
                    html += `
                        <div class="input-group">
                            <input type="text" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" placeholder="${placeholder}" ${qrRequired}>
                            <button type="button" class="btn btn-outline-info generate-directory-qr-value">Сгенерировать</button>
                        </div>
                    `;
                } else if (field.type === 'list') {
                    html += `<select class="form-select directory-value-field" data-key="${field.key}" ${required}>`;
                    html += `<option value="">Выберите значение</option>`;

                    (field.options || []).forEach(function (option) {
                        let selected = String(value) === String(option) ? 'selected' : '';
                        html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                    });

                    html += `</select>`;
                } else {
                    html += `<input type="text" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
                }

                html += `</div>`;
            });

            $('#directoryValueFields').html(html);
        }

        function collectDirectoryValueData(directoryId) {
            let directory = getDirectoryDefinition(directoryId);
            let schema = directory && Array.isArray(directory.schema) ? directory.schema : [];

            if (!schema.length) {
                return {
                    value: ($('#directoryValueInput').val() || '').trim()
                };
            }

            let data = {};

            $('.directory-value-field').each(function () {
                data[$(this).data('key')] = $(this).val();
            });

            return { data };
        }

        function formatValue(field, value) {
            if (value === null || value === undefined || value === '') {
                return '<span class="text-secondary">—</span>';
            }

            if (field.type === 'directory') {
                return escapeHtml(getDirectoryText(field, value));
            }

            if (field.type === 'directory_text') {
                return escapeHtml(value);
            }

            if (field.type === 'number') {
                return escapeHtml(value);
            }

            return escapeHtml(value);
        }

        function getFilterableFields() {
            return schema.filter(function (field) {
                return !!field.filterable;
            });
        }

        function renderFieldFilters() {
            let fields = getFilterableFields();
            let html = '';

            fields.forEach(function (field) {
                html += `<div class="col-md-3">`;
                html += `<label class="form-label">${escapeHtml(field.label)}</label>`;

                if (field.type === 'date') {
                    html += `<input type="date" class="form-control journal-field-filter" data-key="${field.key}">`;
                } else if (field.type === 'time') {
                    html += `<input type="time" class="form-control journal-field-filter" data-key="${field.key}">`;
                } else if (field.type === 'number') {
                    html += `<input type="number" step="any" class="form-control journal-field-filter" data-key="${field.key}" placeholder="Введите значение">`;
                } else if (field.type === 'list') {
                    html += `<select class="form-select journal-field-filter" data-key="${field.key}"><option value="">Все</option>`;

                    (field.options || []).forEach(function (option) {
                        html += `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`;
                    });

                    html += `</select>`;
                } else if (field.type === 'directory') {
                    html += `<select class="form-select journal-field-filter" data-key="${field.key}"><option value="">Все</option>`;

                    (directoryValues[field.directory_id] || []).forEach(function (item) {
                        html += `<option value="${item.id}">${escapeHtml(getDirectoryOptionLabel(field, item))}</option>`;
                    });

                    html += `</select>`;
                } else if (field.type === 'directory_text') {
                    html += `<select class="form-select journal-field-filter" data-key="${field.key}"><option value="">Все</option>`;

                    (directoryValues[field.directory_id] || []).forEach(function (item) {
                        let label = getDirectoryOptionLabel(field, item);
                        html += `<option value="${escapeHtml(label)}">${escapeHtml(label)}</option>`;
                    });

                    html += `</select>`;
                } else {
                    html += `<input type="text" class="form-control journal-field-filter" data-key="${field.key}" placeholder="Поиск по полю">`;
                }

                html += `</div>`;
            });

            $('#fieldFiltersContainer').html(html);

            if (document.getElementById('fieldFiltersContainer')) {
                initSearchableSelects(document.getElementById('fieldFiltersContainer'));
            }
        }

        function collectFieldFilters() {
            let filters = {};

            $('.journal-field-filter').each(function () {
                let key = $(this).data('key');
                let value = $(this).val();

                if (value !== null && value !== '') {
                    filters[key] = value;
                }
            });

            return filters;
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
            <th>Добавил</th>
            <th>Подразделение</th>
            <th>Статус</th>
  <th>Проверил</th>
   <th>Комментарий</th>
            <th class="text-end">Действия</th>
        `;

            $('#entriesTableHead').html(html);
            compactTailHeaders();
        }

        function compactTailHeaders() {
            let headers = $('#entriesTableHead th');
            let statusHeader = headers.eq(headers.length - 4);
            let checkerHeader = headers.eq(headers.length - 3);
            let commentHeader = headers.eq(headers.length - 2);

            statusHeader.attr('title', 'Статус').text('Ст.').css({width: '58px'});
            checkerHeader.attr('title', 'Проверил').text('Пр.').css({width: '58px'});
            commentHeader.attr('title', 'Комментарий').text('Ком.').css({width: '58px'});
        }

        function compactTailCells() {
            $('#entriesTableBody tr').each(function () {
                let cells = $(this).children('td');

                if (cells.length < 4) {
                    return;
                }

                let statusCell = cells.eq(cells.length - 4);
                let checkerCell = cells.eq(cells.length - 3);
                let commentCell = cells.eq(cells.length - 2);

                let statusTitle = statusCell.text().trim();
                let checkerTitle = checkerCell.text().replace(/\s+/g, ' ').trim();
                let commentTitle = commentCell.text().replace(/\s+/g, ' ').trim();

                statusCell.addClass('text-center').attr('title', statusTitle || 'Статус').css({width: '58px'});
                checkerCell.addClass('text-center').attr('title', checkerTitle || 'Не проверено').css({width: '58px'});
                commentCell.addClass('text-center').attr('title', commentTitle || 'Комментария нет').css({width: '58px'});

                if (statusCell.find('.bg-success').length) {
                    statusCell.html('<span class="badge bg-success">OK</span>');
                } else if (statusCell.find('.bg-danger').length) {
                    statusCell.html('<span class="badge bg-danger">!</span>');
                } else if (statusCell.find('.bg-warning').length) {
                    statusCell.html('<span class="badge bg-warning text-dark">...</span>');
                }

                if (checkerTitle && checkerTitle !== '—' && checkerTitle !== 'вЂ”') {
                    checkerCell.html('<span class="badge bg-secondary">✓</span>');
                } else {
                    checkerCell.html('<span class="text-secondary">—</span>');
                }

                if (commentTitle && commentTitle !== '—' && commentTitle !== 'вЂ”') {
                    commentCell.html('<span class="badge bg-info text-dark">...</span>');
                } else {
                    commentCell.html('<span class="text-secondary">—</span>');
                }
            });
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
                    show_deleted: $('#showDeletedFilter').is(':checked') ? 1 : 0,
                    search: $('#searchInput').val(),
                    division_id: $('#divisionFilter').length ? $('#divisionFilter').val() : '',
                    field_filters: collectFieldFilters()
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
                <tr class="${entry.deleted_at ? 'table-danger' : ''}">
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
             <td>${entry.deleted_at ? '<span class="badge bg-danger">Удалена</span>' : statusBadge(entry.status)}</td>

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
            compactTailCells();
        }
        function canChangeStatusButtons(entry) {
            if (!entry.can_change_status) {
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
                            selected = String(value) === String(getDirectoryOptionLabel(field, item)) ? 'selected' : '';
                        }

                        html += `
                        <option value="${item.id}" ${selected}>
                            ${escapeHtml(getDirectoryOptionLabel(field, item))}
                        </option>
                    `;
                    });

                    html += `</select>`;

                    if (getDirectoryQrKey(field)) {
                        html += `
                        <button type="button"
                                class="btn btn-outline-info scan-directory-value-btn"
                                data-field-key="${field.key}"
                                title="Сканировать QR/штрихкод">
                            <i class="bi bi-upc-scan"></i>
                        </button>
                    `;
                    }

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
            setEntryFormReadonly(false);
        }

        function setEntryFormReadonly(readonly) {
            $('#dynamicForm').find('input, select, textarea, button').prop('disabled', readonly);
            $('#changeComment').prop('disabled', readonly);

            if ($('#entryDivisionId').length) {
                $('#entryDivisionId').prop('disabled', readonly);
            }

            $('#entryForm button[type="submit"]').toggleClass('d-none', readonly);
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
                    setEntryFormReadonly(false);
                    entryModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.view-entry', function () {
            let id = $(this).data('id');

            clearEntryForm();

            $.ajax({
                url: `/journals/${journalId}/entries/${id}`,
                method: "GET",
                success: function (response) {
                    let entry = response.entry;

                    $('#entryModalTitle').text('Просмотр удалённой записи');
                    $('#entryId').val(entry.id);

                    if ($('#entryDivisionId').length) {
                        $('#entryDivisionId').val(entry.division_id);
                    }

                    renderDynamicForm(entry.data || {});
                    setEntryFormReadonly(true);
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
            let directoryId = String($(this).data('directory-id'));

            $('#directoryValueFieldKey').val($(this).data('field-key'));
            $('#directoryValueDirectoryId').val(directoryId);

            let fieldLabel = $(this).data('field-label') || 'field';
            $('#directoryValueModalTitle').text(`Add value: ${fieldLabel}`);
            renderDirectoryValueModalForm(directoryId);

            directoryValueModal.show();
        });

        $(document).on('click', '.generate-directory-qr-value', function () {
            $(this).closest('.input-group').find('.directory-value-field').val(generateQrClientValue()).trigger('input');
        });

        $(document).on('click', '.scan-directory-value-btn', function () {
            let fieldKey = $(this).data('field-key');
            let field = schema.find(function (item) {
                return item.key === fieldKey;
            });

            if (!field) {
                showToast('Поле журнала не найдено', 'warning');
                return;
            }

            let scannedValue = prompt('Сканируйте QR/штрихкод');

            if (scannedValue === null || String(scannedValue).trim() === '') {
                return;
            }

            let found = findDirectoryValueByQr(field, scannedValue);

            if (!found) {
                showToast('Объект по этому QR/штрихкоду не найден', 'warning');
                return;
            }

            let select = $(`.journal-field[data-key="${fieldKey}"]`);
            let label = getDirectoryOptionLabel(field, found);

            if (select.find(`option[value="${found.id}"]`).length === 0) {
                select.append(`
                    <option value="${found.id}">
                        ${escapeHtml(label)}
                    </option>
                `);
            }

            select.val(String(found.id)).trigger('change');
            showToast('Объект найден: ' + label, 'success');
        });

        $('#directoryValueForm').on('submit', function (e) {
            e.preventDefault();

            let fieldKey = $('#directoryValueFieldKey').val();
            let directoryId = $('#directoryValueDirectoryId').val();
            if (!fieldKey || !directoryId) {
                showToast('Directory is not selected', 'danger');
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/directories/${directoryId}/values`,
                method: "POST",
                data: collectDirectoryValueData(directoryId),
                success: function (response) {
                    upsertDirectoryValue(directoryId, response.value);

                    let select = $(`.journal-field[data-key="${fieldKey}"]`);

                    if (select.length) {
                        if (select.find(`option[value="${response.value.id}"]`).length === 0) {
                            let field = schema.find(function (item) {
                                return item.key === fieldKey;
                            });
                            let optionLabel = getDirectoryOptionLabel(field || {}, response.value);

                            select.append(`
                                <option value="${response.value.id}">
                                    ${escapeHtml(optionLabel)}
                                </option>
                            `);
                        } else {
                            let field = schema.find(function (item) {
                                return item.key === fieldKey;
                            });
                            let optionLabel = getDirectoryOptionLabel(field || {}, response.value);

                            select.find(`option[value="${response.value.id}"]`).text(optionLabel);
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
            $('.journal-field-filter').val('');

            if ($('#divisionFilter').length) {
                $('#divisionFilter').val('');
            }

            $('#showDeletedFilter').prop('checked', false);

            loadEntries(1);
        });

        $('#searchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadEntries(1);
            }
        });

        $(document).on('keyup', '.journal-field-filter', function (e) {
            if (e.key === 'Enter') {
                loadEntries(1);
            }
        });

        $(document).on('change', '.journal-field-filter', function () {
            if ($(this).is('select, input[type="date"], input[type="time"]')) {
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
        $(document).on('change', '#showDeletedFilter', function () {
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
        $(document).on('click', '.restore-entry', function () {
            let id = $(this).data('id');

            if (!confirm('Восстановить запись журнала?')) {
                return;
            }

            $.ajax({
                url: `/journals/${journalId}/entries/${id}/restore`,
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
        function canEditEntry(entry) {
            if (entry.deleted_at) {
                return false;
            }

            return !!entry.can_edit;
        }

        function canDeleteEntry(entry) {
            return !!entry.can_delete;
        }

        function actionButtons(entry) {
            let html = `<div class="btn-group btn-group-sm">`;

            if (entry.deleted_at) {
                html += `
            <button class="btn btn-outline-secondary view-entry"
                    data-id="${entry.id}"
                    title="Просмотр">
                <i class="bi bi-eye"></i>
            </button>
        `;

                if (userRole === 'admin') {
                    html += `
            <button class="btn btn-outline-success restore-entry"
                    data-id="${entry.id}"
                    title="Восстановить">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
        `;
                }

                html += `</div>`;
                return html;
            }

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

            let fieldFilters = collectFieldFilters();

            Object.keys(fieldFilters).forEach(function (key) {
                params.append(`field_filters[${key}]`, fieldFilters[key]);
            });

            if ($('#divisionFilter').length && $('#divisionFilter').val()) {
                params.append('division_id', $('#divisionFilter').val());
            }

            if ($('#showDeletedFilter').is(':checked')) {
                params.append('show_deleted', '1');
            }

            let url = `/journals/${journalId}/print`;

            if (params.toString()) {
                url += '?' + params.toString();
            }

            window.open(url, '_blank');
        });

        $('#toggleJournalFullscreenBtn').on('click', function () {
            journalFullscreen = !journalFullscreen;
            $('#journalWorkspace').toggleClass('journal-fullscreen', journalFullscreen);
            $('body').toggleClass('journal-fullscreen-active', journalFullscreen);
            updateJournalFullscreenButton();
        });

        $('#toggleEntryModalFullscreenBtn').on('click', function () {
            entryModalFullscreen = !entryModalFullscreen;
            $('#entryModalDialog').toggleClass('modal-dialog-fullscreen', entryModalFullscreen);
            updateEntryModalFullscreenButton();
        });

        $('#entryModal').on('hidden.bs.modal', function () {
            entryModalFullscreen = false;
            $('#entryModalDialog').removeClass('modal-dialog-fullscreen');
            updateEntryModalFullscreenButton();
        });

        updateJournalFullscreenButton();
        updateEntryModalFullscreenButton();
        renderTableHead();
        renderFieldFilters();
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
