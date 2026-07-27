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
                            Отметьте колонки, которые должны попасть в печатную форму, и при необходимости поменяйте их
                            названия. Порядок можно менять стрелками. Фильтры пользователь задаёт в журнале перед печатью.
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                        <div>
                            <div class="fw-bold">Колонки печати</div>
                            <div class="text-secondary small">Служебные поля и поля выбранного журнала.</div>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="showSignatures" checked>
                            <label class="form-check-label" for="showSignatures">Показывать подписи</label>
                        </div>
                    </div>

                    <div id="columnsBox" class="row g-2"></div>
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
        const journals = @json($journals->map(function ($journal) {
            return [
                'id' => $journal->id,
                'name' => $journal->name,
                'schema' => $journal->schema ?? [],
            ];
        })->values()->all());

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

        function renderColumns(selectedColumns = null) {
            let journal = getJournal($('#journalTemplateId').val());
            let allColumns = allColumnsForJournal(journal);
            let selectedMap = {};
            let selectedOrder = selectedColumns && selectedColumns.length ? selectedColumns : defaultColumnsForJournal(journal);

            selectedOrder.forEach(function (column, index) {
                selectedMap[columnId(column)] = {
                    label: column.label,
                    order: index + 1,
                };
            });

            let html = '';

            allColumns.forEach(function (column, index) {
                let id = columnId(column);
                let selected = selectedMap[id] || null;

                html += `
                    <div class="col-md-6 column-card" data-column-id="${escapeHtml(id)}">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex gap-2 align-items-start">
                                <input class="form-check-input column-enabled mt-2" type="checkbox"
                                       data-type="${escapeHtml(column.type)}"
                                       data-key="${escapeHtml(column.key)}"
                                       ${selected ? 'checked' : ''}>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${escapeHtml(column.label)}</div>
                                    <div class="text-secondary small">${column.type === 'system' ? 'Служебная колонка' : 'Поле журнала'}: <code>${escapeHtml(column.key)}</code></div>
                                    <div class="row g-2 mt-1">
                                        <div class="col-8">
                                            <input type="text" class="form-control form-control-sm column-label"
                                                   value="${escapeHtml(selected?.label || column.label)}"
                                                   placeholder="Название колонки">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" class="form-control form-control-sm column-order"
                                                   value="${escapeHtml(selected?.order || index + 1)}"
                                                   min="1" title="Порядок">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#columnsBox').html(html || '<div class="text-secondary">Сначала выберите журнал</div>');
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

                    html += `
                        <tr>
                            <td>
                                <div class="fw-bold">${escapeHtml(item.name)}</div>
                                <div class="text-secondary small">${escapeHtml(item.title || 'Заголовок берётся из журнала')}</div>
                            </td>
                            <td>${escapeHtml(item.journal_template?.name || '—')}</td>
                            <td>${columnsCount}</td>
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
                renderColumns(item.settings?.columns || []);
                templateModal.show();
            });
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

            if (!columns.length) {
                showToast('Выберите хотя бы одну колонку для печати', 'warning');
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
