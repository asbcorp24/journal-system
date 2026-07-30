@extends($directoryPageLayout ?? 'admin.layouts.app')

@section('title', $directoryPageTitle ?? 'Справочники')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">{{ $directoryPageTitle ?? 'Справочники' }}</h2>
            <div class="text-secondary">
                Шаблоны полей и записи справочников в JSON
            </div>
        </div>

        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-magic"></i>
                    Мастер создания
                </button>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><button class="dropdown-item directory-preset-btn" type="button" data-preset="warehouse_nomenclature">Создать складской справочник номенклатуры</button></li>
                    <li><button class="dropdown-item directory-preset-btn" type="button" data-preset="warehouse_sources">Создать справочник источников поступления</button></li>
                    <li><button class="dropdown-item directory-preset-btn" type="button" data-preset="warehouse_workshops">Создать справочник цехов выдачи</button></li>
                </ul>
            </div>
            <button class="btn btn-outline-light" id="importDirectoryTemplateBtn">
                <i class="bi bi-upload"></i>
                Импорт
            </button>
            <button class="btn btn-primary" id="addDirectoryBtn">
                <i class="bi bi-plus-lg"></i>
                Создать справочник
            </button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Список справочников</h5>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <input type="text"
                                   id="directorySearchInput"
                                   class="form-control"
                                   placeholder="Поиск справочника">
                        </div>

                        <div class="col-md-4">
                            <button class="btn btn-outline-info w-100" id="directorySearchBtn">
                                Найти
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Справочник</th>
                                <th>Записей</th>
                                <th>Автор</th>
                                <th class="text-end">Действия</th>
                            </tr>
                            </thead>

                            <tbody id="directoriesTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-5">
                                    Загрузка...
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-secondary small" id="directoriesPaginationInfo"></div>
                        <ul class="pagination mb-0" id="directoriesPaginationLinks"></ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <div id="emptyValuesBlock">
                        <div class="text-center text-secondary py-5">
                            <i class="bi bi-card-list" style="font-size: 48px;"></i>
                            <div class="mt-3">
                                Выберите справочник слева, чтобы управлять его записями
                            </div>
                        </div>
                    </div>

                    <div id="valuesBlock" class="d-none">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold mb-1" id="selectedDirectoryName">Справочник</h5>
                                <div class="text-secondary small" id="selectedDirectoryDescription"></div>
                                <div class="text-secondary small mt-1" id="selectedDirectorySchemaSummary"></div>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-light btn-sm" id="printDirectoryBtn">
                                    <i class="bi bi-printer"></i>
                                    Печать
                                </button>

                                <button class="btn btn-outline-warning btn-sm" id="printBarcodesBtn">
                                    <i class="bi bi-upc-scan"></i>
                                    Штрихкоды
                                </button>

                                <button class="btn btn-outline-success btn-sm" id="importCsvBtn">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                    CSV
                                </button>

                                <button class="btn btn-primary btn-sm" id="addValueBtn">
                                    <i class="bi bi-plus-lg"></i>
                                    Запись
                                </button>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-9">
                                <input type="text"
                                       id="valueSearchInput"
                                       class="form-control"
                                       placeholder="Поиск записи">
                            </div>

                            <div class="col-md-3">
                                <button class="btn btn-outline-info w-100" id="valueSearchBtn">
                                    Найти
                                </button>
                            </div>
                        </div>

                        <div class="card bg-dark border-secondary mb-3 d-none" id="valueFiltersCard">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <div class="fw-semibold small">Фильтры по полям</div>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="resetValueFiltersBtn">
                                        Сбросить фильтры
                                    </button>
                                </div>
                                <div class="row g-2" id="valueFilters"></div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Запись</th>
                                    <th>Код</th>
                                    <th>Сорт.</th>
                                    <th>Статус</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                                </thead>

                                <tbody id="valuesTableBody"></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-secondary small" id="valuesPaginationInfo"></div>
                            <ul class="pagination mb-0" id="valuesPaginationLinks"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="directoryModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="directoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="directoryModalTitle">Создать справочник</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="directoryId" name="id">

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label">Название справочника</label>
                            <input type="text" name="name" id="directoryName" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Код</label>
                            <input type="text" name="code" id="directoryCode" class="form-control">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Описание</label>
                            <textarea name="description" id="directoryDescription" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Доступен подразделениям</label>
                            <select name="division_ids[]" id="directoryDivisions" class="form-select" multiple size="5">
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}">{{ $division->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Шаблон записи</h5>
                            <div class="text-secondary small">
                                Поля записи справочника. Ключи должны быть на латинице.
                            </div>
                        </div>

                        <button type="button" class="btn btn-success btn-sm" id="addSchemaFieldBtn">
                            <i class="bi bi-plus-lg"></i>
                            Добавить поле
                        </button>
                    </div>

                    <div id="schemaBuilder"></div>

                    <div class="alert alert-info mt-3">
                        Если шаблон пустой, справочник работает в старом режиме с одним полем "Значение".
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="valueModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" id="valueForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="valueModalTitle">Добавить запись</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="valueId" name="id">
                    <div id="valueDynamicFields"></div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Код</label>
                            <input type="text" name="code" id="valueCode" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Сортировка</label>
                            <input type="number" name="sort_order" id="valueSortOrder" class="form-control" value="0" min="0">
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="valueIsActive" value="1" checked>
                                <label class="form-check-label" for="valueIsActive">Активно</label>
                            </div>
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

    <div class="modal fade" id="csvModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="csvForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info" id="csvHelpBlock"></div>

                    <div class="mb-3">
                        <label class="form-label">CSV-файл</label>
                        <input type="file" name="csv_file" id="csvFile" class="form-control" accept=".csv,.txt">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Разделитель</label>
                        <select name="delimiter" id="csvDelimiter" class="form-select">
                            <option value=";">Точка с запятой ;</option>
                            <option value=",">Запятая ,</option>
                            <option value="	">Табуляция</option>
                        </select>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="has_header" id="csvHasHeader" value="1" checked>
                        <label class="form-check-label" for="csvHasHeader">Первая строка содержит заголовки</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-success">Импортировать</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="importDirectoryTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="importDirectoryTemplateForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт шаблона справочника</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label">JSON-файл шаблона</label>
                    <input type="file"
                           class="form-control"
                           name="template_file"
                           accept=".json,.txt"
                           required>
                    <div class="text-secondary small mt-2">
                        Будет создан новый справочник-копия. Подразделения и связанные справочники подтянутся по названию или коду.
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
        let directoryModal = new bootstrap.Modal(document.getElementById('directoryModal'));
        let importDirectoryTemplateModal = new bootstrap.Modal(document.getElementById('importDirectoryTemplateModal'));
        let valueModal = new bootstrap.Modal(document.getElementById('valueModal'));
        let csvModal = new bootstrap.Modal(document.getElementById('csvModal'));

        let currentDirectoryPage = 1;
        let currentValuePage = 1;
        let selectedDirectoryId = null;
        let selectedDirectoryData = null;
        let valueModalDirectory = null;
        let valueModalStack = [];
        let schemaFields = [];
        let schemaFieldIndex = 0;
        const referenceDirectories = @json($referenceDirectories);
        const directoryRoutes = @json($directoryRoutes);
        const directoryCanModifyFilled = @json($directoryCanModifyFilled ?? true);
        const directoryWizardPresets = {
            warehouse_nomenclature: {
                title: 'Складской справочник: номенклатура',
                name: 'Номенклатура склада',
                code: 'warehouse_nomenclature',
                description: 'Номенклатура с номером, названием, параметрами и единицей измерения',
                schema: [
                    { label: 'Наименование', key: 'name', type: 'text', tab: 'Основное', required: true, unique: false },
                    { label: 'Номенклатурный номер', key: 'item_number', type: 'text', tab: 'Основное', required: true, unique: true },
                    { label: 'Параметры', key: 'parameters', type: 'text', tab: 'Основное', required: false, unique: false },
                    { label: 'Единица измерения', key: 'unit', type: 'text', tab: 'Основное', required: true, unique: false }
                ]
            },
            warehouse_sources: {
                title: 'Складской справочник: источники поступления',
                name: 'Источники поступления',
                code: 'warehouse_sources',
                description: 'Поставщики, возвраты и внутренние источники поступления',
                schema: [
                    { label: 'Наименование', key: 'name', type: 'text', tab: 'Основное', required: true, unique: false },
                    { label: 'Номер источника', key: 'source_number', type: 'text', tab: 'Основное', required: true, unique: true },
                    { label: 'Комментарий', key: 'comment', type: 'text', tab: 'Основное', required: false, unique: false }
                ]
            },
            warehouse_workshops: {
                title: 'Складской справочник: цеха выдачи',
                name: 'Цеха для выдачи',
                code: 'warehouse_workshops',
                description: 'Цеха и участки, куда выдаётся номенклатура со склада',
                schema: [
                    { label: 'Наименование цеха', key: 'name', type: 'text', tab: 'Основное', required: true, unique: false },
                    { label: 'Номер цеха', key: 'shop_number', type: 'text', tab: 'Основное', required: true, unique: true }
                ]
            }
        };
        let directoryValuesCache = {};

        function directoryRoute(name, id = null) {
            let url = directoryRoutes[name] || '';

            if (id !== null) {
                url = url.replace('__ID__', encodeURIComponent(id));
            }

            return url;
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }

            return $('<div>').text(text).html();
        }

        function renderBadgeActive(isActive) {
            return isActive
                ? '<span class="badge bg-success">Активно</span>'
                : '<span class="badge bg-danger">Отключено</span>';
        }

        function schemaSummary(schema) {
            if (!schema || !schema.length) {
                return 'Одинарное значение без шаблона';
            }

            return schema.map(function (field) {
                return `${field.label} (${field.type})`;
            }).join(', ');
        }

        function buildReferenceDirectoryOptions(selectedId) {
            let html = '<option value="">Выберите справочник</option>';

            referenceDirectories.forEach(function (directory) {
                let selected = String(selectedId || '') === String(directory.id) ? 'selected' : '';
                html += `<option value="${directory.id}" ${selected}>${escapeHtml(directory.name)}</option>`;
            });

            return html;
        }

        function buildReferenceDirectoryDisplayOptions(directoryId, selectedKey) {
            let html = '<option value="">Первое заполненное поле</option>';
            let directory = referenceDirectories.find(function (item) {
                return String(item.id) === String(directoryId || '');
            });

            if (!directory || !Array.isArray(directory.schema)) {
                return html;
            }

            directory.schema.forEach(function (field) {
                let selected = String(selectedKey || '') === String(field.key) ? 'selected' : '';
                html += `<option value="${escapeHtml(field.key)}" ${selected}>${escapeHtml(field.label)} (${escapeHtml(field.key)})</option>`;
            });

            return html;
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
                url: directoryRoute('directoryValues', field.directory_id),
                method: 'GET',
                data: { all: 1 },
                success: function (response) {
                    directoryValuesCache[field.directory_id] = response.items || [];
                    $(`.value-data-field[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue));
                    $(`.value-filter-field[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue));
                }
            });
        }

        function upsertValueCache(directoryId, item) {
            if (!item) {
                return;
            }

            if (!directoryValuesCache[directoryId]) {
                directoryValuesCache[directoryId] = [];
            }

            let index = directoryValuesCache[directoryId].findIndex(function (existingItem) {
                return String(existingItem.id) === String(item.id);
            });

            if (index === -1) {
                directoryValuesCache[directoryId].push(item);
            } else {
                directoryValuesCache[directoryId][index] = item;
            }
        }

        function getValueModalDirectory() {
            return valueModalDirectory || selectedDirectoryData;
        }

        function renderPagination(target, pagination, type) {
            if (!pagination || pagination.last_page <= 1) {
                $(target).html('');
                return;
            }

            let current = pagination.current_page;
            let last = pagination.last_page;
            let links = '';

            links += `<li class="page-item ${current === 1 ? 'disabled' : ''}"><a class="page-link ${type}-page" href="#" data-page="${current - 1}">Назад</a></li>`;

            let start = Math.max(1, current - 2);
            let end = Math.min(last, current + 2);

            for (let i = start; i <= end; i++) {
                links += `<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link ${type}-page" href="#" data-page="${i}">${i}</a></li>`;
            }

            links += `<li class="page-item ${current === last ? 'disabled' : ''}"><a class="page-link ${type}-page" href="#" data-page="${current + 1}">Вперёд</a></li>`;

            $(target).html(links);
        }

        function loadDirectories(page = 1) {
            currentDirectoryPage = page;

            $('#directoriesTableBody').html(`
                <tr>
                    <td colspan="4" class="text-center text-secondary py-5">Загрузка...</td>
                </tr>
            `);

            $.ajax({
                url: directoryRoute('list'),
                method: 'GET',
                data: {
                    page: page,
                    search: $('#directorySearchInput').val()
                },
                success: function (response) {
                    renderDirectories(response.items);
                    renderDirectoriesPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderDirectories(items) {
            if (!items || !items.length) {
                $('#directoriesTableBody').html(`
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">Справочники не найдены</td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let activeClass = selectedDirectoryId === item.id ? 'table-active' : '';
                let fieldCount = item.schema ? item.schema.length : 0;
                let valuesCount = Number(item.values_count || 0);
                let filledDisabled = !directoryCanModifyFilled && valuesCount > 0
                    ? 'disabled title="Нельзя менять шаблон: справочник уже заполнен"'
                    : '';

                let authorName = item.creator && item.creator.name
                    ? escapeHtml(item.creator.name)
                    : '<span class="text-secondary">Суперадмин</span>';

                html += `
                    <tr class="${activeClass}">
                        <td>${item.id}</td>
                        <td>
                            <div class="fw-semibold">
                                <a href="#" class="text-decoration-none text-info select-directory" data-id="${item.id}">
                                    ${escapeHtml(item.name)}
                                </a>
                            </div>
                            <div class="text-secondary small">${item.code ? escapeHtml(item.code) : 'без кода'}</div>
                            <div class="text-secondary small">${fieldCount ? 'Полей: ' + fieldCount : 'Без шаблона'}</div>
                        </td>
                        <td><span class="badge bg-secondary">${item.values_count}</span></td>
                        <td>${authorName}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info edit-directory" data-id="${item.id}" ${filledDisabled}><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-success export-directory-template" data-id="${item.id}"><i class="bi bi-download"></i></button>
                            <button class="btn btn-sm btn-outline-danger delete-directory" data-id="${item.id}" ${filledDisabled}><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
            });

            $('#directoriesTableBody').html(html);
        }

        function renderDirectoriesPagination(pagination) {
            let info = pagination.total > 0
                ? `Показано ${pagination.from}-${pagination.to} из ${pagination.total}`
                : 'Нет записей';

            $('#directoriesPaginationInfo').text(info);
            renderPagination('#directoriesPaginationLinks', pagination, 'directory');
        }

        function renderValueFilters(schema) {
            if (!schema || !schema.length) {
                $('#valueFiltersCard').addClass('d-none');
                $('#valueFilters').html('');
                return;
            }

            let html = '';

            schema.forEach(function (field) {
                let key = escapeHtml(field.key || '');
                let label = escapeHtml(field.label || field.key || '');
                html += `<div class="col-md-4">`;
                html += `<label class="form-label small mb-1">${label}</label>`;

                if (field.type === 'list') {
                    html += `<select class="form-select form-select-sm value-filter-field" data-key="${key}">`;
                    html += `<option value="">Все</option>`;
                    (field.options || []).forEach(function (option) {
                        html += `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`;
                    });
                    html += `</select>`;
                } else if (field.type === 'directory') {
                    html += `<select class="form-select form-select-sm value-filter-field" data-key="${key}">`;
                    html += renderDirectoryFieldOptions(field, '');
                    html += `</select>`;
                    loadDirectoryValuesForField(field, '');
                } else if (field.type === 'date') {
                    html += `<input type="date" class="form-control form-control-sm value-filter-field" data-key="${key}">`;
                } else if (field.type === 'time') {
                    html += `<input type="time" class="form-control form-control-sm value-filter-field" data-key="${key}">`;
                } else if (field.type === 'number') {
                    html += `<input type="number" step="any" class="form-control form-control-sm value-filter-field" data-key="${key}" placeholder="Равно">`;
                } else {
                    html += `<input type="text" class="form-control form-control-sm value-filter-field" data-key="${key}" placeholder="Содержит">`;
                }

                html += `</div>`;
            });

            $('#valueFilters').html(html);
            $('#valueFiltersCard').removeClass('d-none');
            initSearchableSelects(document.getElementById('valueFilters'));
        }

        function collectValueFilters() {
            let filters = {};

            $('.value-filter-field').each(function () {
                let key = $(this).data('key');
                let value = $(this).val();

                if (key && value !== null && String(value).trim() !== '') {
                    filters[key] = String(value).trim();
                }
            });

            return filters;
        }

        function loadValues(page = 1) {
            if (!selectedDirectoryId) {
                return;
            }

            currentValuePage = page;

            $('#valuesTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">Загрузка...</td>
                </tr>
            `);

            $.ajax({
                url: directoryRoute('directoryValues', selectedDirectoryId),
                method: 'GET',
                data: {
                    page: page,
                    search: $('#valueSearchInput').val(),
                    filters: collectValueFilters()
                },
                success: function (response) {
                    selectedDirectoryData = response.directory;

                    $('#emptyValuesBlock').addClass('d-none');
                    $('#valuesBlock').removeClass('d-none');
                    $('#selectedDirectoryName').text(response.directory.name);
                    $('#selectedDirectoryDescription').text(response.directory.description || '');
                    $('#selectedDirectorySchemaSummary').text(schemaSummary(response.directory.schema || []));
                    if (!$('#valueFilters').children().length) {
                        renderValueFilters(response.directory.schema || []);
                    }

                    renderValues(response.items);
                    renderValuesPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderRecordPreview(item) {
            if (!item.data || Array.isArray(item.data) && item.data.length === 0) {
                return '';
            }

            let lines = [];
            let schema = selectedDirectoryData && selectedDirectoryData.schema ? selectedDirectoryData.schema : [];

            schema.forEach(function (field) {
                let value = item.data[field.key];

                if (value === null || value === undefined || value === '') {
                    return;
                }

                if (field.type === 'directory') {
                    let values = directoryValuesCache[field.directory_id] || [];
                    let directoryItem = values.find(function (entry) {
                        return String(entry.id) === String(value);
                    });

                    if (directoryItem) {
                        value = getDirectoryOptionLabel(field, directoryItem);
                    }
                }

                lines.push(`${field.label}: ${value}`);
            });

            if (!lines.length) {
                return '';
            }

            return `<div class="text-secondary small mt-1">${escapeHtml(lines.join(' | '))}</div>`;
        }

        function renderValues(items) {
            if (!items || !items.length) {
                $('#valuesTableBody').html(`
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-5">Записи не найдены</td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                html += `
                    <tr>
                        <td>${item.id}</td>
                        <td>
                            <div>${escapeHtml(item.value)}</div>
                            ${renderRecordPreview(item)}
                        </td>
                        <td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>
                        <td>${item.sort_order}</td>
                        <td>${renderBadgeActive(item.is_active)}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info edit-value" data-id="${item.id}"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger delete-value" data-id="${item.id}"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
            });

            $('#valuesTableBody').html(html);
        }

        function renderValuesPagination(pagination) {
            let info = pagination.total > 0
                ? `Показано ${pagination.from}-${pagination.to} из ${pagination.total}`
                : 'Нет записей';

            $('#valuesPaginationInfo').text(info);
            renderPagination('#valuesPaginationLinks', pagination, 'value');
        }

        function clearDirectoryForm() {
            $('#directoryForm')[0].reset();
            $('#directoryId').val('');
            $('#directoryDivisions').val([]);
            schemaFields = [];
            schemaFieldIndex = 0;
            renderSchemaFields();
        }

        function applyDirectoryPreset(presetKey) {
            let preset = directoryWizardPresets[presetKey];

            if (!preset) {
                return;
            }

            clearDirectoryForm();
            $('#directoryModalTitle').text(preset.title || 'Создать справочник');
            $('#directoryName').val(preset.name || '');
            $('#directoryCode').val(preset.code || '');
            $('#directoryDescription').val(preset.description || '');

            schemaFields = [];
            schemaFieldIndex = 0;

            (preset.schema || []).forEach(function (field) {
                addSchemaField(field);
            });

            if (!(preset.schema || []).length) {
                renderSchemaFields();
            }

            directoryModal.show();
        }

        function addSchemaField(data = null) {
            schemaFieldIndex++;

            schemaFields.push({
                uid: schemaFieldIndex,
                key: data?.key || '',
                label: data?.label || '',
                type: data?.type || 'text',
                tab: data?.tab || '',
                required: !!data?.required,
                unique: !!data?.unique,
                auto_generate: !!data?.auto_generate,
                formula: data?.formula || '',
                directory_id: data?.directory_id || '',
                directory_display_field: data?.directory_display_field || '',
                options: data?.options || []
            });

            renderSchemaFields();
        }

        function renderSchemaFields() {
            if (!schemaFields.length) {
                $('#schemaBuilder').html(`
                    <div class="text-secondary text-center py-4 border rounded">
                        Шаблон пока пустой
                    </div>
                `);
                return;
            }

            let html = '';

            schemaFields.forEach(function (field) {
                let optionsText = field.options ? field.options.join('\n') : '';
                let directoriesOptions = buildReferenceDirectoryOptions(field.directory_id);
                let displayFieldOptions = buildReferenceDirectoryDisplayOptions(field.directory_id, field.directory_display_field);

                html += `
                    <div class="card border-secondary mb-3 schema-field-card" data-uid="${field.uid}">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Название</label>
                                    <input type="text" class="form-control schema-label" value="${escapeHtml(field.label)}">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Ключ</label>
                                    <input type="text" class="form-control schema-key" value="${escapeHtml(field.key)}" placeholder="title">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Вкладка</label>
                                    <input type="text" class="form-control schema-tab" value="${escapeHtml(field.tab || '')}" placeholder="Основное">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Тип</label>
                                    <select class="form-select schema-type">
                                        <option value="text" ${field.type === 'text' ? 'selected' : ''}>Текст</option>
                                        <option value="number" ${field.type === 'number' ? 'selected' : ''}>Число</option>
                                        <option value="date" ${field.type === 'date' ? 'selected' : ''}>Дата</option>
                                        <option value="time" ${field.type === 'time' ? 'selected' : ''}>Время</option>
                                        <option value="list" ${field.type === 'list' ? 'selected' : ''}>Список</option>
                                        <option value="qr" ${field.type === 'qr' ? 'selected' : ''}>QR/штрихкод</option>
                                        <option value="directory" ${field.type === 'directory' ? 'selected' : ''}>Справочник</option>
                                        <option value="calc" ${field.type === 'calc' ? 'selected' : ''}>Р¤РѕСЂРјСѓР»Р°</option>
                                    </select>
                                </div>

                                <div class="col-md-2 d-flex align-items-end justify-content-end gap-2 flex-wrap">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input schema-required" type="checkbox" ${field.required ? 'checked' : ''}>
                                        <label class="form-check-label">Обяз.</label>
                                    </div>

                                    <div class="form-check mt-4">
                                        <input class="form-check-input schema-unique" type="checkbox" ${field.unique ? 'checked' : ''}>
                                        <label class="form-check-label">Уник.</label>
                                    </div>

                                    <button type="button" class="btn btn-outline-danger remove-schema-field">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <div class="col-md-12 schema-options-block ${field.type === 'list' ? '' : 'd-none'}">
                                    <label class="form-label">Варианты списка</label>
                                    <textarea class="form-control schema-options" rows="4" placeholder="Каждое значение с новой строки">${escapeHtml(optionsText)}</textarea>
                                </div>

                                <div class="col-md-12 schema-qr-block ${field.type === 'qr' ? '' : 'd-none'}">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input schema-auto-generate" type="checkbox" ${field.auto_generate ? 'checked' : ''}>
                                        <label class="form-check-label">Автогенерация, если поле пустое</label>
                                    </div>
                                    <div class="form-text">Можно ввести вручную или оставить пустым для автоматического кода.</div>
                                </div>

                                <div class="col-md-12 schema-calc-block ${field.type === 'calc' ? '' : 'd-none'}">
                                    <label class="form-label">Р¤РѕСЂРјСѓР»Р°</label>
                                    <input type="text" class="form-control schema-formula" value="${escapeHtml(field.formula || '')}" placeholder="price * quantity">
                                    <div class="form-text">РСЃРїРѕР»СЊР·СѓР№С‚Рµ РєР»СЋС‡Рё РґСЂСѓРіРёС… РїРѕР»РµР№ Рё РјР°С‚РµРјР°С‚РёРєСѓ: <code>price * quantity</code>, <code>(width + height) / 2</code>.</div>
                                </div>

                                <div class="col-md-6 schema-directory-block ${field.type === 'directory' ? '' : 'd-none'}">
                                    <label class="form-label">Справочник</label>
                                    <select class="form-select schema-directory">
                                        ${directoriesOptions}
                                    </select>
                                </div>

                                <div class="col-md-6 schema-directory-block ${field.type === 'directory' ? '' : 'd-none'}">
                                    <label class="form-label">Поле для отображения</label>
                                    <select class="form-select schema-directory-display">
                                        ${displayFieldOptions}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#schemaBuilder').html(html);
            $('#schemaBuilder .schema-type option[value="calc"]').text('Формула');
            $('#schemaBuilder .schema-calc-block .form-label').text('Формула');
            $('#schemaBuilder .schema-calc-block .form-text').html('Используйте ключи других полей и математику: <code>price * quantity</code>, <code>(width + height) / 2</code>.');
        }

        function syncSchemaFieldsFromDom() {
            let updated = [];

            $('#schemaBuilder .schema-field-card').each(function () {
                let card = $(this);
                let optionsText = card.find('.schema-options').val() || '';

                updated.push({
                    uid: Number(card.data('uid')),
                    label: (card.find('.schema-label').val() || '').trim(),
                    key: (card.find('.schema-key').val() || '').trim(),
                    tab: (card.find('.schema-tab').val() || '').trim(),
                    type: card.find('.schema-type').val(),
                    required: card.find('.schema-required').is(':checked'),
                    unique: card.find('.schema-unique').is(':checked'),
                    auto_generate: card.find('.schema-auto-generate').is(':checked'),
                    formula: (card.find('.schema-formula').val() || '').trim(),
                    directory_id: card.find('.schema-directory').val(),
                    directory_display_field: card.find('.schema-directory-display').val(),
                    options: optionsText
                        .split('\n')
                        .map(item => item.trim())
                        .filter(Boolean)
                });
            });

            schemaFields = updated;
        }

        function buildDirectoryPayload() {
            syncSchemaFieldsFromDom();

            let schema = schemaFields.map(function (field) {
                let item = {
                    label: field.label,
                    key: field.key,
                    type: field.type,
                    tab: field.tab || '',
                    required: field.required,
                    unique: field.unique
                };

                if (field.type === 'list') {
                    item.options = field.options || [];
                }

                if (field.type === 'qr') {
                    item.auto_generate = !!field.auto_generate;
                }

                if (field.type === 'calc') {
                    item.formula = field.formula || '';
                }

                if (field.type === 'directory') {
                    item.directory_id = field.directory_id;
                    item.directory_display_field = field.directory_display_field;
                }

                return item;
            });

            return {
                name: $('#directoryName').val(),
                code: $('#directoryCode').val(),
                description: $('#directoryDescription').val(),
                division_ids: $('#directoryDivisions').val() || [],
                schema: schema
            };
        }

        function clearValueForm() {
            $('#valueForm')[0].reset();
            $('#valueId').val('');
            $('#valueSortOrder').val(0);
            $('#valueIsActive').prop('checked', true);
            let modalDirectory = getValueModalDirectory();
            renderValueFields({}, modalDirectory ? modalDirectory.schema || [] : []);
        }

        function snapshotValueModalState() {
            let payload = buildValuePayload();

            return {
                title: $('#valueModalTitle').text(),
                valueId: $('#valueId').val(),
                code: $('#valueCode').val(),
                sortOrder: $('#valueSortOrder').val(),
                isActive: $('#valueIsActive').is(':checked'),
                payload: payload
            };
        }

        function restoreValueModalState(snapshot, selectedValues = {}) {
            let modalDirectory = getValueModalDirectory();
            let data = snapshot.payload && snapshot.payload.data ? Object.assign({}, snapshot.payload.data) : {};

            Object.keys(selectedValues).forEach(function (fieldKey) {
                data[fieldKey] = selectedValues[fieldKey];
            });

            $('#valueModalTitle').text(snapshot.title || 'Добавить запись');
            $('#valueId').val(snapshot.valueId || '');
            $('#valueCode').val(snapshot.code || '');
            $('#valueSortOrder').val(snapshot.sortOrder || 0);
            $('#valueIsActive').prop('checked', snapshot.isActive !== false);

            if (modalDirectory && modalDirectory.schema && modalDirectory.schema.length) {
                renderValueFields(data, modalDirectory.schema);
            } else {
                renderValueFields({ value: snapshot.payload ? snapshot.payload.value || '' : '' }, []);
            }
        }

        function openNestedAdminValueModal(fieldKey, directoryId) {
            let modalDirectory = getValueModalDirectory();
            let nestedDirectory = referenceDirectories.find(function (item) {
                return String(item.id) === String(directoryId);
            });

            if (!modalDirectory || !nestedDirectory) {
                return;
            }

            valueModalStack.push({
                directory: modalDirectory,
                returnFieldKey: fieldKey,
                snapshot: snapshotValueModalState()
            });

            valueModalDirectory = nestedDirectory;
            $('#valueId').val('');
            $('#valueCode').val('');
            $('#valueSortOrder').val(0);
            $('#valueIsActive').prop('checked', true);
            $('#valueModalTitle').text(`Добавить запись: ${nestedDirectory.name}`);
            renderValueFields({}, nestedDirectory.schema || []);
        }

        function unwindNestedAdminValueModal(createdValue = null) {
            let parentState = valueModalStack.pop();

            if (!parentState) {
                return;
            }

            valueModalDirectory = parentState.directory;

            let selectedValues = {};
            if (createdValue && parentState.returnFieldKey) {
                selectedValues[parentState.returnFieldKey] = String(createdValue.id);
            }

            restoreValueModalState(parentState.snapshot, selectedValues);
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

        function renderValueFieldControl(field, value) {
            let required = field.required ? 'required' : '';
            let requiredMark = field.required ? '<span class="text-danger">*</span>' : '';
            let html = `<div class="mb-3">`;

            html += `<label class="form-label">${escapeHtml(field.label)} ${requiredMark}</label>`;

            if (field.type === 'number') {
                html += `<input type="number" step="any" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            } else if (field.type === 'date') {
                html += `<input type="date" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            } else if (field.type === 'time') {
                html += `<input type="time" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(String(value || '').substring(0, 5))}" ${required}>`;
            } else if (field.type === 'calc') {
                html += `<input type="number" step="any" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" readonly>`;
            } else if (field.type === 'qr') {
                let qrRequired = field.auto_generate ? '' : required;
                let placeholder = field.auto_generate ? 'Оставьте пустым для автогенерации' : 'Введите QR/штрихкод';
                html += `
                    <div class="input-group">
                        <input type="text" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" placeholder="${placeholder}" ${qrRequired}>
                        <button type="button" class="btn btn-outline-info generate-qr-value">Сгенерировать</button>
                    </div>
                `;
            } else if (field.type === 'directory') {
                html += `
                    <div class="input-group">
                        <select class="form-select value-data-field" data-key="${field.key}" ${required}>
                            ${renderDirectoryFieldOptions(field, value)}
                        </select>
                        <button
                            type="button"
                            class="btn btn-outline-success open-nested-admin-value-modal"
                            data-directory-id="${field.directory_id || ''}"
                            data-field-key="${escapeHtml(field.key)}"
                            title="Добавить значение в связанный справочник"
                        >
                            +
                        </button>
                    </div>
                `;
                loadDirectoryValuesForField(field, value);
            } else if (field.type === 'list') {
                html += `<select class="form-select value-data-field" data-key="${field.key}" ${required}>`;
                html += `<option value="">Выберите значение</option>`;

                (field.options || []).forEach(function (option) {
                    let selected = String(value) === String(option) ? 'selected' : '';
                    html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                });

                html += `</select>`;
            } else {
                html += `<input type="text" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            }

            html += `</div>`;

            return html;
        }

        function renderValueFields(data = {}, schema = []) {
            if (!schema.length) {
                $('#valueDynamicFields').html(`
                    <div class="mb-3">
                        <label class="form-label">Значение</label>
                        <input type="text" class="form-control value-data-field" data-key="value" value="${escapeHtml(data.value || '')}" required>
                    </div>
                `);
                return;
            }

            let tabs = groupSchemaFieldsByTab(schema);
            let html = '';

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
                        html += renderValueFieldControl(field, data[field.key] ?? '');
                    });
                    html += '</div>';
                });

                html += '</div>';
            } else {
                schema.forEach(function (field) {
                    html += renderValueFieldControl(field, data[field.key] ?? '');
                });
            }

            $('#valueDynamicFields').html(html);
            initSearchableSelects(document.getElementById('valueDynamicFields'));
        }

        function buildValuePayload() {
            let modalDirectory = getValueModalDirectory();
            let schema = modalDirectory && modalDirectory.schema ? modalDirectory.schema : [];
            let payload = {
                code: $('#valueCode').val(),
                sort_order: $('#valueSortOrder').val(),
                is_active: $('#valueIsActive').is(':checked') ? 1 : 0
            };

            if (!schema.length) {
                payload.value = $('.value-data-field[data-key="value"]').val();
                return payload;
            }

            let data = {};

            $('.value-data-field').each(function () {
                data[$(this).data('key')] = $(this).val();
            });

            payload.data = data;

            return payload;
        }

        function updateCsvHelp() {
            let schema = selectedDirectoryData && selectedDirectoryData.schema ? selectedDirectoryData.schema : [];

            if (!schema.length) {
                $('#csvHelpBlock').html(`
                    Формат CSV:
                    <br><code>value;code;sort_order</code>
                `);
                return;
            }

            let columns = schema.map(field => field.key).concat(['code', 'sort_order']);

            $('#csvHelpBlock').html(`
                Формат CSV для этого шаблона:
                <br><code>${escapeHtml(columns.join(';'))}</code>
            `);
        }

        function generateQrClientValue() {
            return 'QR-' + new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14)
                + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        }

        $('#addDirectoryBtn').on('click', function () {
            clearDirectoryForm();
            $('#directoryModalTitle').text('Создать справочник');
            directoryModal.show();
        });

        $(document).on('click', '.directory-preset-btn', function () {
            applyDirectoryPreset($(this).data('preset'));
        });

        $('#addSchemaFieldBtn').on('click', function () {
            syncSchemaFieldsFromDom();
            addSchemaField();
        });

        $(document).on('change', '.schema-type', function () {
            let card = $(this).closest('.schema-field-card');
            card.find('.schema-options-block').toggleClass('d-none', $(this).val() !== 'list');
            card.find('.schema-qr-block').toggleClass('d-none', $(this).val() !== 'qr');
            card.find('.schema-calc-block').toggleClass('d-none', $(this).val() !== 'calc');
            card.find('.schema-directory-block').toggleClass('d-none', $(this).val() !== 'directory');
        });

        $(document).on('change', '.schema-directory', function () {
            let card = $(this).closest('.schema-field-card');
            card.find('.schema-directory-display').html(buildReferenceDirectoryDisplayOptions($(this).val(), ''));
        });

        $(document).on('click', '.generate-qr-value', function () {
            $(this).closest('.input-group').find('.value-data-field').val(generateQrClientValue()).trigger('input');
        });

        $(document).on('click', '.remove-schema-field', function () {
            let uid = Number($(this).closest('.schema-field-card').data('uid'));
            schemaFields = schemaFields.filter(field => field.uid !== uid);
            renderSchemaFields();
        });

        $('#directoryForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#directoryId').val();
            let url = id ? directoryRoute('directory', id) : directoryRoute('store');

            $.ajax({
                url: url,
                method: 'POST',
                data: buildDirectoryPayload(),
                success: function (response) {
                    showToast(response.message, 'success');
                    directoryModal.hide();
                    loadDirectories(currentDirectoryPage);

                    if (selectedDirectoryId && String(selectedDirectoryId) === String(id || response.directory?.id || '')) {
                        loadValues(currentValuePage);
                    }
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-directory', function () {
            let id = $(this).data('id');

            clearDirectoryForm();

            $.ajax({
                url: directoryRoute('directory', id),
                method: 'GET',
                success: function (response) {
                    let item = response.directory;

                    $('#directoryModalTitle').text('Редактировать справочник');
                    $('#directoryId').val(item.id);
                    $('#directoryName').val(item.name);
                    $('#directoryCode').val(item.code);
                    $('#directoryDescription').val(item.description);
                    $('#directoryDivisions').val(item.division_ids);

                    schemaFields = [];
                    schemaFieldIndex = 0;

                    (item.schema || []).forEach(function (field) {
                        addSchemaField(field);
                    });

                    if (!(item.schema || []).length) {
                        renderSchemaFields();
                    }

                    directoryModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-directory', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить справочник вместе со всеми записями?')) {
                return;
            }

            $.ajax({
                url: directoryRoute('directory', id),
                method: 'DELETE',
                success: function (response) {
                    showToast(response.message, 'success');

                    if (String(selectedDirectoryId) === String(id)) {
                        selectedDirectoryId = null;
                        selectedDirectoryData = null;
                        $('#valuesBlock').addClass('d-none');
                        $('#emptyValuesBlock').removeClass('d-none');
                    }

                    loadDirectories(currentDirectoryPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.select-directory', function (e) {
            e.preventDefault();
            selectedDirectoryId = $(this).data('id');
            $('#valueSearchInput').val('');
            $('#valueFilters').html('');
            $('#valueFiltersCard').addClass('d-none');
            loadDirectories(currentDirectoryPage);
            loadValues(1);
        });

        $('#addValueBtn').on('click', function () {
            if (!selectedDirectoryData) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            valueModalDirectory = selectedDirectoryData;
            valueModalStack = [];
            clearValueForm();
            $('#valueModalTitle').text('Добавить запись');
            valueModal.show();
        });

        $('#importDirectoryTemplateBtn').on('click', function () {
            $('#importDirectoryTemplateForm')[0].reset();
            importDirectoryTemplateModal.show();
        });

        $('#valueForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            let id = $('#valueId').val();
            let modalDirectory = getValueModalDirectory();
            let url = id ? directoryRoute('value', id) : directoryRoute('directoryValues', modalDirectory ? modalDirectory.id : selectedDirectoryId);

            $.ajax({
                url: url,
                method: 'POST',
                data: buildValuePayload(),
                success: function (response) {
                    upsertValueCache(modalDirectory ? modalDirectory.id : selectedDirectoryId, response.value || null);

                    if (!id && valueModalStack.length) {
                        unwindNestedAdminValueModal(response.value || null);
                        showToast(response.message, 'success');
                        return;
                    }

                    showToast(response.message, 'success');
                    valueModal.hide();
                    loadValues(currentValuePage);
                    loadDirectories(currentDirectoryPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-value', function () {
            let id = $(this).data('id');

            $.ajax({
                url: directoryRoute('value', id),
                method: 'GET',
                success: function (response) {
                    let item = response.value;

                    $('#valueModalTitle').text('Редактировать запись');
                    $('#valueId').val(item.id);
                    valueModalDirectory = selectedDirectoryData;
                    valueModalStack = [];
                    $('#valueCode').val(item.code);
                    $('#valueSortOrder').val(item.sort_order);
                    $('#valueIsActive').prop('checked', item.is_active);

                    if (selectedDirectoryData && selectedDirectoryData.schema && selectedDirectoryData.schema.length) {
                        renderValueFields(item.data || {}, selectedDirectoryData.schema);
                    } else {
                        renderValueFields({ value: item.value }, []);
                    }

                    valueModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.open-nested-admin-value-modal', function () {
            let directoryId = $(this).data('directory-id');
            let fieldKey = $(this).data('field-key');

            if (!directoryId || !fieldKey) {
                return;
            }

            openNestedAdminValueModal(fieldKey, directoryId);
        });

        $(document).on('click', '#valueModal [data-bs-dismiss="modal"]', function (e) {
            if (!valueModalStack.length) {
                return;
            }

            e.preventDefault();
            unwindNestedAdminValueModal();
        });

        $('#valueModal').on('hidden.bs.modal', function () {
            valueModalDirectory = null;
            valueModalStack = [];
        });

        $(document).on('click', '.delete-value', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить запись справочника?')) {
                return;
            }

            $.ajax({
                url: directoryRoute('value', id),
                method: 'DELETE',
                success: function (response) {
                    showToast(response.message, 'success');
                    loadValues(currentValuePage);
                    loadDirectories(currentDirectoryPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.export-directory-template', function () {
            let id = $(this).data('id');
            window.open(directoryRoute('directoryExport', id), '_blank');
        });

        $('#importCsvBtn').on('click', function () {
            if (!selectedDirectoryData) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            $('#csvForm')[0].reset();
            $('#csvHasHeader').prop('checked', true);
            updateCsvHelp();
            csvModal.show();
        });

        $('#printDirectoryBtn').on('click', function () {
            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            window.open(directoryRoute('directoryPrint', selectedDirectoryId), '_blank');
        });

        $('#printBarcodesBtn').on('click', function () {
            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            window.open(directoryRoute('directoryBarcodes', selectedDirectoryId), '_blank');
        });

        $('#csvForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            let formData = new FormData(this);

            $.ajax({
                url: directoryRoute('directoryImportCsv', selectedDirectoryId),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    showToast(response.message, 'success');
                    csvModal.hide();
                    loadValues(1);
                    loadDirectories(currentDirectoryPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#importDirectoryTemplateForm').on('submit', function (e) {
            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: directoryRoute('directoryImport'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    showToast(response.message, 'success');
                    importDirectoryTemplateModal.hide();
                    loadDirectories(1);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#directorySearchBtn').on('click', function () {
            loadDirectories(1);
        });

        $('#directorySearchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadDirectories(1);
            }
        });

        $('#valueSearchBtn').on('click', function () {
            loadValues(1);
        });

        $('#valueSearchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadValues(1);
            }
        });

        $(document).on('change keyup', '.value-filter-field', function (e) {
            if (e.type === 'change' || e.key === 'Enter') {
                loadValues(1);
            }
        });

        $('#resetValueFiltersBtn').on('click', function () {
            $('.value-filter-field').val('').trigger('change.select2');
            loadValues(1);
        });

        $(document).on('click', '.directory-page', function (e) {
            e.preventDefault();
            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadDirectories(page);
            }
        });

        $(document).on('click', '.value-page', function (e) {
            e.preventDefault();
            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadValues(page);
            }
        });

        renderSchemaFields();
        loadDirectories();
    </script>
@endpush
