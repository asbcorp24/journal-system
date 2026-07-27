@extends($layout)

@section('title', $pageTitle)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">{{ $pageTitle }}</h2>
            <div class="text-secondary">
                Создавайте печатные формы для журналов: пользователь выберет шаблон перед печатью.
            </div>
        </div>

        <button class="btn btn-primary" id="addTemplateBtn">
            <i class="bi bi-plus-lg"></i>
            Создать шаблон
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <input type="text" class="form-control" id="searchInput" placeholder="Поиск по названию или журналу">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-light w-100" id="searchBtn">
                        <i class="bi bi-search"></i>
                        Найти
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
                        <th>Шаблон</th>
                        <th>Журнал</th>
                        <th>Колонки</th>
                        <th>Автор</th>
                        <th>Статус</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>
                    <tbody id="templatesTableBody">
                    </tbody>
                </table>
            </div>

            <nav class="mt-3">
                <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="templateForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalTitle">Создать шаблон печати</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="templateId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Название шаблона</label>
                            <input type="text" class="form-control" id="templateName" required
                                   placeholder="Например: Акт списания">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Журнал</label>
                            <select class="form-select" id="journalTemplateId" required>
                                <option value="">Выберите журнал</option>
                                @foreach($journals as $journal)
                                    <option value="{{ $journal->id }}">
                                        {{ $journal->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Заголовок при печати</label>
                            <input type="text" class="form-control" id="templateTitle"
                                   placeholder="Если пусто, будет название журнала">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Ориентация</label>
                            <select class="form-select" id="templateOrientation">
                                <option value="landscape">Альбомная</option>
                                <option value="portrait">Книжная</option>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="templateIsActive" checked>
                                <label class="form-check-label" for="templateIsActive">Активен</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Описание / подсказка пользователю</label>
                            <textarea class="form-control" id="templateDescription" rows="2"
                                      placeholder="Например: Использовать для печати выдачи деталей за выбранный период"></textarea>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <div class="fw-bold mb-1">Подсказка по шаблону печати</div>
                        <div class="small">
                            Напишите HTML-шаблон ниже. Можно вставлять поля журнала через двойные фигурные скобки:
                            <code>@{{ part }}</code>, <code>@{{ quantity }}</code>, <code>@{{ entry.date }}</code>.
                            Для печати всей таблицы журнала используйте <code>@{{ table }}</code>.
                            Для ручной разметки списка записей используйте цикл
                            <code>@{{#entries}} ... @{{/entries}}</code>.
                        </div>
                    </div>

                    <div class="mt-4">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active"
                                        type="button"
                                        data-bs-toggle="tab"
                                        data-bs-target="#templateEditorTab"
                                        role="tab">
                                    Редактор
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link"
                                        type="button"
                                        data-bs-toggle="tab"
                                        data-bs-target="#templateExamplesTab"
                                        role="tab">
                                    Примеры
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content border border-top-0 rounded-bottom p-3">
                            <div class="tab-pane fade show active" id="templateEditorTab" role="tabpanel">
                                <label class="form-label">HTML-шаблон печати</label>
                                <textarea class="form-control font-monospace"
                                          id="templateBodyHtml"
                                          rows="11"
                                          placeholder="<h2>Акт списания</h2>&#10;<p>Дата: @{{ entry.date }}</p>&#10;<p>Деталь: @{{ part }}</p>&#10;@{{ table }}"></textarea>
                                <div class="text-secondary small mt-2">
                                    Если вставить <code>@{{ table }}</code>, система напечатает всю таблицу записей по текущим фильтрам.
                                    Если таблицы нет, шаблон будет выведен отдельно для каждой записи.
                                </div>
                                <div class="mt-2">
                                    <div class="fw-bold small mb-1">Доступные переменные</div>
                                    <div class="d-flex flex-wrap gap-2 small" id="templateVariablesBox"></div>
                                </div>
                                <div class="mt-3">
                                    <div class="fw-bold small mb-1">Готовые вставки</div>
                                    <div class="d-flex flex-wrap gap-2 small" id="templateTablesBox"></div>
                                    <div class="text-secondary small mt-1">
                                        Кнопки вставляют готовый HTML или переменную таблицы в поле выше.
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="templateExamplesTab" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100">
                                            <div class="fw-bold mb-1">Заголовок + вся таблица</div>
                                            <div class="text-secondary small mb-2">Самый частый вариант отчёта по журналу.</div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info insert-example-template"
                                                    data-example-template="full-table">
                                                Вставить пример
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100">
                                            <div class="fw-bold mb-1">Карточка записи</div>
                                            <div class="text-secondary small mb-2">Каждая запись печатается отдельным блоком.</div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info insert-example-template"
                                                    data-example-template="entry-card">
                                                Вставить пример
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100">
                                            <div class="fw-bold mb-1">Таблица через цикл</div>
                                            <div class="text-secondary small mb-2">Ручная таблица, где строки создаются циклом.</div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info insert-example-template"
                                                    data-example-template="loop-table">
                                                Вставить пример
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100">
                                            <div class="fw-bold mb-1">Акт с подписью</div>
                                            <div class="text-secondary small mb-2">Заготовка с датой, журналом и строкой подписи.</div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info insert-example-template"
                                                    data-example-template="act">
                                                Вставить пример
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="showSignatures" checked>
                            <label class="form-check-label" for="showSignatures">Показывать подписи</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const routes = @json($routes);
        const journals = @json($journalsForScript);

        const systemColumns = [
            {type: 'system', key: 'number', label: '№'},
            {type: 'system', key: 'entry_date', label: 'Дата'},
            {type: 'system', key: 'created_by', label: 'Добавил'},
            {type: 'system', key: 'division', label: 'Подразделение'},
            {type: 'system', key: 'status', label: 'Статус'},
            {type: 'system', key: 'checked_by', label: 'Проверил'},
            {type: 'system', key: 'last_comment', label: 'Комментарий'},
        ];

        let templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
        let currentPage = 1;

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }

        function templateRoute(id) {
            return routes.template.replace('__ID__', id);
        }

        function getJournal(id) {
            return journals.find(item => String(item.id) === String(id));
        }

        function defaultColumnsForJournal(journal) {
            let columns = [
                systemColumns[0],
                systemColumns[1],
            ];

            (journal?.schema || []).forEach(function (field) {
                if (field.key) {
                    columns.push({
                        type: 'field',
                        key: field.key,
                        label: field.label || field.key,
                    });
                }
            });

            columns.push(systemColumns[2], systemColumns[3], systemColumns[4]);

            return columns;
        }

        function allColumnsForJournal(journal) {
            let columns = [...systemColumns];

            (journal?.schema || []).forEach(function (field) {
                if (field.key) {
                    columns.push({
                        type: 'field',
                        key: field.key,
                        label: field.label || field.key,
                    });
                }
            });

            return columns;
        }

        function columnId(column) {
            return `${column.type}:${column.key}`;
        }

        function templateToken(key) {
            return '{' + '{ ' + key + ' }' + '}';
        }

        function entriesLoopStartToken() {
            return '{' + '{#entries}' + '}';
        }

        function entriesLoopEndToken() {
            return '{' + '{/entries}' + '}';
        }

        function insertIntoTemplateTextarea(text) {
            let textarea = document.getElementById('templateBodyHtml');
            let start = textarea.selectionStart || 0;
            let end = textarea.selectionEnd || 0;
            let value = textarea.value;

            textarea.value = value.substring(0, start) + text + value.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
        }

        function renderColumns(selectedColumns = null) {
            let journal = getJournal($('#journalTemplateId').val());
            renderTemplateVariables(journal);
            renderTemplateTableButtons(journal);
        }

        function variableKeyForColumn(column) {
            if (column.type === 'field') {
                return column.key;
            }

            const map = {
                number: 'entry.number',
                entry_date: 'entry.date',
                created_by: 'entry.created_by',
                division: 'entry.division',
                status: 'entry.status',
                checked_by: 'entry.checked_by',
                last_comment: 'entry.comment',
            };

            return map[column.key] || column.key;
        }

        function renderTemplateVariables(journal) {
            let variables = [
                {key: 'entry.number', label: 'Номер строки'},
                {key: 'entry.date', label: 'Дата записи'},
                {key: 'entry.created_by', label: 'Кто добавил'},
                {key: 'entry.division', label: 'Подразделение'},
                {key: 'entry.status', label: 'Статус'},
                {key: 'entry.checked_by', label: 'Проверил'},
                {key: 'entry.comment', label: 'Комментарий'},
                {key: 'journal.name', label: 'Название журнала'},
                {key: 'print.date', label: 'Дата печати'},
                {key: 'table', label: 'Вся таблица журнала'},
            ];

            (journal?.schema || []).forEach(function (field) {
                if (field.key) {
                    variables.push({
                        key: field.key,
                        label: field.label || field.key,
                    });
                }
            });

            let html = variables.map(function (variable) {
                return `
                    <button type="button"
                            class="btn btn-sm btn-outline-light insert-variable"
                            data-variable="${escapeHtml(variable.key)}"
                            title="${escapeHtml(variable.label)}">
                        @{{ ${escapeHtml(variable.key)} }}
                    </button>
                `;
            }).join('');

            $('#templateVariablesBox').html(html || '<span class="text-secondary">Сначала выберите журнал</span>');
        }

        function renderTemplateTableButtons(journal) {
            let disabled = journal ? '' : 'disabled';
            let html = `
                <button type="button" class="btn btn-sm btn-outline-info insert-table-template"
                        data-table-template="whole" ${disabled}>
                    Вся таблица журнала
                </button>
                <button type="button" class="btn btn-sm btn-outline-info insert-table-template"
                        data-table-template="vertical" ${disabled}>
                    Таблица записи: поле - значение
                </button>
                <button type="button" class="btn btn-sm btn-outline-info insert-table-template"
                        data-table-template="loop" ${disabled}>
                    Цикл по записям
                </button>
            `;

            $('#templateTablesBox').html(html);
        }

        function htmlTableSnippet(type) {
            let journal = getJournal($('#journalTemplateId').val());

            if (!journal) {
                return '';
            }

            let columns = [];

            if (type === 'whole') {
                return templateToken('table');
            }

            if (type === 'loop') {
                return `${entriesLoopStartToken()}\n<section>\n    <h3>Запись №${templateToken('entry.number')}</h3>\n    <p>Дата: ${templateToken('entry.date')}</p>\n</section>\n${entriesLoopEndToken()}\n`;
            }

            columns = (journal.schema || [])
                .filter(field => field.key)
                .map(field => ({
                    type: 'field',
                    key: field.key,
                    label: field.label || field.key,
                }));

            let rows = columns
                .map(column => `    <tr>\n        <th>${escapeHtml(column.label)}</th>\n        <td>${templateToken(column.key)}</td>\n    </tr>`)
                .join('\n');

            return `<table>\n    <tbody>\n${rows}\n    </tbody>\n</table>\n`;
        }

        function exampleSnippet(type) {
            if (type === 'entry-card') {
                return `<section>\n    <h2>${templateToken('journal.name')}</h2>\n    <p><strong>Дата:</strong> ${templateToken('entry.date')}</p>\n    <table>\n        <tbody>\n            <tr>\n                <th>Подразделение</th>\n                <td>${templateToken('entry.division')}</td>\n            </tr>\n            <tr>\n                <th>Статус</th>\n                <td>${templateToken('entry.status')}</td>\n            </tr>\n        </tbody>\n    </table>\n</section>\n`;
            }

            if (type === 'act') {
                return `<h2 style="text-align:center;">Акт по журналу ${templateToken('journal.name')}</h2>\n<p><strong>Дата формирования:</strong> ${templateToken('print.date')}</p>\n<p><strong>Журнал:</strong> ${templateToken('journal.name')}</p>\n${templateToken('table')}\n<br>\n<table>\n    <tbody>\n    <tr>\n        <td style="border:0; width:50%;">Ответственный: ____________________</td>\n        <td style="border:0; width:50%;">Проверил: ____________________</td>\n    </tr>\n    </tbody>\n</table>\n`;
            }

            if (type === 'loop-table') {
                return `<h2>${templateToken('journal.name')}</h2>\n<p>Дата печати: ${templateToken('print.date')}</p>\n<table>\n    <thead>\n    <tr>\n        <th>№</th>\n        <th>Дата</th>\n        <th>Статус</th>\n    </tr>\n    </thead>\n    <tbody>\n    ${entriesLoopStartToken()}\n    <tr>\n        <td>${templateToken('entry.number')}</td>\n        <td>${templateToken('entry.date')}</td>\n        <td>${templateToken('entry.status')}</td>\n    </tr>\n    ${entriesLoopEndToken()}\n    </tbody>\n</table>\n`;
            }

            return `<h2>${templateToken('journal.name')}</h2>\n<p>Дата печати: ${templateToken('print.date')}</p>\n${templateToken('table')}\n`;
        }

        function readColumns() {
            let columns = [];

            $('.column-card').each(function () {
                let card = $(this);
                let checkbox = card.find('.column-enabled');

                if (!checkbox.is(':checked')) {
                    return;
                }

                columns.push({
                    type: checkbox.data('type'),
                    key: checkbox.data('key'),
                    label: card.find('.column-label').val() || checkbox.data('key'),
                    order: parseInt(card.find('.column-order').val() || '999', 10),
                });
            });

            return columns
                .sort((a, b) => a.order - b.order)
                .map(function (column) {
                    delete column.order;
                    return column;
                });
        }

        function loadTemplates(page = 1) {
            currentPage = page;

            $.get(routes.list, {
                page,
                search: $('#searchInput').val(),
            }).done(function (response) {
                let html = '';

                if (!response.items.length) {
                    html = '<tr><td colspan="6" class="text-center text-secondary py-4">Шаблоны не найдены</td></tr>';
                }

                response.items.forEach(function (item) {
                    let columnsCount = item.settings?.columns?.length || 0;
                    let hasHtml = !!(item.settings?.body_html || '').trim();
                    let hasTable = !!(item.settings?.body_html || '').match(/\{\{\s*table\s*\}\}/);

                    html += `
                        <tr>
                            <td>
                                <div class="fw-bold">${escapeHtml(item.name)}</div>
                                <div class="text-secondary small">${escapeHtml(item.title || 'Заголовок берётся из журнала')}</div>
                            </td>
                            <td>${escapeHtml(item.journal_template?.name || '—')}</td>
                            <td>
                                ${hasHtml ? '<span class="badge bg-info me-1">HTML</span>' : ''}
                                ${hasTable ? '<span class="badge bg-success me-1">Таблица</span>' : ''}
                                ${columnsCount ? `${columnsCount} кол.` : 'textarea'}
                            </td>
                            <td>${escapeHtml(item.creator?.name || 'Суперадмин')}</td>
                            <td>
                                <span class="badge ${item.is_active ? 'bg-success' : 'bg-secondary'}">
                                    ${item.is_active ? 'Активен' : 'Выключен'}
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-info edit-template" data-id="${item.id}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-template" data-id="${item.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                $('#templatesTableBody').html(html);
                renderPagination(response.pagination);
            });
        }

        function renderPagination(pagination) {
            if (!pagination || pagination.last_page <= 1) {
                $('#pagination').html('');
                return;
            }

            let html = '';

            for (let i = 1; i <= pagination.last_page; i++) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link templates-page" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }

            $('#pagination').html(html);
        }

        function resetForm() {
            $('#templateForm')[0].reset();
            $('#templateId').val('');
            $('#templateIsActive').prop('checked', true);
            $('#showSignatures').prop('checked', true);
            $('#templateBodyHtml').val('');
            renderColumns([]);
        }

        $('#addTemplateBtn').on('click', function () {
            resetForm();
            $('#templateModalTitle').text('Создать шаблон печати');
            templateModal.show();
        });

        $('#journalTemplateId').on('change', function () {
            renderColumns();
        });

        $('#searchBtn').on('click', function () {
            loadTemplates(1);
        });

        $('#searchInput').on('keydown', function (event) {
            if (event.key === 'Enter') {
                loadTemplates(1);
            }
        });

        $(document).on('click', '.templates-page', function (event) {
            event.preventDefault();
            loadTemplates($(this).data('page'));
        });

        $(document).on('click', '.edit-template', function () {
            let id = $(this).data('id');

            $.get(templateRoute(id)).done(function (response) {
                let item = response.template;

                resetForm();
                $('#templateModalTitle').text('Редактировать шаблон печати');
                $('#templateId').val(item.id);
                $('#templateName').val(item.name);
                $('#journalTemplateId').val(item.journal_template_id);
                $('#templateTitle').val(item.title || '');
                $('#templateDescription').val(item.description || '');
                $('#templateOrientation').val(item.settings?.orientation || 'landscape');
                $('#templateIsActive').prop('checked', !!item.is_active);
                $('#showSignatures').prop('checked', !!item.settings?.show_signatures);
                $('#templateBodyHtml').val(item.settings?.body_html || '');
                renderColumns(item.settings?.columns || []);
                templateModal.show();
            });
        });

        $(document).on('click', '.insert-variable', function () {
            insertIntoTemplateTextarea(templateToken($(this).data('variable')));
        });

        $(document).on('click', '.insert-table-template', function () {
            let snippet = htmlTableSnippet($(this).data('table-template'));

            if (!snippet) {
                showToast('Сначала выберите журнал', 'warning');
                return;
            }

            insertIntoTemplateTextarea(snippet);
        });

        $(document).on('click', '.insert-example-template', function () {
            insertIntoTemplateTextarea(exampleSnippet($(this).data('example-template')));
            bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#templateEditorTab"]')).show();
        });

        $(document).on('click', '.delete-template', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить шаблон печати?')) {
                return;
            }

            $.ajax({
                url: templateRoute(id),
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}',
                },
            }).done(function (response) {
                showToast(response.message || 'Шаблон удалён', 'success');
                loadTemplates(currentPage);
            }).fail(function (xhr) {
                showToast(xhr.responseJSON?.message || 'Ошибка удаления', 'danger');
            });
        });

        $('#templateForm').on('submit', function (event) {
            event.preventDefault();

            let id = $('#templateId').val();
            let url = id ? templateRoute(id) : routes.store;
            let columns = readColumns();
            let bodyHtml = $('#templateBodyHtml').val().trim();

            if (!columns.length && !bodyHtml) {
                showToast('Выберите хотя бы одну колонку или заполните HTML-шаблон', 'warning');
                return;
            }

            $.ajax({
                url,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    journal_template_id: $('#journalTemplateId').val(),
                    name: $('#templateName').val(),
                    title: $('#templateTitle').val(),
                    description: $('#templateDescription').val(),
                    is_active: $('#templateIsActive').is(':checked') ? 1 : 0,
                    settings: {
                        orientation: $('#templateOrientation').val(),
                        show_signatures: $('#showSignatures').is(':checked') ? 1 : 0,
                        body_html: bodyHtml,
                        columns,
                    },
                },
            }).done(function (response) {
                showToast(response.message || 'Шаблон сохранён', 'success');
                templateModal.hide();
                loadTemplates(currentPage);
            }).fail(function (xhr) {
                showToast(xhr.responseJSON?.message || 'Ошибка сохранения', 'danger');
            });
        });

        loadTemplates();
    </script>
@endpush
