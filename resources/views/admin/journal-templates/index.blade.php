@extends('admin.layouts.app')

@section('title', 'Конструктор журналов')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Конструктор журналов</h2>
            <div class="text-secondary">
                Создание шаблонов журналов и настройка динамических полей
            </div>
        </div>

        <button class="btn btn-primary" id="addTemplateBtn">
            <i class="bi bi-plus-lg"></i>
            Создать журнал
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-7">
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Поиск по названию, коду или описанию">
                </div>

                <div class="col-md-3">
                    <select id="activeFilter" class="form-select">
                        <option value="">Все</option>
                        <option value="1">Активные</option>
                        <option value="0">Отключённые</option>
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
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Журнал</th>
                        <th>Код</th>
                        <th>Полей</th>
                        <th>Подразделения</th>
                        <th>Статус</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>

                    <tbody id="templatesTableBody">
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
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

    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="templateForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalTitle">
                        Создать журнал
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="templateId" name="id">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Название журнала</label>
                            <input type="text"
                                   class="form-control"
                                   id="templateName"
                                   name="name"
                                   placeholder="Например: Журнал выпуска продукции">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Код</label>
                            <input type="text"
                                   class="form-control"
                                   id="templateCode"
                                   name="code"
                                   placeholder="production_log">
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="templateIsActive"
                                       name="is_active"
                                       value="1"
                                       checked>

                                <label class="form-check-label" for="templateIsActive">
                                    Журнал активен
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control"
                                      id="templateDescription"
                                      name="description"
                                      rows="2"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Доступен подразделениям</label>

                            <select class="form-select"
                                    id="templateDivisions"
                                    name="division_ids[]"
                                    multiple
                                    size="5">
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}">
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="text-secondary small mt-1">
                                Если ничего не выбрано — журнал пока ни одному подразделению не назначен.
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Поля журнала</h5>
                            <div class="text-secondary small">
                                Добавьте поля формы. Ключ поля должен быть латиницей без пробелов.
                            </div>
                        </div>

                        <button type="button" class="btn btn-success btn-sm" id="addFieldBtn">
                            <i class="bi bi-plus-lg"></i>
                            Добавить поле
                        </button>
                    </div>

                    <div id="fieldsBuilder"></div>

                    <div class="alert alert-warning mt-3 d-none" id="noFieldsAlert">
                        В журнале должно быть хотя бы одно поле.
                    </div>

                    <div class="mt-4">
                        <label class="form-label">Итоговая JSON-схема</label>
                        <textarea class="form-control"
                                  id="schemaPreview"
                                  rows="8"
                                  readonly></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Сохранить журнал
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        let templateModal = new bootstrap.Modal(document.getElementById('templateModal'));

        let currentPage = 1;
        let fields = [];
        let fieldIndex = 0;

        const directories = @json($directories);

        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }

            return $('<div>').text(text).html();
        }

        function loadTemplates(page = 1) {
            currentPage = page;

            $('#templatesTableBody').html(`
            <tr>
                <td colspan="7" class="text-center text-secondary py-5">
                    Загрузка...
                </td>
            </tr>
        `);

            $.ajax({
                url: "{{ route('admin.journal-templates.list') }}",
                method: "GET",
                data: {
                    page: page,
                    search: $('#searchInput').val(),
                    is_active: $('#activeFilter').val()
                },
                success: function (response) {
                    renderTemplates(response.items);
                    renderPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderTemplates(items) {
            let html = '';

            if (!items || items.length === 0) {
                $('#templatesTableBody').html(`
                <tr>
                    <td colspan="7" class="text-center text-secondary py-5">
                        Журналы не найдены
                    </td>
                </tr>
            `);
                return;
            }

            items.forEach(function (item) {
                let schemaCount = item.schema ? item.schema.length : 0;

                let divisionsHtml = '<span class="text-secondary">—</span>';

                if (item.divisions && item.divisions.length > 0) {
                    divisionsHtml = item.divisions.map(function (division) {
                        return `<span class="badge bg-secondary me-1">${escapeHtml(division.name)}</span>`;
                    }).join('');
                }

                let activeBadge = item.is_active
                    ? '<span class="badge bg-success">Активен</span>'
                    : '<span class="badge bg-danger">Отключён</span>';

                html += `
                <tr>
                    <td>${item.id}</td>

                    <td>
                        <div class="fw-semibold">${escapeHtml(item.name)}</div>
                        <div class="text-secondary small">${escapeHtml(item.description || '')}</div>
                    </td>

                    <td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">—</span>'}</td>

                    <td>
                        <span class="badge bg-info text-dark">${schemaCount}</span>
                    </td>

                    <td>${divisionsHtml}</td>

                    <td>${activeBadge}</td>

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
                <a class="page-link template-page" href="#" data-page="${current - 1}">Назад</a>
            </li>
        `;

            let start = Math.max(1, current - 2);
            let end = Math.min(last, current + 2);

            for (let i = start; i <= end; i++) {
                html += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link template-page" href="#" data-page="${i}">${i}</a>
                </li>
            `;
            }

            html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <a class="page-link template-page" href="#" data-page="${current + 1}">Вперёд</a>
            </li>
        `;

            $('#paginationLinks').html(html);
        }

        function clearTemplateForm() {
            $('#templateForm')[0].reset();

            $('#templateId').val('');
            $('#templateDivisions').val([]);
            $('#templateIsActive').prop('checked', true);

            fields = [];
            fieldIndex = 0;

            renderFields();
        }

        function addField(data = null) {
            fieldIndex++;

            fields.push({
                uid: fieldIndex,
                key: data?.key || '',
                label: data?.label || '',
                type: data?.type || 'string',
                required: data?.required || false,
                directory_id: data?.directory_id || '',
                options: data?.options || [],
                formula: data?.formula || ''
            });

            renderFields();
        }

        function removeField(uid) {
            fields = fields.filter(function (field) {
                return field.uid !== uid;
            });

            renderFields();
        }

        function moveField(uid, direction) {
            let index = fields.findIndex(field => field.uid === uid);

            if (index < 0) {
                return;
            }

            let newIndex = index + direction;

            if (newIndex < 0 || newIndex >= fields.length) {
                return;
            }

            let temp = fields[index];
            fields[index] = fields[newIndex];
            fields[newIndex] = temp;

            renderFields();
        }

        function readFieldsFromDom() {
            fields.forEach(function (field) {
                let prefix = `.field-card[data-uid="${field.uid}"]`;

                field.key = $(`${prefix} .field-key`).val();
                field.label = $(`${prefix} .field-label`).val();
                field.type = $(`${prefix} .field-type`).val();
                field.required = $(`${prefix} .field-required`).is(':checked');
                field.directory_id = $(`${prefix} .field-directory`).val();
                field.formula = $(`${prefix} .field-formula`).val();

                let optionsText = $(`${prefix} .field-options`).val() || '';

                field.options = optionsText
                    .split('\n')
                    .map(item => item.trim())
                    .filter(item => item.length > 0);
            });
        }

        function renderFields() {
            let html = '';

            if (fields.length === 0) {
                $('#fieldsBuilder').html('');
                $('#noFieldsAlert').removeClass('d-none');
                updateSchemaPreview();
                return;
            }

            $('#noFieldsAlert').addClass('d-none');

            fields.forEach(function (field, index) {
                let directoriesOptions = `<option value="">Выберите справочник</option>`;

                directories.forEach(function (directory) {
                    let selected = String(field.directory_id) === String(directory.id) ? 'selected' : '';

                    directoriesOptions += `
                    <option value="${directory.id}" ${selected}>
                        ${escapeHtml(directory.name)}
                    </option>
                `;
                });

                let optionsText = field.options ? field.options.join('\n') : '';

                html += `
                <div class="card mb-3 field-card" data-uid="${field.uid}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold">
                                Поле #${index + 1}
                            </div>

                            <div class="btn-group btn-group-sm">
                                <button type="button"
                                        class="btn btn-outline-light move-field"
                                        data-uid="${field.uid}"
                                        data-direction="-1">
                                    ↑
                                </button>

                                <button type="button"
                                        class="btn btn-outline-light move-field"
                                        data-uid="${field.uid}"
                                        data-direction="1">
                                    ↓
                                </button>

                                <button type="button"
                                        class="btn btn-outline-danger remove-field"
                                        data-uid="${field.uid}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Ключ поля</label>
                                <input type="text"
                                       class="form-control field-key"
                                       value="${escapeHtml(field.key)}"
                                       placeholder="equipment">
                                <div class="text-secondary small">
                                    Только латиница, цифры, _
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Название поля</label>
                                <input type="text"
                                       class="form-control field-label"
                                       value="${escapeHtml(field.label)}"
                                       placeholder="Оборудование">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Тип поля</label>
                                <select class="form-select field-type">
                                    <option value="string" ${field.type === 'string' ? 'selected' : ''}>Строка</option>
                                    <option value="number" ${field.type === 'number' ? 'selected' : ''}>Число</option>
                                    <option value="date" ${field.type === 'date' ? 'selected' : ''}>Дата</option>
                                    <option value="time" ${field.type === 'time' ? 'selected' : ''}>Время</option>
                                    <option value="list" ${field.type === 'list' ? 'selected' : ''}>Список</option>
                                    <option value="directory" ${field.type === 'directory' ? 'selected' : ''}>Справочник ID</option>
                                    <option value="directory_text" ${field.type === 'directory_text' ? 'selected' : ''}>Справочник текстом</option>
                                    <option value="calc" ${field.type === 'calc' ? 'selected' : ''}>Вычисляемое</option>
                                </select>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input field-required"
                                           type="checkbox"
                                           ${field.required ? 'checked' : ''}>
                                    <label class="form-check-label">
                                        Обязательное
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6 field-directory-block ${['directory', 'directory_text'].includes(field.type) ? '' : 'd-none'}">
                                <label class="form-label">Справочник</label>
                                <select class="form-select field-directory">
                                    ${directoriesOptions}
                                </select>
                            </div>

                            <div class="col-md-6 field-options-block ${field.type === 'list' ? '' : 'd-none'}">
                                <label class="form-label">Варианты списка</label>
                                <textarea class="form-control field-options"
                                          rows="4"
                                          placeholder="Каждый вариант с новой строки">${escapeHtml(optionsText)}</textarea>
                            </div>

                            <div class="col-md-6 field-formula-block ${field.type === 'calc' ? '' : 'd-none'}">
                                <label class="form-label">Формула</label>
                                <input type="text"
                                       class="form-control field-formula"
                                       value="${escapeHtml(field.formula)}"
                                       placeholder="count * price">
                                <div class="text-secondary small">
                                    Пока сохраняется как текст. Расчёт добавим позже.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            });

            $('#fieldsBuilder').html(html);
            updateSchemaPreview();
        }

        function buildSchemaForSubmit() {
            readFieldsFromDom();

            return fields.map(function (field) {
                let item = {
                    key: field.key,
                    label: field.label,
                    type: field.type,
                    required: field.required ? 1 : 0
                };

                if (field.type === 'directory' || field.type === 'directory_text') {
                    item.directory_id = field.directory_id;
                }

                if (field.type === 'list') {
                    item.options = field.options || [];
                }

                if (field.type === 'calc') {
                    item.formula = field.formula || '';
                }

                return item;
            });
        }

        function updateSchemaPreview() {
            let schema = buildSchemaForSubmit();

            $('#schemaPreview').val(JSON.stringify(schema, null, 2));
        }

        $('#addTemplateBtn').on('click', function () {
            clearTemplateForm();

            $('#templateModalTitle').text('Создать журнал');
            addField();

            templateModal.show();
        });

        $('#addFieldBtn').on('click', function () {
            readFieldsFromDom();
            addField();
        });

        $(document).on('click', '.remove-field', function () {
            let uid = parseInt($(this).data('uid'));
            removeField(uid);
        });

        $(document).on('click', '.move-field', function () {
            readFieldsFromDom();

            let uid = parseInt($(this).data('uid'));
            let direction = parseInt($(this).data('direction'));

            moveField(uid, direction);
        });

        $(document).on('change', '.field-type', function () {
            readFieldsFromDom();
            renderFields();
        });

        $(document).on('input change', '.field-key, .field-label, .field-required, .field-directory, .field-options, .field-formula', function () {
            updateSchemaPreview();
        });

        $('#templateForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#templateId').val();

            let url = id
                ? "/admin/journal-templates/" + id
                : "{{ route('admin.journal-templates.store') }}";

            let payload = {
                name: $('#templateName').val(),
                code: $('#templateCode').val(),
                description: $('#templateDescription').val(),
                is_active: $('#templateIsActive').is(':checked') ? 1 : 0,
                division_ids: $('#templateDivisions').val() || [],
                schema: buildSchemaForSubmit()
            };

            $.ajax({
                url: url,
                method: "POST",
                data: payload,
                success: function (response) {
                    showToast(response.message, 'success');
                    templateModal.hide();
                    loadTemplates(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-template', function () {
            let id = $(this).data('id');

            clearTemplateForm();

            $.ajax({
                url: "/admin/journal-templates/" + id,
                method: "GET",
                success: function (response) {
                    let item = response.template;

                    $('#templateModalTitle').text('Редактировать журнал');

                    $('#templateId').val(item.id);
                    $('#templateName').val(item.name);
                    $('#templateCode').val(item.code);
                    $('#templateDescription').val(item.description);
                    $('#templateIsActive').prop('checked', item.is_active);
                    $('#templateDivisions').val(item.division_ids);

                    fields = [];
                    fieldIndex = 0;

                    if (item.schema && item.schema.length > 0) {
                        item.schema.forEach(function (field) {
                            addField(field);
                        });
                    } else {
                        addField();
                    }

                    templateModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-template', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить журнал?')) {
                return;
            }

            $.ajax({
                url: "/admin/journal-templates/" + id,
                method: "DELETE",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadTemplates(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#searchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadTemplates(1);
            }
        });

        $('#activeFilter').on('change', function () {
            loadTemplates(1);
        });

        $('#resetFilters').on('click', function () {
            $('#searchInput').val('');
            $('#activeFilter').val('');
            loadTemplates(1);
        });

        $(document).on('click', '.template-page', function (e) {
            e.preventDefault();

            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadTemplates(page);
            }
        });

        loadTemplates();
    </script>
@endpush
