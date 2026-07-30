@extends('admin.layouts.app')

@section('title', 'Конструктор отчётов')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Конструктор отчётов</h2>
            <div class="text-secondary">
                SQL-запросы, параметры, импорт и быстрый конструктор типовых отчётов
            </div>
        </div>

        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-magic"></i>
                    Мастер создания
                </button>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><button class="dropdown-item report-preset-btn" type="button" data-preset="warehouse_stock_balance">Создать отчёт по остаткам</button></li>
                </ul>
            </div>
            <button class="btn btn-outline-light" id="importReportBtn">
                <i class="bi bi-upload"></i>
                Импорт
            </button>

            <button class="btn btn-primary" id="addReportBtn">
                <i class="bi bi-plus-lg"></i>
                Создать отчёт
            </button>
        </div>
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
                        <td colspan="6" class="text-center text-secondary py-5">Загрузка...</td>
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
                    <h5 class="modal-title" id="reportModalTitle">Создать отчёт</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                                <label class="form-check-label" for="reportIsActive">Активен</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Описание</label>
                            <textarea id="reportDescription" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-md-12">
                            <div class="card border-secondary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <div>
                                            <div class="fw-bold">Быстрый конструктор</div>
                                            <div class="text-secondary small">
                                                Собирает SQL по журналу без ручного написания. После генерации запрос можно доправить вручную.
                                            </div>
                                        </div>

                                        <button type="button" class="btn btn-outline-info btn-sm" id="generateReportSqlBtn">
                                            <i class="bi bi-magic"></i>
                                            Собрать SQL
                                        </button>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Режим</label>
                                            <select id="builderMode" class="form-select">
                                                <option value="table">Таблица записей</option>
                                                <option value="summary">Группировка + сумма</option>
                                            </select>
                                        </div>

                                        <div class="col-md-8">
                                            <label class="form-label">Журнал</label>
                                            <select id="builderJournalId" class="form-select"></select>
                                        </div>

                                        <div class="col-md-12 builder-table-block">
                                            <label class="form-label">Колонки отчёта</label>
                                            <div id="builderColumnsBox" class="row g-2"></div>
                                        </div>

                                        <div class="col-md-6 builder-summary-block d-none">
                                            <label class="form-label">Группировать по полю</label>
                                            <select id="builderGroupField" class="form-select"></select>
                                        </div>

                                        <div class="col-md-6 builder-summary-block d-none">
                                            <label class="form-label">Суммировать по полю</label>
                                            <select id="builderSumField" class="form-select"></select>
                                        </div>
                                    </div>

                                    <div class="text-secondary small mt-3">
                                        Генератор автоматически добавляет параметры <code>:date_from</code>, <code>:date_to</code>, <code>:division_id</code>.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">SQL-запрос</label>
                            <textarea id="sqlQuery"
                                      class="form-control"
                                      rows="9"
                                      placeholder="SELECT * FROM journal_entries WHERE entry_date BETWEEN :date_from AND :date_to"></textarea>
                            <div class="text-secondary small mt-1">
                                Разрешены только <code>SELECT</code>-запросы.
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Параметры отчёта</h5>
                            <div class="text-secondary small">
                                Эти поля увидит пользователь перед запуском отчёта.
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

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Печатная форма отчёта</h5>
                            <div class="text-secondary small">
                                Отдельное оформление для кнопки печати. Если шаблон пустой, останется обычная табличная печать.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Заголовок печати</label>
                            <input type="text" id="reportPrintTitle" class="form-control" placeholder="Например: Остатки номенклатуры на складе">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Ориентация листа</label>
                            <select id="reportPrintOrientation" class="form-select">
                                <option value="portrait">Книжная</option>
                                <option value="landscape">Альбомная</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <div class="fw-bold mb-1">Подсказка по шаблону</div>
                        <div class="small">
                            Для всей таблицы используйте <code>@{{ table }}</code>.
                            Для ручной разметки строк используйте цикл <code>@{{#rows}} ... @{{/rows}}</code>.
                            Внутри цикла доступны поля результата как <code>@{{ row.column_name }}</code>.
                            Параметры запуска: <code>@{{ params.date_from }}</code>, <code>@{{ params.date_to }}</code>.
                            Общие переменные: <code>@{{ report.name }}</code>, <code>@{{ print.title }}</code>, <code>@{{ print.date }}</code>, <code>@{{ report.row_count }}</code>.
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">HTML-шаблон печати</label>
                        <textarea id="reportPrintBodyHtml"
                                  class="form-control font-monospace"
                                  rows="12"
                                  placeholder="<h2>@{{ print.title }}</h2>&#10;<p>Дата печати: @{{ print.date }}</p>&#10;@{{ table }}"></textarea>
                    </div>

                    <div class="mt-3">
                        <div class="fw-bold small mb-2">Доступные переменные</div>
                        <div class="d-flex flex-wrap gap-2 small" id="reportPrintVariablesBox"></div>
                    </div>

                    <div class="mt-3">
                        <div class="fw-bold small mb-2">Примеры</div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-info insert-report-print-example" data-example-template="full-table">Вся таблица</button>
                            <button type="button" class="btn btn-sm btn-outline-info insert-report-print-example" data-example-template="rows-loop">Цикл по строкам</button>
                            <button type="button" class="btn btn-sm btn-outline-info insert-report-print-example" data-example-template="summary-act">Акт / сводка</button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить отчёт</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="importReportModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="importReportForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт шаблона отчёта</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="importReportFile" class="form-label">JSON-файл шаблона</label>
                        <input type="file" class="form-control" id="importReportFile" name="template_file" accept=".json,.txt" required>
                    </div>

                    <div class="text-secondary small">
                        Будет создан новый шаблон отчёта с суффиксом импорта.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Импортировать</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
        let importReportModal = new bootstrap.Modal(document.getElementById('importReportModal'));

        let currentPage = 1;
        let params = [];
        let paramIndex = 0;

        const directories = @json($directories);
        const journals = @json($journals);
        const reportWizardPresets = {
            warehouse_stock_balance: {
                title: 'Складской отчёт: остатки номенклатуры',
                name: 'Остатки номенклатуры на складе',
                code: 'warehouse_stock_balance',
                description: 'Показывает по каждой позиции номенклатуры приход, расход и текущий остаток на складе',
                sql_query: `SELECT
    dv.id AS item_id,
    dv.value AS item_name,
    json_extract(dv.data, '$.item_number') AS item_number,
    json_extract(dv.data, '$.unit') AS unit,
    json_extract(dv.data, '$.parameters') AS parameters,
    ROUND(COALESCE(receipts.received_quantity, 0), 3) AS received_quantity,
    ROUND(COALESCE(issues.issued_quantity, 0), 3) AS issued_quantity,
    ROUND(COALESCE(receipts.received_quantity, 0) - COALESCE(issues.issued_quantity, 0), 3) AS stock_balance
FROM directory_values dv
INNER JOIN directories d
    ON d.id = dv.directory_id
LEFT JOIN (
    SELECT
        CAST(json_extract(je.data, '$.item') AS INTEGER) AS item_id,
        SUM(CAST(json_extract(je.data, '$.quantity') AS REAL)) AS received_quantity
    FROM journal_entries je
    WHERE je.journal_template_id = (
        SELECT id
        FROM journal_templates
        WHERE code = 'warehouse_receipt'
           OR code LIKE 'warehouse_receipt_import%'
        ORDER BY CASE WHEN code = 'warehouse_receipt' THEN 0 ELSE 1 END, id
        LIMIT 1
    )
      AND je.deleted_at IS NULL
      AND je.status != 'rejected'
    GROUP BY CAST(json_extract(je.data, '$.item') AS INTEGER)
) receipts
    ON receipts.item_id = dv.id
LEFT JOIN (
    SELECT
        CAST(json_extract(je.data, '$.item') AS INTEGER) AS item_id,
        SUM(CAST(json_extract(je.data, '$.quantity') AS REAL)) AS issued_quantity
    FROM journal_entries je
    WHERE je.journal_template_id = (
        SELECT id
        FROM journal_templates
        WHERE code = 'warehouse_issue'
           OR code LIKE 'warehouse_issue_import%'
        ORDER BY CASE WHEN code = 'warehouse_issue' THEN 0 ELSE 1 END, id
        LIMIT 1
    )
      AND je.deleted_at IS NULL
      AND je.status != 'rejected'
    GROUP BY CAST(json_extract(je.data, '$.item') AS INTEGER)
) issues
    ON issues.item_id = dv.id
WHERE d.code = 'warehouse_nomenclature'
  AND dv.is_active = 1
ORDER BY item_name`,
                params_schema: []
            }
        };

        function loadReports(page = 1) {
            currentPage = page;

            $('#reportsTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">Загрузка...</td>
                </tr>
            `);

            $.ajax({
                url: "{{ route('admin.reports.list') }}",
                method: 'GET',
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
                        <td colspan="6" class="text-center text-secondary py-5">Отчёты не найдены</td>
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
                        <td><span class="badge bg-info text-dark">${paramsCount}</span></td>
                        <td>${item.is_active ? '<span class="badge bg-success">Активен</span>' : '<span class="badge bg-danger">Отключён</span>'}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-light" href="/admin/reports/${item.id}/export" title="Экспорт">
                                <i class="bi bi-download"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-info edit-report" data-id="${item.id}" title="Редактировать">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-report" data-id="${item.id}" title="Удалить">
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
                ? `Показано ${pagination.from}-${pagination.to} из ${pagination.total}`
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
                    <a href="#" class="page-link report-page" data-page="${current - 1}">Назад</a>
                </li>
            `;

            for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
                html += `
                    <li class="page-item ${i === current ? 'active' : ''}">
                        <a href="#" class="page-link report-page" data-page="${i}">${i}</a>
                    </li>
                `;
            }

            html += `
                <li class="page-item ${current === last ? 'disabled' : ''}">
                    <a href="#" class="page-link report-page" data-page="${current + 1}">Вперёд</a>
                </li>
            `;

            $('#paginationLinks').html(html);
        }

        function clearReportForm() {
            $('#reportForm')[0].reset();
            $('#reportId').val('');
            $('#reportIsActive').prop('checked', true);
            $('#reportPrintOrientation').val('portrait');
            params = [];
            paramIndex = 0;
            renderBuilder();
            renderParams();
            renderReportPrintVariables();
        }

        function reportTemplateToken(key) {
            return '{' + '{ ' + key + ' }' + '}';
        }

        function reportRowsLoopStartToken() {
            return '{' + '{#rows}' + '}';
        }

        function reportRowsLoopEndToken() {
            return '{' + '{/rows}' + '}';
        }

        function parseSqlOutputColumns() {
            let sql = $('#sqlQuery').val() || '';
            let matches = [...sql.matchAll(/\bas\s+["'`]?([a-zA-Z0-9_]+)["'`]?/gi)];

            return matches
                .map(match => (match[1] || '').trim())
                .filter(Boolean)
                .filter((value, index, array) => array.indexOf(value) === index);
        }

        function insertIntoReportPrintTextarea(text) {
            let textarea = document.getElementById('reportPrintBodyHtml');

            if (!textarea) {
                return;
            }

            let start = textarea.selectionStart || 0;
            let end = textarea.selectionEnd || 0;
            let value = textarea.value || '';

            textarea.value = value.substring(0, start) + text + value.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
        }

        function renderReportPrintVariables() {
            let variables = [
                { key: 'report.name', label: 'Название отчёта' },
                { key: 'report.description', label: 'Описание отчёта' },
                { key: 'report.row_count', label: 'Количество строк' },
                { key: 'report.columns_count', label: 'Количество колонок' },
                { key: 'print.title', label: 'Заголовок печати' },
                { key: 'print.date', label: 'Дата печати' },
                { key: 'table', label: 'Вся таблица результата' },
            ];

            params.forEach(function (param) {
                if (param.key) {
                    variables.push({
                        key: 'params.' + param.key,
                        label: param.label || param.key
                    });
                }
            });

            parseSqlOutputColumns().forEach(function (column) {
                variables.push({
                    key: 'row.' + column,
                    label: 'Поле строки: ' + column
                });
            });

            let html = variables.map(function (variable) {
                return `
                    <button type="button"
                            class="btn btn-sm btn-outline-light insert-report-print-variable"
                            data-variable="${escapeHtml(variable.key)}"
                            title="${escapeHtml(variable.label)}">
                        ${escapeHtml(reportTemplateToken(variable.key))}
                    </button>
                `;
            }).join('');

            $('#reportPrintVariablesBox').html(html || '<span class="text-secondary">Сначала настройте SQL и параметры.</span>');
        }

        function buildReportPrintSettings() {
            return {
                title: $('#reportPrintTitle').val(),
                orientation: $('#reportPrintOrientation').val(),
                body_html: $('#reportPrintBodyHtml').val()
            };
        }

        function reportPrintExampleSnippet(type) {
            if (type === 'rows-loop') {
                return `<h2>${reportTemplateToken('print.title')}</h2>\n<p>Сформирован: ${reportTemplateToken('print.date')}</p>\n<table>\n    <thead>\n    <tr>\n        <th>#</th>\n        <th>Значение</th>\n    </tr>\n    </thead>\n    <tbody>\n    ${reportRowsLoopStartToken()}\n    <tr>\n        <td>${reportTemplateToken('row.index')}</td>\n        <td>${reportTemplateToken('row.' + (parseSqlOutputColumns()[0] || 'column_name'))}</td>\n    </tr>\n    ${reportRowsLoopEndToken()}\n    </tbody>\n</table>`;
            }

            if (type === 'summary-act') {
                return `<h2 style="text-align:center;">${reportTemplateToken('print.title')}</h2>\n<p><strong>Дата печати:</strong> ${reportTemplateToken('print.date')}</p>\n<p><strong>Отчёт:</strong> ${reportTemplateToken('report.name')}</p>\n<p><strong>Строк в отчёте:</strong> ${reportTemplateToken('report.row_count')}</p>\n${reportTemplateToken('table')}\n<br>\n<table>\n    <tbody>\n    <tr>\n        <td style="border:0; width:50%;">Составил: ____________________</td>\n        <td style="border:0; width:50%;">Проверил: ____________________</td>\n    </tr>\n    </tbody>\n</table>`;
            }

            return `<h2>${reportTemplateToken('print.title')}</h2>\n<p>Дата печати: ${reportTemplateToken('print.date')}</p>\n${reportTemplateToken('table')}`;
        }

        function applyReportPreset(presetKey) {
            let preset = reportWizardPresets[presetKey];

            if (!preset) {
                return;
            }

            clearReportForm();
            $('#reportModalTitle').text(preset.title || 'Создать отчёт');
            $('#reportName').val(preset.name || '');
            $('#reportCode').val(preset.code || '');
            $('#reportDescription').val(preset.description || '');
            $('#sqlQuery').val(preset.sql_query || '');
            $('#reportIsActive').prop('checked', true);
            $('#reportPrintTitle').val(preset.print_settings?.title || preset.name || '');
            $('#reportPrintOrientation').val(preset.print_settings?.orientation || 'portrait');
            $('#reportPrintBodyHtml').val(preset.print_settings?.body_html || '');

            params = [];
            paramIndex = 0;

            (preset.params_schema || []).forEach(function (param) {
                addParam(param);
            });

            if (!(preset.params_schema || []).length) {
                renderParams();
            }

            renderReportPrintVariables();

            reportModal.show();
        }

        function getSelectedJournal() {
            let journalId = $('#builderJournalId').val();

            return journals.find(function (journal) {
                return String(journal.id) === String(journalId || '');
            }) || null;
        }

        function getSystemBuilderColumns() {
            return [
                { key: 'entry_id', label: 'ID записи', type: 'system', sql: 'je.id' },
                { key: 'entry_date', label: 'Дата', type: 'system', sql: 'je.entry_date' },
                { key: 'division_name', label: 'Подразделение', type: 'system', sql: 'd.name' },
                { key: 'user_name', label: 'Добавил', type: 'system', sql: 'u.name' },
                { key: 'status', label: 'Статус', type: 'system', sql: 'je.status' }
            ];
        }

        function buildJsonExtract(field, numeric = false) {
            let expr = `json_extract(je.data, '$.${field.key}')`;
            return numeric ? `CAST(${expr} AS REAL)` : expr;
        }

        function getJournalBuilderColumns() {
            let journal = getSelectedJournal();
            let schema = journal && Array.isArray(journal.schema) ? journal.schema : [];

            return schema.map(function (field) {
                return {
                    key: field.key,
                    label: field.label || field.key,
                    type: field.type || 'text',
                    sql: buildJsonExtract(field, false),
                    sql_numeric: buildJsonExtract(field, true)
                };
            });
        }

        function getAllBuilderColumns() {
            return getSystemBuilderColumns().concat(getJournalBuilderColumns());
        }

        function renderBuilderJournalOptions() {
            let html = '<option value="">Выберите журнал</option>';

            journals.forEach(function (journal) {
                html += `<option value="${journal.id}">${escapeHtml(journal.name)}</option>`;
            });

            $('#builderJournalId').html(html);
        }

        function renderBuilderColumns() {
            let columns = getAllBuilderColumns();

            if (!columns.length) {
                $('#builderColumnsBox').html('<div class="col-12 text-secondary small">Сначала выберите журнал.</div>');
                return;
            }

            let html = '';

            columns.forEach(function (column, index) {
                let checked = index < 5 ? 'checked' : '';

                html += `
                    <div class="col-md-4">
                        <label class="border rounded p-2 w-100 d-block">
                            <input type="checkbox" class="form-check-input me-2 builder-column-checkbox" value="${escapeHtml(column.key)}" ${checked}>
                            <span>${escapeHtml(column.label)}</span>
                        </label>
                    </div>
                `;
            });

            $('#builderColumnsBox').html(html);
        }

        function renderBuilderSummaryOptions() {
            let columns = getJournalBuilderColumns();
            let groupHtml = '<option value="">Выберите поле</option>';
            let sumHtml = '<option value="">Выберите числовое поле</option>';

            columns.forEach(function (column) {
                groupHtml += `<option value="${escapeHtml(column.key)}">${escapeHtml(column.label)}</option>`;

                if (['number', 'calc'].includes(column.type)) {
                    sumHtml += `<option value="${escapeHtml(column.key)}">${escapeHtml(column.label)}</option>`;
                }
            });

            $('#builderGroupField').html(groupHtml);
            $('#builderSumField').html(sumHtml);
        }

        function syncBuilderMode() {
            let isSummary = $('#builderMode').val() === 'summary';
            $('.builder-table-block').toggleClass('d-none', isSummary);
            $('.builder-summary-block').toggleClass('d-none', !isSummary);
        }

        function renderBuilder() {
            renderBuilderJournalOptions();
            renderBuilderColumns();
            renderBuilderSummaryOptions();
            syncBuilderMode();
            initSearchableSelects(document.getElementById('reportModal'));
        }

        function buildAutoParamsSchema() {
            return [
                {
                    key: 'date_from',
                    label: 'Дата от',
                    type: 'date',
                    required: 0
                },
                {
                    key: 'date_to',
                    label: 'Дата до',
                    type: 'date',
                    required: 0
                },
                {
                    key: 'division_id',
                    label: 'Подразделение',
                    type: 'directory',
                    source: 'divisions',
                    required: 0
                }
            ];
        }

        function applyGeneratedParamsSchema(schema) {
            params = [];
            paramIndex = 0;

            schema.forEach(function (param) {
                addParam(param);
            });
        }

        function generateSqlFromBuilder() {
            let journal = getSelectedJournal();

            if (!journal) {
                showToast('Выберите журнал для конструктора', 'warning');
                return;
            }

            let allColumns = getAllBuilderColumns();
            let columnsByKey = {};
            allColumns.forEach(function (column) {
                columnsByKey[column.key] = column;
            });

            let whereSql = `
WHERE je.journal_template_id = ${Number(journal.id)}
  AND (:date_from IS NULL OR je.entry_date >= :date_from)
  AND (:date_to IS NULL OR je.entry_date <= :date_to)
  AND (:division_id IS NULL OR je.division_id = :division_id)
`.trim();

            let fromSql = `
FROM journal_entries je
LEFT JOIN divisions d ON d.id = je.division_id
LEFT JOIN users u ON u.id = je.user_id
`.trim();

            if ($('#builderMode').val() === 'summary') {
                let groupKey = $('#builderGroupField').val();
                let sumKey = $('#builderSumField').val();
                let groupColumn = columnsByKey[groupKey];
                let sumColumn = columnsByKey[sumKey];

                if (!groupColumn || !sumColumn) {
                    showToast('Для группировки выберите поле группы и числовое поле суммы', 'warning');
                    return;
                }

                let groupExpr = groupColumn.sql || 'NULL';
                let sumExpr = sumColumn.sql_numeric || sumColumn.sql || '0';
                let sql = `
SELECT
    COALESCE(${groupExpr}, 'Без значения') AS group_value,
    SUM(${sumExpr}) AS total_value,
    COUNT(*) AS entries_count
${fromSql}
${whereSql}
GROUP BY COALESCE(${groupExpr}, '')
ORDER BY total_value DESC, group_value ASC
`.trim();

                $('#sqlQuery').val(sql);

                if (!$('#reportName').val().trim()) {
                    $('#reportName').val(`Сводка: ${journal.name}`);
                }
            } else {
                let selectedKeys = $('.builder-column-checkbox:checked').map(function () {
                    return $(this).val();
                }).get();

                if (!selectedKeys.length) {
                    showToast('Выберите хотя бы одну колонку отчёта', 'warning');
                    return;
                }

                let selectSql = selectedKeys.map(function (key) {
                    let column = columnsByKey[key];
                    let expr = column ? column.sql : null;
                    let alias = column ? column.key : key;
                    return `${expr} AS "${alias}"`;
                }).filter(Boolean).join(",\n    ");

                let sql = `
SELECT
    ${selectSql}
${fromSql}
${whereSql}
ORDER BY je.entry_date DESC, je.id DESC
`.trim();

                $('#sqlQuery').val(sql);

                if (!$('#reportName').val().trim()) {
                    $('#reportName').val(`Журнал: ${journal.name}`);
                }
            }

            applyGeneratedParamsSchema(buildAutoParamsSchema());
            showToast('SQL и параметры собраны', 'success');
        }

        function addParam(data = null) {
            paramIndex++;

            params.push({
                uid: paramIndex,
                key: data?.key || '',
                label: data?.label || '',
                type: data?.type || 'string',
                required: !!data?.required,
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
                    .filter(Boolean);
            });
        }

        function renderParams() {
            let html = '';

            params.forEach(function (param, index) {
                let directoriesOptions = `<option value="">Выберите справочник</option>`;

                directories.forEach(function (directory) {
                    let selected = String(param.directory_id) === String(directory.id) ? 'selected' : '';
                    directoriesOptions += `<option value="${directory.id}" ${selected}>${escapeHtml(directory.name)}</option>`;
                });

                let optionsText = param.options ? param.options.join('\n') : '';

                html += `
                    <div class="card mb-3 param-card" data-uid="${param.uid}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="fw-bold">Параметр #${index + 1}</div>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-param" data-uid="${param.uid}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Ключ</label>
                                    <input type="text" class="form-control param-key" value="${escapeHtml(param.key)}" placeholder="date_from">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Название</label>
                                    <input type="text" class="form-control param-label" value="${escapeHtml(param.label)}" placeholder="Дата от">
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
                                        <input class="form-check-input param-required" type="checkbox" ${param.required ? 'checked' : ''}>
                                        <label class="form-check-label">Обяз.</label>
                                    </div>
                                </div>

                                <div class="col-md-6 param-directory-block ${['directory', 'directory_text'].includes(param.type) ? '' : 'd-none'}">
                                    <label class="form-label">Справочник</label>
                                    <select class="form-select param-directory">${directoriesOptions}</select>
                                    <div class="text-secondary small mt-1">Для системных списков можно не выбирать справочник, а указать source.</div>
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
                                    <input type="text" class="form-control param-formula" value="${escapeHtml(param.formula)}">
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
            renderReportPrintVariables();
        }

        $('#addReportBtn').on('click', function () {
            clearReportForm();
            $('#reportModalTitle').text('Создать отчёт');
            reportModal.show();
        });

        $(document).on('click', '.report-preset-btn', function () {
            applyReportPreset($(this).data('preset'));
        });

        $('#importReportBtn').on('click', function () {
            $('#importReportForm')[0].reset();
            importReportModal.show();
        });

        $('#addParamBtn').on('click', function () {
            readParamsFromDom();
            addParam();
        });

        $('#generateReportSqlBtn').on('click', function () {
            generateSqlFromBuilder();
            renderReportPrintVariables();
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

        $(document).on('click', '.insert-report-print-variable', function () {
            insertIntoReportPrintTextarea(reportTemplateToken($(this).data('variable')));
        });

        $(document).on('click', '.insert-report-print-example', function () {
            $('#reportPrintBodyHtml').val(reportPrintExampleSnippet($(this).data('example-template')));
        });

        $('#sqlQuery').on('input', function () {
            renderReportPrintVariables();
        });

        $('#builderMode').on('change', function () {
            syncBuilderMode();
        });

        $('#builderJournalId').on('change', function () {
            renderBuilderColumns();
            renderBuilderSummaryOptions();
            initSearchableSelects(document.getElementById('reportModal'));
        });

        $('#reportForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#reportId').val();
            let url = id ? `/admin/reports/${id}` : "{{ route('admin.reports.store') }}";

            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    name: $('#reportName').val(),
                    code: $('#reportCode').val(),
                    description: $('#reportDescription').val(),
                    sql_query: $('#sqlQuery').val(),
                    is_active: $('#reportIsActive').is(':checked') ? 1 : 0,
                    params_schema: buildParamsSchema(),
                    print_settings: buildReportPrintSettings()
                },
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

        $('#importReportForm').on('submit', function (e) {
            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: "{{ route('admin.reports.import') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    showToast(response.message, 'success');
                    importReportModal.hide();
                    loadReports(1);
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
                url: `/admin/reports/${id}`,
                method: 'GET',
                success: function (response) {
                    let item = response.report;

                    $('#reportModalTitle').text('Редактировать отчёт');
                    $('#reportId').val(item.id);
                    $('#reportName').val(item.name);
                    $('#reportCode').val(item.code);
                    $('#reportDescription').val(item.description);
                    $('#sqlQuery').val(item.sql_query);
                    $('#reportIsActive').prop('checked', item.is_active);
                    $('#reportPrintTitle').val(item.print_settings ? (item.print_settings.title || '') : '');
                    $('#reportPrintOrientation').val(item.print_settings ? (item.print_settings.orientation || 'portrait') : 'portrait');
                    $('#reportPrintBodyHtml').val(item.print_settings ? (item.print_settings.body_html || '') : '');

                    params = [];
                    paramIndex = 0;

                    if (item.params_schema && item.params_schema.length) {
                        item.params_schema.forEach(function (param) {
                            addParam(param);
                        });
                    } else {
                        renderParams();
                    }

                    renderReportPrintVariables();

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
                url: `/admin/reports/${id}`,
                method: 'DELETE',
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

        renderBuilder();
        renderParams();
        loadReports();
    </script>
@endpush
