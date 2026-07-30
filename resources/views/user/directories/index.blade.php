@extends('user.layouts.app')

@section('title', 'Справочники')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Справочники</h2>
            <div class="text-secondary">
                Доступные справочники и их значения
            </div>
        </div>

        @if(in_array(session('user_role'), ['foreman', 'admin']))
            <button class="btn btn-primary" id="addDirectoryValueBtn" disabled>
                <i class="bi bi-plus-lg"></i>
                Добавить значение
            </button>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Поиск по справочникам</label>
                    <input type="text"
                           id="directoriesSearchInput"
                           class="form-control"
                           placeholder="Название, код или описание">
                </div>

                <div class="col-md-3">
                    <button class="btn btn-outline-info w-100 mt-md-4" id="searchDirectoriesBtn">
                        Найти
                    </button>
                </div>

                <div class="col-md-3">
                    <button class="btn btn-outline-light w-100 mt-md-4" id="resetDirectoriesBtn">
                        Сбросить
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">Список справочников</div>
                    <div id="directoriesList" class="d-flex flex-column gap-2">
                        <div class="text-secondary py-4 text-center">
                            Загрузка...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-semibold" id="selectedDirectoryTitle">Выберите справочник</div>
                            <div class="text-secondary small" id="selectedDirectoryDescription"></div>
                        </div>

                        <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                            <button type="button" class="btn btn-outline-light btn-sm" id="printDirectoryBtn" disabled>
                                <i class="bi bi-printer"></i>
                                Печать
                            </button>

                            <button type="button" class="btn btn-outline-warning btn-sm" id="printDirectoryBarcodesBtn" disabled>
                                <i class="bi bi-upc-scan"></i>
                                Штрихкоды
                            </button>

                            <button type="button" class="btn btn-outline-warning btn-sm" id="toggleDirectoryFavoriteBtn" disabled>
                                <i class="bi bi-star"></i>
                                Избранное
                            </button>

                            <button type="button" class="btn btn-outline-info btn-sm" id="exportDirectoryCsvBtn" disabled>
                                CSV
                            </button>

                            <button type="button" class="btn btn-outline-info btn-sm" id="exportDirectoryXmlBtn" disabled>
                                XML
                            </button>

                            @if(in_array(session('user_role'), ['foreman', 'admin']))
                                <button type="button" class="btn btn-outline-success btn-sm" id="importDirectoryBtn" disabled>
                                    <i class="bi bi-upload"></i>
                                    Импорт
                                </button>
                            @endif

                            <input type="text"
                                   id="directoryValuesSearchInput"
                                   class="form-control"
                                   style="min-width: 220px;"
                                   placeholder="Поиск по значениям">
                        </div>
                    </div>

                    <div class="card bg-dark border-secondary mb-3 d-none" id="directoryValueFiltersCard">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <div class="fw-semibold small">Фильтры по полям</div>
                                <button type="button" class="btn btn-sm btn-outline-light" id="resetDirectoryValueFiltersBtn">
                                    Сбросить фильтры
                                </button>
                            </div>
                            <div class="row g-2" id="directoryValueFilters"></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                            <tr id="directoryValuesHead">
                                <th>Значение</th>
                                <th>Код</th>
                                <th>Сортировка</th>
                                <th>Создано</th>
                                @if(in_array(session('user_role'), ['foreman', 'admin']))
                                    <th class="text-end">Действия</th>
                                @endif
                            </tr>
                            </thead>

                            <tbody id="directoryValuesBody">
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-5">
                                    Выберите справочник
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                        <div class="text-secondary small" id="directoryValuesPaginationInfo"></div>
                        <ul class="pagination mb-0" id="directoryValuesPaginationLinks"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(in_array(session('user_role'), ['foreman', 'admin']))
        <div class="modal fade" id="directoryValueModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" id="directoryValueForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="directoryValueModalTitle">Добавить значение</h5>

                        <button type="button"
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="directoryValueId">
                        <div class="mb-3">
                            <label class="form-label">Код</label>
                            <input type="text" class="form-control" id="directoryValueCode">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Порядок сортировки</label>
                            <input type="number" class="form-control" id="directoryValueSortOrder" min="0" value="0">
                        </div>

                        <div id="directoryValueDynamicFields"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-outline-light"
                                data-bs-dismiss="modal">
                            Отмена
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <span id="directoryValueSubmitText">Сохранить</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="directoryImportModal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" id="directoryImportForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title">Импорт значений справочника</h5>

                        <button type="button"
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Формат файла</label>
                            <select class="form-select" id="directoryImportFormat">
                                <option value="csv">CSV</option>
                                <option value="xml">XML</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Файл</label>
                            <input type="file"
                                   class="form-control"
                                   id="directoryImportFile"
                                   name="import_file"
                                   accept=".csv,.txt,.xml">
                        </div>

                        <div class="small text-secondary">
                            Для CSV первая строка должна содержать заголовки: value, code, sort_order, is_active и ключи полей шаблона.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-outline-light"
                                data-bs-dismiss="modal">
                            Отмена
                        </button>

                        <button type="submit" class="btn btn-primary">
                            Загрузить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        const canManageDirectoryValues = @json(in_array(session('user_role'), ['foreman', 'admin']));
        const canDeleteDirectoryValues = @json(session('user_role') === 'admin');
        let directoryValueModal = canManageDirectoryValues
            ? new bootstrap.Modal(document.getElementById('directoryValueModal'))
            : null;
        let directoryImportModal = canManageDirectoryValues
            ? new bootstrap.Modal(document.getElementById('directoryImportModal'))
            : null;
        let directories = @json($directories);
        let selectedDirectory = null;
        let currentDirectoryValues = [];
        let directoryValuesCache = {};
        let currentValuesPage = 1;
        let directoryValueModalDirectory = null;
        let directoryValueModalStack = [];

        function formatDateTime(value) {
            if (!value) {
                return '—';
            }

            let date = new Date(value.replace(' ', 'T'));

            if (isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString('ru-RU');
        }

        function findDirectoryDefinition(directoryId) {
            return directories.find(function (directory) {
                return String(directory.id) === String(directoryId || '');
            }) || null;
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

        function renderDirectoryFieldOptions(field, selectedValue) {
            let html = '<option value="">Выберите значение</option>';
            let values = directoryValuesCache[field.directory_id] || [];

            values.forEach(function (item) {
                let selected = String(selectedValue || '') === String(item.id) ? 'selected' : '';
                html += `<option value="${item.id}" ${selected}>${escapeHtml(getDirectoryOptionLabel(field, item))}</option>`;
            });

            return html;
        }

        function loadDirectoryValuesForField(field, selectedValue) {
            if (!field.directory_id || directoryValuesCache[field.directory_id]) {
                return;
            }

            $.ajax({
                url: `/directories/${field.directory_id}/values`,
                method: 'GET',
                data: { all: 1 },
                success: function (response) {
                    directoryValuesCache[field.directory_id] = response.items || [];
                    $(`.directory-value-field[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue));
                    $(`.directory-value-filter[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue));
                }
            });
        }

        function upsertDirectoryValueCache(directoryId, item) {
            if (!directoryId || !item) {
                return;
            }

            if (!directoryValuesCache[directoryId]) {
                directoryValuesCache[directoryId] = [];
            }

            let index = directoryValuesCache[directoryId].findIndex(function (existing) {
                return String(existing.id) === String(item.id);
            });

            if (index === -1) {
                directoryValuesCache[directoryId].push(item);
            } else {
                directoryValuesCache[directoryId][index] = item;
            }
        }

        function getDirectoryValueModalDirectory() {
            return directoryValueModalDirectory || selectedDirectory;
        }

        function renderDirectories(items) {
            if (!items || items.length === 0) {
                $('#directoriesList').html(`
                    <div class="text-secondary py-4 text-center">
                        Справочники не найдены
                    </div>
                `);

                selectedDirectory = null;
                updateSelectedDirectoryInfo();
                renderValuesTableHead([]);
                renderValues([]);
                toggleAddButton();
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let activeClass = selectedDirectory && String(selectedDirectory.id) === String(item.id)
                    ? 'border-info'
                    : '';

                html += `
                    <button type="button"
                            class="btn btn-outline-light text-start directory-select-btn ${activeClass}"
                            data-id="${item.id}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-semibold">${escapeHtml(item.name)}</div>
                            <span class="text-warning">
                                <i class="bi ${item.is_favorite ? 'bi-star-fill' : 'bi-star'}"></i>
                            </span>
                        </div>
                        <div class="small text-secondary">
                            ${item.description ? escapeHtml(item.description) : 'Без описания'}
                        </div>
                    </button>
                `;
            });

            $('#directoriesList').html(html);
        }

        function updateSelectedDirectoryInfo() {
            if (!selectedDirectory) {
                $('#selectedDirectoryTitle').text('Выберите справочник');
                $('#selectedDirectoryDescription').text('');
                $('#toggleDirectoryFavoriteBtn').prop('disabled', true).html('<i class="bi bi-star"></i> Избранное');
                return;
            }

            $('#selectedDirectoryTitle').text(selectedDirectory.name);
            $('#selectedDirectoryDescription').text(selectedDirectory.description || '');
            $('#toggleDirectoryFavoriteBtn')
                .prop('disabled', false)
                .html(`<i class="bi ${selectedDirectory.is_favorite ? 'bi-star-fill' : 'bi-star'}"></i> ${selectedDirectory.is_favorite ? 'Убрать' : 'Избранное'}`);
        }

        function renderDirectoryValueFilters(schema) {
            if (!schema || !schema.length) {
                $('#directoryValueFiltersCard').addClass('d-none');
                $('#directoryValueFilters').html('');
                return;
            }

            let html = '';

            schema.forEach(function (field) {
                let key = escapeHtml(field.key || '');
                let label = escapeHtml(field.label || field.key || '');
                html += `<div class="col-md-4">`;
                html += `<label class="form-label small mb-1">${label}</label>`;

                if (field.type === 'list') {
                    html += `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
                    html += `<option value="">Все</option>`;
                    (field.options || []).forEach(function (option) {
                        html += `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`;
                    });
                    html += `</select>`;
                } else if (field.type === 'directory') {
                    html += `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
                    html += renderDirectoryFieldOptions(field, '');
                    html += `</select>`;
                    loadDirectoryValuesForField(field, '');
                } else if (field.type === 'date') {
                    html += `<input type="date" class="form-control form-control-sm directory-value-filter" data-key="${key}">`;
                } else if (field.type === 'time') {
                    html += `<input type="time" class="form-control form-control-sm directory-value-filter" data-key="${key}">`;
                } else if (field.type === 'number' || field.type === 'calc') {
                    html += `<input type="number" step="any" class="form-control form-control-sm directory-value-filter" data-key="${key}" placeholder="Равно">`;
                } else {
                    html += `<input type="text" class="form-control form-control-sm directory-value-filter" data-key="${key}" placeholder="Содержит">`;
                }

                html += `</div>`;
            });

            $('#directoryValueFilters').html(html);
            $('#directoryValueFiltersCard').removeClass('d-none');
            initSearchableSelects(document.getElementById('directoryValueFilters'));
        }

        function collectDirectoryValueFilters() {
            let filters = {};

            $('.directory-value-filter').each(function () {
                let key = $(this).data('key');
                let value = $(this).val();

                if (key && value !== null && String(value).trim() !== '') {
                    filters[key] = String(value).trim();
                }
            });

            return filters;
        }

        function buildDirectoryExportUrl(format) {
            if (!selectedDirectory) {
                return null;
            }

            let query = $.param({
                search: $('#directoryValuesSearchInput').val() || '',
                filters: collectDirectoryValueFilters()
            });

            return `/directories/${selectedDirectory.id}/export/${format}?${query}`;
        }

        function renderValuesTableHead(schema) {
            let html = '';

            if (schema && schema.length) {
                schema.forEach(function (field) {
                    html += `<th>${escapeHtml(field.label)}</th>`;
                });
            } else {
                html += '<th>Значение</th>';
            }

            html += '<th>Код</th>';
            html += '<th>Сортировка</th>';
            html += '<th>Создано</th>';

            if (canManageDirectoryValues) {
                html += '<th class="text-end">Действия</th>';
            }

            $('#directoryValuesHead').html(html);
        }

        function renderValues(items) {
            if (!selectedDirectory) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">
                            Выберите справочник
                        </td>
                    </tr>
                `);
                return;
            }

            let schema = selectedDirectory.schema || [];

            if (!items || items.length === 0) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="${schema.length ? schema.length + (canManageDirectoryValues ? 4 : 3) : (canManageDirectoryValues ? 5 : 4)}" class="text-center text-secondary py-5">
                            Значения не найдены
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                html += '<tr>';

                if (schema.length) {
                    schema.forEach(function (field) {
                        let value = item.data && item.data[field.key] !== undefined ? item.data[field.key] : '';

                        if (field.type === 'directory' && value !== null && value !== '') {
                            let values = directoryValuesCache[field.directory_id] || [];
                            let directoryItem = values.find(function (entry) {
                                return String(entry.id) === String(value);
                            });

                            if (directoryItem) {
                                value = getDirectoryOptionLabel(field, directoryItem);
                            }
                        }
                        html += `<td>${value === null || value === '' ? '<span class="text-secondary">—</span>' : escapeHtml(String(value))}</td>`;
                    });
                } else {
                    html += `<td>${escapeHtml(item.value || '')}</td>`;
                }

                html += `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">—</span>'}</td>`;
                html += `<td>${escapeHtml(String(item.sort_order ?? 0))}</td>`;
                html += `<td>${escapeHtml(formatDateTime(item.created_at))}</td>`;

                if (canManageDirectoryValues) {
                    html += `<td class="text-end">`;
                    html += `<button class="btn btn-sm btn-outline-info edit-directory-value" data-id="${item.id}"><i class="bi bi-pencil"></i></button>`;

                    if (canDeleteDirectoryValues) {
                        html += ` <button class="btn btn-sm btn-outline-danger delete-directory-value" data-id="${item.id}"><i class="bi bi-trash"></i></button>`;
                    }

                    html += `</td>`;
                }

                html += '</tr>';
            });

            $('#directoryValuesBody').html(html);
        }

        function toggleAddButton() {
            $('#printDirectoryBtn, #printDirectoryBarcodesBtn, #exportDirectoryCsvBtn, #exportDirectoryXmlBtn').prop('disabled', !selectedDirectory);

            if (canManageDirectoryValues) {
                $('#addDirectoryValueBtn, #importDirectoryBtn').prop('disabled', !selectedDirectory);
            }
        }

        function loadDirectories() {
            $('#directoriesList').html(`
                <div class="text-secondary py-4 text-center">
                    Загрузка...
                </div>
            `);

            $.ajax({
                url: "{{ route('user.directories.list') }}",
                method: "GET",
                data: {
                    search: $('#directoriesSearchInput').val()
                },
                success: function (response) {
                    directories = response.items || [];

                    if (selectedDirectory) {
                        selectedDirectory = directories.find(function (item) {
                            return String(item.id) === String(selectedDirectory.id);
                        }) || null;
                    }

                    if (!selectedDirectory && directories.length) {
                        selectedDirectory = directories[0];
                    }

                    renderDirectories(directories);
                    updateSelectedDirectoryInfo();
                    toggleAddButton();

                    if (selectedDirectory) {
                        renderValuesTableHead(selectedDirectory.schema || []);
                        renderDirectoryValueFilters(selectedDirectory.schema || []);
                        loadValues();
                    } else {
                        renderValuesTableHead([]);
                        renderDirectoryValueFilters([]);
                        renderValues([]);
                    }
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderValuesPagination(pagination) {
            if (!pagination) {
                $('#directoryValuesPaginationInfo').text('');
                $('#directoryValuesPaginationLinks').html('');
                return;
            }

            let info = pagination.total > 0
                ? `Показано ${pagination.from}-${pagination.to} из ${pagination.total}`
                : 'Нет записей';
            let html = '';

            for (let page = 1; page <= pagination.last_page; page++) {
                let active = page === pagination.current_page ? 'active' : '';
                html += `
                    <li class="page-item ${active}">
                        <button type="button" class="page-link directory-values-page" data-page="${page}">${page}</button>
                    </li>
                `;
            }

            $('#directoryValuesPaginationInfo').text(info);
            $('#directoryValuesPaginationLinks').html(html);
        }

        function loadValues(page = 1) {
            if (!selectedDirectory) {
                return;
            }

            currentValuesPage = page;

            $('#directoryValuesBody').html(`
                <tr>
                    <td colspan="${(selectedDirectory.schema || []).length ? (selectedDirectory.schema || []).length + 3 : 4}" class="text-center text-secondary py-5">
                        Загрузка...
                    </td>
                </tr>
            `);

            $.ajax({
                url: `/directories/${selectedDirectory.id}/values`,
                method: "GET",
                data: {
                    page: page,
                    search: $('#directoryValuesSearchInput').val(),
                    filters: collectDirectoryValueFilters()
                },
                success: function (response) {
                    selectedDirectory = response.directory;
                    currentDirectoryValues = response.items || [];
                    directoryValuesCache[selectedDirectory.id] = currentDirectoryValues;
                    renderDirectories(directories);
                    updateSelectedDirectoryInfo();
                    renderValuesTableHead(selectedDirectory.schema || []);
                    renderValues(currentDirectoryValues);
                    renderValuesPagination(response.pagination);
                    toggleAddButton();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function groupSchemaFieldsByTab(schema) {
            let tabs = [];
            let indexes = {};

            schema.forEach(function (field) {
                let tabName = (field.tab || '').trim() || 'Основное';

                if (indexes[tabName] === undefined) {
                    indexes[tabName] = tabs.length;
                    tabs.push({
                        name: tabName,
                        fields: []
                    });
                }

                tabs[indexes[tabName]].fields.push(field);
            });

            return tabs;
        }

        function generateQrClientValue() {
            return 'QR-' + new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14)
                + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        }

        function renderDirectoryValueFieldControl(field, value = '') {
            let required = field.required ? 'required' : '';
            let requiredMark = field.required ? ' <span class="text-danger">*</span>' : '';
            let html = `<div class="mb-3">`;

            html += `<label class="form-label">${escapeHtml(field.label)}${requiredMark}</label>`;

            if (field.type === 'number') {
                html += `<input type="number" step="any" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            } else if (field.type === 'date') {
                html += `<input type="date" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            } else if (field.type === 'time') {
                html += `<input type="time" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(String(value || '').substring(0, 5))}" ${required}>`;
            } else if (field.type === 'calc') {
                html += `<input type="number" step="any" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" readonly>`;
            } else if (field.type === 'directory') {
                html += `<div class="input-group">`;
                html += `<select class="form-select directory-value-field" data-key="${field.key}" ${required}>`;
                html += renderDirectoryFieldOptions(field, value);
                html += `</select>`;
                html += `<button type="button" class="btn btn-outline-success open-nested-directory-value-modal" data-directory-id="${escapeHtml(field.directory_id || '')}" data-field-key="${escapeHtml(field.key)}" title="Добавить значение">+</button>`;
                html += `</div>`;
                loadDirectoryValuesForField(field, value);
            } else if (field.type === 'list') {
                html += `<select class="form-select directory-value-field" data-key="${field.key}" ${required}>`;
                html += `<option value="">Выберите значение</option>`;

                (field.options || []).forEach(function (option) {
                    let selected = String(value) === String(option) ? 'selected' : '';
                    html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                });

                html += `</select>`;
            } else if (field.type === 'qr') {
                let qrRequired = field.auto_generate ? '' : required;
                let placeholder = field.auto_generate ? 'Оставьте пустым для автогенерации' : 'Введите QR/штрихкод';
                html += `
                    <div class="input-group">
                        <input type="text" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" placeholder="${placeholder}" ${qrRequired}>
                        <button type="button" class="btn btn-outline-info generate-directory-qr-value">Сгенерировать</button>
                    </div>
                `;
            } else {
                html += `<input type="text" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            }

            html += `</div>`;

            return html;
        }

        function renderDirectoryValueForm() {
            let modalDirectory = getDirectoryValueModalDirectory();

            if (!modalDirectory) {
                $('#directoryValueDynamicFields').html('');
                return;
            }

            let schema = modalDirectory.schema || [];

            if (!schema.length) {
                $('#directoryValueDynamicFields').html(`
                    <div class="mb-3">
                        <label class="form-label">Значение</label>
                        <input type="text" class="form-control directory-value-field" data-key="value">
                    </div>
                `);
                return;
            }

            let html = '';
            let tabs = groupSchemaFieldsByTab(schema);

            if (tabs.length > 1 || tabs[0].name !== 'Основное') {
                html += '<ul class="nav nav-tabs mb-3" role="tablist">';
                tabs.forEach(function (tab, index) {
                    let active = index === 0 ? 'active' : '';
                    html += `
                        <li class="nav-item" role="presentation">
                            <button class="nav-link ${active}" type="button" data-bs-toggle="tab" data-bs-target="#directory-value-tab-${index}" role="tab">
                                ${escapeHtml(tab.name)}
                            </button>
                        </li>
                    `;
                });
                html += '</ul>';
                html += '<div class="tab-content">';

                tabs.forEach(function (tab, index) {
                    let active = index === 0 ? 'show active' : '';
                    html += `<div class="tab-pane fade ${active}" id="directory-value-tab-${index}" role="tabpanel">`;
                    tab.fields.forEach(function (field) {
                        html += renderDirectoryValueFieldControl(field);
                    });
                    html += '</div>';
                });

                html += '</div>';
            } else {
                schema.forEach(function (field) {
                    html += renderDirectoryValueFieldControl(field);
                });
            }

            $('#directoryValueDynamicFields').html(html);
            initSearchableSelects(document.getElementById('directoryValueDynamicFields'));
        }

        function fillDirectoryValueForm(item = null) {
            let modalDirectory = getDirectoryValueModalDirectory();
            $('#directoryValueId').val(item ? item.id : '');
            $('#directoryValueCode').val(item ? (item.code || '') : '');
            $('#directoryValueSortOrder').val(item ? (item.sort_order ?? 0) : 0);

            renderDirectoryValueForm();

            if (!item) {
                return;
            }

            let schema = modalDirectory ? (modalDirectory.schema || []) : [];

            if (!schema.length) {
                $('.directory-value-field[data-key="value"]').val(item.value || '');
                return;
            }

            schema.forEach(function (field) {
                let value = item.data && item.data[field.key] !== undefined && item.data[field.key] !== null
                    ? item.data[field.key]
                    : '';

                if (field.type === 'time' && value) {
                    value = String(value).substring(0, 5);
                }

                if (field.type === 'directory') {
                    loadDirectoryValuesForField(field, value);
                }

                $(`.directory-value-field[data-key="${field.key}"]`).val(value);
            });
        }

        function collectDirectoryValuePayload() {
            let modalDirectory = getDirectoryValueModalDirectory();
            let payload = {
                code: $('#directoryValueCode').val(),
                sort_order: $('#directoryValueSortOrder').val() || 0
            };

            let schema = modalDirectory ? (modalDirectory.schema || []) : [];

            if (!schema.length) {
                payload.value = $('.directory-value-field[data-key="value"]').val() || '';
                return payload;
            }

            payload.data = {};

            $('.directory-value-field').each(function () {
                payload.data[$(this).data('key')] = $(this).val();
            });

            return payload;
        }

        function snapshotDirectoryValueModalState() {
            return {
                directory: getDirectoryValueModalDirectory(),
                valueId: $('#directoryValueId').val(),
                title: $('#directoryValueModalTitle').text(),
                submitText: $('#directoryValueSubmitText').text(),
                payload: collectDirectoryValuePayload()
            };
        }

        function restoreDirectoryValueModalState(snapshot, selectedValues = {}) {
            if (!snapshot || !snapshot.directory) {
                return;
            }

            directoryValueModalDirectory = snapshot.directory;
            $('#directoryValueModalTitle').text(snapshot.title || `Добавить значение: ${snapshot.directory.name}`);
            $('#directoryValueSubmitText').text(snapshot.submitText || 'Сохранить');
            $('#directoryValueId').val(snapshot.valueId || '');
            $('#directoryValueCode').val(snapshot.payload?.code || '');
            $('#directoryValueSortOrder').val(snapshot.payload?.sort_order ?? 0);

            renderDirectoryValueForm();

            if (snapshot.payload?.data) {
                Object.keys(snapshot.payload.data).forEach(function (key) {
                    $(`.directory-value-field[data-key="${key}"]`).val(snapshot.payload.data[key]);
                });
            } else if (snapshot.payload?.value !== undefined) {
                $('.directory-value-field[data-key="value"]').val(snapshot.payload.value || '');
            }

            Object.keys(selectedValues).forEach(function (key) {
                $(`.directory-value-field[data-key="${key}"]`).val(selectedValues[key]).trigger('change');
            });

            initSearchableSelects(document.getElementById('directoryValueDynamicFields'));
        }

        function openNestedDirectoryValueModal(fieldKey, directoryId) {
            let directory = findDirectoryDefinition(directoryId);

            if (!directory) {
                showToast('Вложенный справочник не найден', 'warning');
                return;
            }

            directoryValueModalStack.push({
                returnFieldKey: fieldKey,
                snapshot: snapshotDirectoryValueModalState()
            });

            directoryValueModalDirectory = directory;
            $('#directoryValueForm')[0].reset();
            $('#directoryValueId').val('');
            $('#directoryValueCode').val('');
            $('#directoryValueSortOrder').val(0);
            $('#directoryValueModalTitle').text(`Добавить значение: ${directory.name}`);
            $('#directoryValueSubmitText').text('Сохранить и выбрать');
            renderDirectoryValueForm();
            directoryValueModal.show();
        }

        function unwindNestedDirectoryValueModal(createdValue = null) {
            if (!directoryValueModalStack.length) {
                return false;
            }

            let context = directoryValueModalStack.pop();
            let selectedValues = {};

            if (createdValue && context.returnFieldKey) {
                selectedValues[context.returnFieldKey] = createdValue.id;
            }

            restoreDirectoryValueModalState(context.snapshot, selectedValues);
            return true;
        }

        $(document).on('click', '.directory-select-btn', function () {
            let id = $(this).data('id');

            selectedDirectory = directories.find(function (item) {
                return String(item.id) === String(id);
            }) || null;

            renderDirectories(directories);
            updateSelectedDirectoryInfo();
            renderValuesTableHead(selectedDirectory ? (selectedDirectory.schema || []) : []);
            renderDirectoryValueFilters(selectedDirectory ? (selectedDirectory.schema || []) : []);
            toggleAddButton();
            loadValues(1);
        });

        $('#searchDirectoriesBtn').on('click', function () {
            loadDirectories();
        });

        $('#resetDirectoriesBtn').on('click', function () {
            $('#directoriesSearchInput').val('');
            loadDirectories();
        });

        $('#directoriesSearchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadDirectories();
            }
        });

        $('#directoryValuesSearchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadValues(1);
            }
        });

        $(document).on('change keyup', '.directory-value-filter', function (e) {
            if (e.type === 'change' || e.key === 'Enter') {
                loadValues(1);
            }
        });

        $('#resetDirectoryValueFiltersBtn').on('click', function () {
            $('.directory-value-filter').val('').trigger('change.select2');
            loadValues(1);
        });

        $(document).on('click', '.directory-values-page', function () {
            loadValues(Number($(this).data('page')) || 1);
        });

        $('#printDirectoryBtn').on('click', function () {
            if (!selectedDirectory) {
                return;
            }

            window.open(`/directories/${selectedDirectory.id}/print`, '_blank');
        });

        $('#printDirectoryBarcodesBtn').on('click', function () {
            if (!selectedDirectory) {
                return;
            }

            window.open(`/directories/${selectedDirectory.id}/barcodes`, '_blank');
        });

        $('#exportDirectoryCsvBtn').on('click', function () {
            let url = buildDirectoryExportUrl('csv');

            if (url) {
                window.open(url, '_blank');
            }
        });

        $('#exportDirectoryXmlBtn').on('click', function () {
            let url = buildDirectoryExportUrl('xml');

            if (url) {
                window.open(url, '_blank');
            }
        });

        if (canManageDirectoryValues) {
            $('#importDirectoryBtn').on('click', function () {
                if (!selectedDirectory) {
                    return;
                }

                $('#directoryImportForm')[0].reset();
                directoryImportModal.show();
            });

            $('#addDirectoryValueBtn').on('click', function () {
                if (!selectedDirectory) {
                    return;
                }

                directoryValueModalDirectory = selectedDirectory;
                directoryValueModalStack = [];
                $('#directoryValueModalTitle').text(`Добавить значение: ${selectedDirectory.name}`);
                $('#directoryValueSubmitText').text('Сохранить');
                $('#directoryValueForm')[0].reset();
                fillDirectoryValueForm();
                directoryValueModal.show();
            });

            $(document).on('click', '.edit-directory-value', function () {
                let id = $(this).data('id');
                let item = currentDirectoryValues.find(function (entry) {
                    return String(entry.id) === String(id);
                });

                if (!item || !selectedDirectory) {
                    return;
                }

                directoryValueModalDirectory = selectedDirectory;
                directoryValueModalStack = [];
                $('#directoryValueModalTitle').text(`Редактировать значение: ${selectedDirectory.name}`);
                $('#directoryValueSubmitText').text('Сохранить изменения');
                $('#directoryValueForm')[0].reset();
                fillDirectoryValueForm(item);
                directoryValueModal.show();
            });

            $(document).on('click', '.open-nested-directory-value-modal', function () {
                openNestedDirectoryValueModal($(this).data('field-key'), $(this).data('directory-id'));
            });

            $(document).on('click', '#directoryValueModal [data-bs-dismiss="modal"]', function (e) {
                if (!directoryValueModalStack.length) {
                    return;
                }

                e.preventDefault();
                unwindNestedDirectoryValueModal();
            });

            $('#directoryValueModal').on('hidden.bs.modal', function () {
                directoryValueModalDirectory = null;
                directoryValueModalStack = [];
            });

            $('#directoryValueForm').on('submit', function (e) {
                e.preventDefault();

                let modalDirectory = getDirectoryValueModalDirectory();

                if (!modalDirectory) {
                    return;
                }

                let valueId = $('#directoryValueId').val();

                $.ajax({
                    url: valueId
                        ? `/directory-values/${valueId}`
                        : `/directories/${modalDirectory.id}/values`,
                    method: "POST",
                    data: collectDirectoryValuePayload(),
                    success: function (response) {
                        upsertDirectoryValueCache(modalDirectory.id, response.value || null);
                        showToast(response.message, 'success');

                        if (unwindNestedDirectoryValueModal(response.value || null)) {
                            return;
                        }

                        directoryValueModal.hide();
                        directoryValueModalDirectory = null;
                        loadValues();
                    },
                    error: function (xhr) {
                        showAjaxErrors(xhr);
                    }
                });
            });

            $('#directoryImportForm').on('submit', function (e) {
                e.preventDefault();

                if (!selectedDirectory) {
                    return;
                }

                let fileInput = document.getElementById('directoryImportFile');
                let format = $('#directoryImportFormat').val() || 'csv';

                if (!fileInput.files.length) {
                    showToast('Выберите файл для импорта', 'danger');
                    return;
                }

                let formData = new FormData();
                formData.append('import_file', fileInput.files[0]);

                $.ajax({
                    url: `/directories/${selectedDirectory.id}/import/${format}`,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        showToast(response.message, 'success');
                        directoryImportModal.hide();
                        loadValues(1);
                    },
                    error: function (xhr) {
                        showAjaxErrors(xhr);
                    }
                });
            });
        }

        if (canDeleteDirectoryValues) {
            $(document).on('click', '.delete-directory-value', function () {
                let id = $(this).data('id');

                if (!confirm('Удалить значение справочника?')) {
                    return;
                }

                $.ajax({
                    url: `/directory-values/${id}`,
                    method: "DELETE",
                    success: function (response) {
                        showToast(response.message, 'success');
                        loadValues();
                    },
                    error: function (xhr) {
                        showAjaxErrors(xhr);
                    }
                });
            });
        }

        $(document).on('click', '.generate-directory-qr-value', function () {
            $(this).closest('.input-group').find('.directory-value-field').val(generateQrClientValue()).trigger('input');
        });

        $('#toggleDirectoryFavoriteBtn').on('click', function () {
            if (!selectedDirectory) {
                return;
            }

            $.ajax({
                url: "{{ route('user.favorites.toggle') }}",
                method: 'POST',
                data: {
                    entity_type: 'directory',
                    entity_id: selectedDirectory.id
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    loadDirectories();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        loadDirectories();
    </script>
@endpush
