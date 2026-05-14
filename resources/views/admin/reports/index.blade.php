@extends('admin.layouts.app')

@section('title', 'Конструктор отчётов')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Конструктор отчётов</h2>
            <div class="text-secondary">
                SQL-запросы, параметры отчётов и экспорт в Excel
            </div>
        </div>

        <button class="btn btn-primary" id="addReportBtn">
            <i class="bi bi-plus-lg"></i>
            Создать отчёт
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-10">
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Поиск отчёта">
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
                    <tr>
                        <th>ID</th>
                        <th>Отчёт</th>
                        <th>Код</th>
                        <th>Параметров</th>
                        <th>Статус</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>
                    <tbody id="reportsTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-5">
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

    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="reportForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalTitle">
                        Создать отчёт
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="reportId">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Название отчёта</label>
                            <input type="text" id="reportName" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Код</label>
                            <input type="text" id="reportCode" class="form-control" placeholder="entries_by_period">
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="reportIsActive" checked>
                                <label class="form-check-label">Активен</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Описание</label>
                            <textarea id="reportDescription" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">SQL-запрос</label>
                            <textarea id="sqlQuery"
                                      class="form-control"
                                      rows="9"
                                      placeholder="SELECT * FROM journal_entries WHERE entry_date BETWEEN :date_from AND :date_to"></textarea>

                            <div class="text-secondary small mt-1">
                                Разрешены только SELECT-запросы. Параметры указываются через двоеточие:
                                <code>:date_from</code>, <code>:date_to</code>, <code>:division_id</code>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Параметры отчёта</h5>
                            <div class="text-secondary small">
                                Эти поля увидит admin перед формированием отчёта.
                            </div>
                        </div>

                        <button type="button" class="btn btn-success btn-sm" id="addParamBtn">
                            <i class="bi bi-plus-lg"></i>
                            Добавить параметр
                        </button>
                    </div>

                    <div id="paramsBuilder"></div>

                    <div class="mt-4">
                        <label class="form-label">JSON параметров</label>
                        <textarea id="paramsPreview" class="form-control" rows="7" readonly></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Сохранить отчёт
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        let reportModal = new bootstrap.Modal(document.getElementById('reportModal'));

        let currentPage = 1;
        let params = [];
        let paramIndex = 0;

        const directories = @json($directories);

        function loadReports(page = 1) {
            currentPage = page;

            $('#reportsTableBody').html(`
            <tr>
                <td colspan="6" class="text-center text-secondary py-5">
                    Загрузка...
                </td>
            </tr>
        `);

            $.ajax({
                url: "{{ route('admin.reports.list') }}",
                method: "GET",
                data: {
                    page: page,
                    search: $('#searchInput').val()
                },
                success: function (response) {
                    renderReports(response.items);
                    renderPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderReports(items) {
            if (!items || items.length === 0) {
                $('#reportsTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">
                        Отчёты не найдены
                    </td>
                </tr>
            `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let paramsCount = item.params_schema ? item.params_schema.length : 0;

                html += `
                <tr>
                    <td>${item.id}</td>

                    <td>
                        <div class="fw-semibold">${escapeHtml(item.name)}</div>
                        <div class="text-secondary small">${escapeHtml(item.description || '')}</div>
                    </td>

                    <td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">—</span>'}</td>

                    <td>
                        <span class="badge bg-info text-dark">${paramsCount}</span>
                    </td>

                    <td>
                        ${item.is_active
                    ? '<span class="badge bg-success">Активен</span>'
                    : '<span class="badge bg-danger">Отключён</span>'}
                    </td>

                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info edit-report" data-id="${item.id}">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-danger delete-report" data-id="${item.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            });

            $('#reportsTableBody').html(html);
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

            let html = '';
            let current = pagination.current_page;
            let last = pagination.last_page;

            html += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a href="#" class="page-link report-page" data-page="${current - 1}">
                    Назад
                </a>
            </li>
        `;

            for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
                html += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a href="#" class="page-link report-page" data-page="${i}">
                        ${i}
                    </a>
                </li>
            `;
            }

            html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <a href="#" class="page-link report-page" data-page="${current + 1}">
                    Вперёд
                </a>
            </li>
        `;

            $('#paginationLinks').html(html);
        }

        function clearReportForm() {
            $('#reportForm')[0].reset();
            $('#reportId').val('');
            $('#reportIsActive').prop('checked', true);

            params = [];
            paramIndex = 0;

            renderParams();
        }

        function addParam(data = null) {
            paramIndex++;

            params.push({
                uid: paramIndex,
                key: data?.key || '',
                label: data?.label || '',
                type: data?.type || 'string',
                required: data?.required || false,
                directory_id: data?.directory_id || '',
                source: data?.source || '',
                options: data?.options || [],
                formula: data?.formula || ''
            });

            renderParams();
        }

        function readParamsFromDom() {
            params.forEach(function (param) {
                let prefix = `.param-card[data-uid="${param.uid}"]`;

                param.key = $(`${prefix} .param-key`).val();
                param.label = $(`${prefix} .param-label`).val();
                param.type = $(`${prefix} .param-type`).val();
                param.required = $(`${prefix} .param-required`).is(':checked');
                param.directory_id = $(`${prefix} .param-directory`).val();
                param.source = $(`${prefix} .param-source`).val();
                param.formula = $(`${prefix} .param-formula`).val();

                let optionsText = $(`${prefix} .param-options`).val() || '';

                param.options = optionsText
                    .split('\n')
                    .map(item => item.trim())
                    .filter(item => item.length > 0);
            });
        }

        function renderParams() {
            let html = '';

            params.forEach(function (param, index) {
                let directoriesOptions = `<option value="">Выберите справочник</option>`;

                directories.forEach(function (directory) {
                    let selected = String(param.directory_id) === String(directory.id) ? 'selected' : '';

                    directoriesOptions += `
                    <option value="${directory.id}" ${selected}>
                        ${escapeHtml(directory.name)}
                    </option>
                `;
                });

                let optionsText = param.options ? param.options.join('\n') : '';

                html += `
                <div class="card mb-3 param-card" data-uid="${param.uid}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold">Параметр #${index + 1}</div>

                            <button type="button"
                                    class="btn btn-sm btn-outline-danger remove-param"
                                    data-uid="${param.uid}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Ключ</label>
                                <input type="text"
                                       class="form-control param-key"
                                       value="${escapeHtml(param.key)}"
                                       placeholder="date_from">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Название</label>
                                <input type="text"
                                       class="form-control param-label"
                                       value="${escapeHtml(param.label)}"
                                       placeholder="Дата от">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Тип</label>
                                <select class="form-select param-type">
                                    <option value="string" ${param.type === 'string' ? 'selected' : ''}>Строка</option>
                                    <option value="number" ${param.type === 'number' ? 'selected' : ''}>Число</option>
                                    <option value="date" ${param.type === 'date' ? 'selected' : ''}>Дата</option>
                                    <option value="time" ${param.type === 'time' ? 'selected' : ''}>Время</option>
                                    <option value="list" ${param.type === 'list' ? 'selected' : ''}>Список</option>
                                    <option value="directory" ${param.type === 'directory' ? 'selected' : ''}>Справочник ID</option>
                                    <option value="directory_text" ${param.type === 'directory_text' ? 'selected' : ''}>Справочник текстом</option>
                                    <option value="calc" ${param.type === 'calc' ? 'selected' : ''}>Вычисляемое</option>
                                </select>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input param-required"
                                           type="checkbox"
                                           ${param.required ? 'checked' : ''}>
                                    <label class="form-check-label">Обяз.</label>
                                </div>
                            </div>

                            <div class="col-md-6 param-directory-block ${['directory', 'directory_text'].includes(param.type) ? '' : 'd-none'}">
                                <label class="form-label">Справочник</label>
                                <select class="form-select param-directory">
                                    ${directoriesOptions}
                                </select>

                                <div class="text-secondary small mt-1">
                                    Для системных таблиц можно не выбирать справочник, а указать source.
                                </div>
                            </div>

                            <div class="col-md-6 param-source-block ${['directory', 'directory_text'].includes(param.type) ? '' : 'd-none'}">
                                <label class="form-label">Источник</label>
                                <select class="form-select param-source">
                                    <option value="" ${param.source === '' ? 'selected' : ''}>Обычный справочник</option>
                                    <option value="divisions" ${param.source === 'divisions' ? 'selected' : ''}>Подразделения</option>
                                    <option value="users" ${param.source === 'users' ? 'selected' : ''}>Пользователи</option>
                                    <option value="journal_templates" ${param.source === 'journal_templates' ? 'selected' : ''}>Журналы</option>
                                </select>
                            </div>

                            <div class="col-md-6 param-options-block ${param.type === 'list' ? '' : 'd-none'}">
                                <label class="form-label">Варианты списка</label>
                                <textarea class="form-control param-options" rows="4">${escapeHtml(optionsText)}</textarea>
                            </div>

                            <div class="col-md-6 param-formula-block ${param.type === 'calc' ? '' : 'd-none'}">
                                <label class="form-label">Формула</label>
                                <input type="text"
                                       class="form-control param-formula"
                                       value="${escapeHtml(param.formula)}">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            });

            $('#paramsBuilder').html(html);
            updateParamsPreview();
        }

        function buildParamsSchema() {
            readParamsFromDom();

            return params.map(function (param) {
                let item = {
                    key: param.key,
                    label: param.label,
                    type: param.type,
                    required: param.required ? 1 : 0
                };

                if (param.type === 'directory' || param.type === 'directory_text') {
                    item.directory_id = param.directory_id || null;
                    item.source = param.source || '';
                }

                if (param.type === 'list') {
                    item.options = param.options || [];
                }

                if (param.type === 'calc') {
                    item.formula = param.formula || '';
                }

                return item;
            });
        }

        function updateParamsPreview() {
            $('#paramsPreview').val(JSON.stringify(buildParamsSchema(), null, 2));
        }

        $('#addReportBtn').on('click', function () {
            clearReportForm();
            $('#reportModalTitle').text('Создать отчёт');
            reportModal.show();
        });

        $('#addParamBtn').on('click', function () {
            readParamsFromDom();
            addParam();
        });

        $(document).on('click', '.remove-param', function () {
            let uid = parseInt($(this).data('uid'));

            params = params.filter(item => item.uid !== uid);

            renderParams();
        });

        $(document).on('change', '.param-type', function () {
            readParamsFromDom();
            renderParams();
        });

        $(document).on('input change', '.param-key, .param-label, .param-required, .param-directory, .param-source, .param-options, .param-formula', function () {
            updateParamsPreview();
        });

        $('#reportForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#reportId').val();

            let url = id
                ? "/admin/reports/" + id
                : "{{ route('admin.reports.store') }}";

            let payload = {
                name: $('#reportName').val(),
                code: $('#reportCode').val(),
                description: $('#reportDescription').val(),
                sql_query: $('#sqlQuery').val(),
                is_active: $('#reportIsActive').is(':checked') ? 1 : 0,
                params_schema: buildParamsSchema()
            };

            $.ajax({
                url: url,
                method: "POST",
                data: payload,
                success: function (response) {
                    showToast(response.message, 'success');
                    reportModal.hide();
                    loadReports(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-report', function () {
            let id = $(this).data('id');

            clearReportForm();

            $.ajax({
                url: "/admin/reports/" + id,
                method: "GET",
                success: function (response) {
                    let item = response.report;

                    $('#reportModalTitle').text('Редактировать отчёт');

                    $('#reportId').val(item.id);
                    $('#reportName').val(item.name);
                    $('#reportCode').val(item.code);
                    $('#reportDescription').val(item.description);
                    $('#sqlQuery').val(item.sql_query);
                    $('#reportIsActive').prop('checked', item.is_active);

                    params = [];
                    paramIndex = 0;

                    if (item.params_schema && item.params_schema.length > 0) {
                        item.params_schema.forEach(function (param) {
                            addParam(param);
                        });
                    }

                    reportModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-report', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить отчёт?')) {
                return;
            }

            $.ajax({
                url: "/admin/reports/" + id,
                method: "DELETE",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadReports(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#searchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadReports(1);
            }
        });

        $('#resetFilters').on('click', function () {
            $('#searchInput').val('');
            loadReports(1);
        });

        $(document).on('click', '.report-page', function (e) {
            e.preventDefault();

            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadReports(page);
            }
        });

        loadReports();
    </script>
@endpush
