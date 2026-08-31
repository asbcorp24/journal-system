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

        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" type="button" id="toggleDirectoriesSidebarBtn">
                <i class="bi bi-layout-sidebar-inset"></i>
                Список справочников
            </button>
        @if(in_array(session('user_role'), ['foreman', 'admin']))
            <button class="btn btn-primary" id="addDirectoryValueBtn" disabled>
                <i class="bi bi-plus-lg"></i>
                Добавить значение
            </button>
        @endif
        </div>
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
        <div class="col-lg-4" id="directoriesSidebarCol">
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

        <div class="col-lg-8" id="directoriesContentCol">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-semibold" id="selectedDirectoryTitle">Выберите справочник</div>
                            <div class="text-secondary small" id="selectedDirectoryDescription"></div>
                        </div>

                        <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                            <select id="savedDirectoryFilterSelect" class="form-select form-select-sm" style="min-width: 220px;" disabled>
                                <option value="">Все поля</option>
                            </select>

                            <button type="button" class="btn btn-outline-warning btn-sm" id="openDirectoryFiltersBuilderBtn" disabled>
                                Конструктор
                            </button>

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

                            @if(session('user_role') === 'admin')
                                <button type="button" class="btn btn-outline-danger btn-sm" id="toggleDeletedDirectoryValuesBtn" disabled>
                                    <i class="bi bi-trash3"></i>
                                    Удалённые
                                </button>
                            @endif

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

                        <div class="card bg-dark border-secondary mb-3 d-none" id="directoryValueScriptsBox">
                            <div class="card-body py-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label">JS-РєРѕРґ</label>
                                        <select class="form-select" id="directoryValueScriptSelect">
                                            <option value="">Выберите JS-код</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-outline-info w-100" id="runDirectoryValueScriptBtn">
                                            Выполнить
                                        </button>
                                    </div>
                                </div>
                                <div class="small text-secondary mt-2" id="directoryValueScriptDescription"></div>
                            </div>
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
        let templateLists = @json($templateLists ?? []);
        let selectedDirectory = null;
        let currentDirectoryValues = [];
        let directoryValuesCache = {};
        let currentValuesPage = 1;
        let currentSavedDirectoryFilterId = '';
        let directoryFiltersVisible = false;
        let directoryParentFilterModes = {};
        let directoriesSidebarVisible = localStorage.getItem('userDirectoriesSidebarVisible') !== '0';
        let showDeletedDirectoryValues = false;
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

        function updateDirectoriesSidebarLayout() {
            $('#directoriesSidebarCol').toggleClass('d-none', !directoriesSidebarVisible);
            $('#directoriesContentCol')
                .toggleClass('col-lg-8', directoriesSidebarVisible)
                .toggleClass('col-lg-12', !directoriesSidebarVisible);

            $('#toggleDirectoriesSidebarBtn').html(
                directoriesSidebarVisible
                    ? '<i class="bi bi-layout-sidebar-inset"></i> Скрыть список'
                    : '<i class="bi bi-layout-sidebar"></i> Показать список'
            );
        }

        function findTemplateListById(templateListId) {
            return templateLists.find(function (item) {
                return String(item.id) === String(templateListId || '');
            }) || null;
        }

        function buildTemplateListDataKey(fieldKey, itemKey, subFieldKey) {
            return `${fieldKey}__${itemKey}__${subFieldKey}`;
        }

        function getVisibleTableSchema(schema = []) {
            return (schema || []).filter(function (field) {
                return field.show_in_table !== false;
            });
        }

        function getDirectoryTableSettings(directory = null) {
            let settings = directory && directory.table_settings ? directory.table_settings : {};

            return Object.assign({
                show_code: true,
                show_sort_order: true,
                show_status: true,
            }, settings || {});
        }

        function getDirectoryValuesTableColumnCount(schema = [], directory = null) {
            let visibleSchema = getVisibleTableSchema(schema);
            let settings = getDirectoryTableSettings(directory || selectedDirectory);
            let total = (visibleSchema.length ? visibleSchema.length : 1) + 1;

            if (settings.show_code !== false) {
                total += 1;
            }

            if (settings.show_sort_order !== false) {
                total += 1;
            }

            if (canManageDirectoryValues) {
                total += 1;
            }

            return total;
        }

        function collectTemplateListDynamicFields(schema, data = {}) {
            let dynamicTabs = [];

            (schema || []).forEach(function (field) {
                if (field.type !== 'template_list') {
                    return;
                }

                let selectedItemKey = String(data[field.key] || '').trim();
                if (!selectedItemKey) {
                    return;
                }

                let templateList = findTemplateListById(field.template_list_id);
                let selectedItem = templateList && Array.isArray(templateList.items)
                    ? templateList.items.find(function (item) {
                        return String(item.key) === selectedItemKey;
                    })
                    : null;

                if (!selectedItem) {
                    return;
                }

                dynamicTabs.push({
                    name: `${field.label}: ${selectedItem.name}`,
                    fields: (selectedItem.fields || []).map(function (subField) {
                        return {
                            key: buildTemplateListDataKey(field.key, selectedItem.key, subField.key),
                            label: subField.label,
                            type: subField.type,
                            options: subField.options || [],
                            required: !!subField.required,
                        };
                    }),
                });
            });

            return dynamicTabs;
        }

        function getTemplateListSelectedItem(field, data = {}) {
            let templateList = findTemplateListById(field.template_list_id);
            let rawValue = data[field.key];
            let selectedItemKey = '';

            if (rawValue && typeof rawValue === 'object' && !Array.isArray(rawValue)) {
                selectedItemKey = String(rawValue.value || '').trim();
            } else {
                selectedItemKey = String(rawValue || '').trim();
            }

            if (!selectedItemKey && templateList) {
                let matchedKeys = (templateList.items || []).map(function (item) {
                    let hasValues = (item.fields || []).some(function (subField) {
                        let compoundKey = buildTemplateListDataKey(field.key, item.key, subField.key);
                        let compoundValue = data[compoundKey];
                        return compoundValue !== null && compoundValue !== undefined && String(compoundValue).trim() !== '';
                    });

                    return hasValues ? String(item.key) : null;
                }).filter(Boolean);

                matchedKeys = [...new Set(matchedKeys)];
                selectedItemKey = matchedKeys.length === 1 ? matchedKeys[0] : '';
            }

            if (!templateList || !selectedItemKey) {
                return null;
            }

            return (templateList.items || []).find(function (item) {
                return String(item.key) === selectedItemKey;
            }) || null;
        }

        function getTemplateListDisplayLines(field, data = {}) {
            let selectedItem = getTemplateListSelectedItem(field, data);

            if (!selectedItem) {
                return [];
            }

            return (selectedItem.fields || []).map(function (subField) {
                let compoundKey = buildTemplateListDataKey(field.key, selectedItem.key, subField.key);
                let value = data[compoundKey];

                if (value === null || value === undefined || value === '') {
                    return null;
                }

                return {
                    key: compoundKey,
                    label: subField.label,
                    value: value,
                    type: subField.type,
                };
            }).filter(Boolean);
        }

        function stringifyTemplateFieldValue(field, value, data = {}) {
            if (field.type === 'template_list') {
                let parts = [];

                getTemplateListDisplayLines(field, data).forEach(function (line) {
                    let lineValue = String(line.value || '').trim();
                    if (lineValue) {
                        parts.push(lineValue);
                    }
                });

                return [...new Set(parts.filter(Boolean))].join(' ').trim();
            }

            if (value === null || value === undefined) {
                return '';
            }

            return String(value);
        }

        function buildRuntimeFieldValues(schema, data = {}) {
            let runtimeData = Object.assign({}, data || {});

            (schema || []).forEach(function (field) {
                if (field.type !== 'template') {
                    return;
                }

                let template = String(field.template || '');
                runtimeData[field.key] = template.replace(new RegExp('\\{\\{\\s*([a-zA-Z][a-zA-Z0-9_]*)\\s*\\}\\}', 'g'), function (_, key) {
                    let sourceField = (schema || []).find(function (item) {
                        return String(item.key || '') === String(key || '');
                    }) || {};

                    return stringifyTemplateFieldValue(sourceField, runtimeData[key], runtimeData);
                });
            });

            return runtimeData;
        }

        function collectTemplateListRowBlocks(schema, data = {}) {
            let blocks = [];

            (schema || []).forEach(function (field) {
                if (field.type !== 'template_list') {
                    return;
                }

                if (field.show_in_table === false) {
                    return;
                }

                let selectedItem = getTemplateListSelectedItem(field, data);
                let lines = getTemplateListDisplayLines(field, data);

                if (!selectedItem || !lines.length) {
                    return;
                }

                blocks.push({
                    fieldLabel: field.label || field.key || 'Список шаблонов',
                    templateName: selectedItem.name || '',
                    lines: lines,
                });
            });

            return blocks;
        }

        function renderTemplateListRowBlocks(blocks = [], colspan = 1) {
            if (!blocks.length) {
                return '';
            }

            let html = `<tr class="template-list-details-row"><td colspan="${colspan}" class="pt-0 border-0"><div class="ps-2 pb-2">`;

            blocks.forEach(function (block, index) {
                html += `
                    <div class="${index > 0 ? 'mt-2' : ''}">
                        <div class="small fw-semibold text-info">${escapeHtml(block.fieldLabel)}: ${escapeHtml(block.templateName)}</div>
                        <div class="small text-secondary mt-1">
                            ${block.lines.map(function (line) {
                                return `${escapeHtml(line.label)}: ${escapeHtml(String(line.value))}`;
                            }).join(', ')}
                        </div>
                    </div>
                `;
            });

            html += `</div></td></tr>`;

            return html;
        }

        function buildDirectoryFilterSchema(schema, filterValues = {}) {
            let expanded = [];

            (schema || []).forEach(function (field) {
                expanded.push(field);

                if (field.type !== 'template_list') {
                    return;
                }

                let selectedItem = getTemplateListSelectedItem(field, filterValues);
                if (!selectedItem) {
                    return;
                }

                (selectedItem.fields || []).forEach(function (subField) {
                    expanded.push({
                        key: buildTemplateListDataKey(field.key, selectedItem.key, subField.key),
                        label: `${field.label} / ${selectedItem.name} / ${subField.label}`,
                        type: subField.type,
                        options: subField.options || [],
                    });
                });
            });

            return expanded;
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

        function buildParentTreeOptionLabel(field, item, valuesMap) {
            let chain = [];
            let visited = new Set();
            let current = item;

            while (current && !visited.has(String(current.id))) {
                visited.add(String(current.id));
                chain.unshift(getDirectoryOptionLabel(field, current));
                let parentId = current.data && current.data[field.key] !== undefined && current.data[field.key] !== null
                    ? String(current.data[field.key]).trim()
                    : '';
                current = parentId && valuesMap[parentId] ? valuesMap[parentId] : null;
            }

            return chain.join(' / ');
        }

        function getParentTreeOrderedValues(field, values) {
            let valuesMap = {};
            let childrenMap = {};
            let roots = [];
            let ordered = [];
            let visited = new Set();

            values.forEach(function (item) {
                valuesMap[String(item.id)] = item;
            });

            values.forEach(function (item) {
                let parentId = item.data && item.data[field.key] !== undefined && item.data[field.key] !== null
                    ? String(item.data[field.key]).trim()
                    : '';

                if (!parentId || !valuesMap[parentId] || parentId === String(item.id)) {
                    roots.push(item);
                    return;
                }

                if (!childrenMap[parentId]) {
                    childrenMap[parentId] = [];
                }

                childrenMap[parentId].push(item);
            });

            function sortItems(items) {
                items.sort(function (left, right) {
                    return getDirectoryOptionLabel(field, left).localeCompare(getDirectoryOptionLabel(field, right), 'ru', {
                        sensitivity: 'base'
                    });
                });
            }

            sortItems(roots);

            Object.keys(childrenMap).forEach(function (parentId) {
                sortItems(childrenMap[parentId]);
            });

            function walk(item, depth) {
                let itemId = String(item.id);

                if (visited.has(itemId)) {
                    return;
                }

                visited.add(itemId);
                ordered.push({
                    item: item,
                    depth: depth,
                    label: buildParentTreeOptionLabel(field, item, valuesMap),
                });

                (childrenMap[itemId] || []).forEach(function (child) {
                    walk(child, depth + 1);
                });
            }

            roots.forEach(function (root) {
                walk(root, 0);
            });

            values.forEach(function (item) {
                if (!visited.has(String(item.id))) {
                    walk(item, 0);
                }
            });

            return ordered;
        }

        function getDirectoryFilterMode(field) {
            if (field.type !== 'parent') {
                return 'list';
            }

            return directoryParentFilterModes[field.key] || 'list';
        }

        function renderDirectoryFieldOptions(field, selectedValue, mode = 'list') {
            let html = '<option value="">Выберите значение</option>';
            let values = directoryValuesCache[field.directory_id] || [];

            if (field.type === 'parent' && mode === 'tree') {
                getParentTreeOrderedValues(field, values).forEach(function (entry) {
                    let selected = String(selectedValue || '') === String(entry.item.id) ? 'selected' : '';
                    let prefix = entry.depth > 0 ? ('    '.repeat(entry.depth) + '> ') : '';
                    html += '<option value="' + escapeHtml(String(entry.item.id)) + '" ' + selected + '>' + escapeHtml(prefix + entry.label) + '</option>';
                });

                return html;
            }

            values.forEach(function (item) {
                let selected = String(selectedValue || '') === String(item.id) ? 'selected' : '';
                let label = getDirectoryOptionLabel(field, item);
                html += '<option value="' + escapeHtml(String(item.id)) + '" ' + selected + '>' + escapeHtml(label) + '</option>';
            });

            return html;
        }

        function renderParentFilterSelect(field, selectedValue) {
            let key = escapeHtml(field.key || '');
            let mode = getDirectoryFilterMode(field);
            let html = `<div class="d-grid gap-2">`;
            html += `<select class="form-select form-select-sm directory-parent-filter-mode" data-key="${key}">`;
            html += `<option value="list" ${mode === 'list' ? 'selected' : ''}>РЎРїРёСЃРѕРє</option>`;
            html += `<option value="tree" ${mode === 'tree' ? 'selected' : ''}>Дерево</option>`;
            html += `</select>`;
            html += `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
            html += renderDirectoryFieldOptions(field, selectedValue, mode);
            html += `</select>`;
            html += `</div>`;
            return html;
        }

        function refreshParentFilterOptions(field, selectedValue = '') {
            let mode = getDirectoryFilterMode(field);
            $(`.directory-value-filter[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue, mode));
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

                    if (field.type === 'parent') {
                        refreshParentFilterOptions(field, selectedValue);
                    } else {
                        $(`.directory-value-filter[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue));
                    }
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
            populateSavedDirectoryFilterSelect();
            $('#toggleDirectoryFavoriteBtn')
                .prop('disabled', false)
                .html(`<i class="bi ${selectedDirectory.is_favorite ? 'bi-star-fill' : 'bi-star'}"></i> ${selectedDirectory.is_favorite ? 'Убрать' : 'Избранное'}`);
        }

        function populateSavedDirectoryFilterSelect() {
            let select = $('#savedDirectoryFilterSelect');
            let filters = selectedDirectory && Array.isArray(selectedDirectory.filter_presets) ? selectedDirectory.filter_presets : [];
            let html = '<option value="">Все поля</option>';

            filters.forEach(function (filter) {
                html += `<option value="${filter.id}">${escapeHtml(filter.name)}</option>`;
            });

            select.html(html);
            select.prop('disabled', !selectedDirectory);

            if (currentSavedDirectoryFilterId) {
                select.val(currentSavedDirectoryFilterId);
            }

            $('#openDirectoryFiltersBuilderBtn').prop('disabled', !selectedDirectory);
        }

        function ensureDirectoryFiltersPanel() {
            let card = $('#directoryValueFiltersCard');

            if (!card.length) {
                return;
            }

            let header = card.find('.d-flex.justify-content-between.align-items-center.gap-2.mb-2').first();

            if (header.length && !$('#toggleDirectoryFiltersBtn').length) {
                $('<button type="button" class="btn btn-sm btn-outline-light" id="toggleDirectoryFiltersBtn">Показать</button>')
                    .insertBefore($('#resetDirectoryValueFiltersBtn'));
            }

            let filters = $('#directoryValueFilters');

            if (filters.length && !$('#directoryValueFiltersPanel').length) {
                filters.before('<div class="text-secondary small mb-2">Фильтры продолжают работать, даже когда панель скрыта.</div>');
                filters.wrap('<div class="d-none" id="directoryValueFiltersPanel"></div>');
            }
        }

        function updateDirectoryFiltersButton() {
            let btn = $('#toggleDirectoryFiltersBtn');

            if (!btn.length) {
                return;
            }

            let activeCount = getActiveDirectoryFiltersCount();
            let label = directoryFiltersVisible ? 'Скрыть' : 'Показать';
            let badge = activeCount > 0
                ? ` <span class="badge bg-info text-dark ms-1">${activeCount}</span>`
                : '';

            btn.html(`${label}${badge}`);
        }

        function getActiveDirectoryFiltersCount() {
            return Object.keys(collectDirectoryValueFilters()).length;
        }

        function applySavedDirectoryFilter(filterId, shouldLoad = true) {
            currentSavedDirectoryFilterId = String(filterId || '');
            let filter = selectedDirectory && Array.isArray(selectedDirectory.filter_presets)
                ? selectedDirectory.filter_presets.find(function (item) {
                    return String(item.id) === currentSavedDirectoryFilterId;
                })
                : null;

            if (!selectedDirectory) {
                renderDirectoryValueFilters([]);
            } else if (!filter) {
                renderDirectoryValueFilters(selectedDirectory.schema || []);
            } else {
                renderDirectoryValueFilters(selectedDirectory.schema || [], filter.visible_fields || [], filter.values || {});
            }

            if (shouldLoad && selectedDirectory) {
                loadValues(1);
            }
        }

        function renderDirectoryValueFilters(schema, visibleKeys = null, presetValues = {}) {
            if (!schema || !schema.length) {
                $('#directoryValueFiltersCard').addClass('d-none');
                $('#directoryValueFilters').html('');
                return;
            }

            if (Array.isArray(visibleKeys) && visibleKeys.length) {
                schema = schema.filter(function (field) {
                    return visibleKeys.includes(String(field.key || ''));
                });
            }

            let html = '';

            schema.forEach(function (field) {
                let key = escapeHtml(field.key || '');
                let label = escapeHtml(field.label || field.key || '');
                html += `<div class="col-md-4">`;
                html += `<label class="form-label small mb-1">${label}</label>`;

                if (field.type === 'list') {
                    html += `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
                    html += `<option value="">Р 'РЎРѓР Вµ</option>`;
                    (field.options || []).forEach(function (option) {
                        html += `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`;
                    });
                    html += `</select>`;
                } else if (field.type === 'directory') {
                    html += `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
                    html += renderDirectoryFieldOptions(field, '');
                    html += `</select>`;
                    loadDirectoryValuesForField(field, '');
                } else if (field.type === 'parent') {
                    html += renderParentFilterSelect(field, '');
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
            Object.keys(presetValues || {}).forEach(function (key) {
                $(`.directory-value-filter[data-key="${key}"]`).val(presetValues[key]);
            });
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

        function normalizeDirectoryFilterValue(rawValue) {
            if (rawValue && typeof rawValue === 'object' && !Array.isArray(rawValue)) {
                return {
                    operator: String(rawValue.operator || '').trim() || 'eq',
                    value: rawValue.value ?? '',
                    value_to: rawValue.value_to ?? '',
                };
            }

            return {
                operator: 'eq',
                value: rawValue ?? '',
                value_to: '',
            };
        }

        function supportsAdvancedDirectoryFilter(field) {
            return ['text', 'number', 'date', 'time', 'directory_text', 'calc', 'template'].includes(field.type);
        }

        function renderDirectoryFilterOperator(field, operator = 'eq') {
            if (!supportsAdvancedDirectoryFilter(field)) {
                return '';
            }

            let options = [
                {value: 'eq', label: '='},
                {value: 'gt', label: '>'},
                {value: 'lt', label: '<'},
                {value: 'neq', label: '<>'},
                {value: 'between', label: 'between'},
            ];

            if (field.type !== 'number' && field.type !== 'date' && field.type !== 'time' && field.type !== 'calc') {
                options.push({value: 'contains', label: 'contains'});
            }

            let html = `<select class="form-select form-select-sm directory-value-filter-operator mb-2" data-key="${escapeHtml(field.key || '')}">`;
            options.forEach(function (item) {
                html += `<option value="${item.value}" ${item.value === operator ? 'selected' : ''}>${item.label}</option>`;
            });
            html += `</select>`;
            return html;
        }

        function renderDirectoryFilterMainControl(field, normalizedValue) {
            let key = escapeHtml(field.key || '');
            let value = normalizedValue.value ?? '';

            if (field.type === 'list') {
                let html = `<select class="form-select form-select-sm directory-value-filter" data-key="${key}"><option value="">Р 'РЎРѓР Вµ</option>`;
                (field.options || []).forEach(function (option) {
                    let selected = String(option) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'template_list') {
                let templateList = findTemplateListById(field.template_list_id);
                let html = `<select class="form-select form-select-sm directory-value-filter directory-template-list-filter" data-key="${key}"><option value="">Все варианты</option>`;
                (templateList?.items || []).forEach(function (item) {
                    let selected = String(item.key) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(item.key)}" ${selected}>${escapeHtml(item.name)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'directory') {
                let html = `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
                html += renderDirectoryFieldOptions(field, value);
                html += `</select>`;
                loadDirectoryValuesForField(field, value);
                return html;
            }

            if (field.type === 'parent') {
                loadDirectoryValuesForField(field, value);
                return renderParentFilterSelect(field, value);
            }

            if (field.type === 'date') {
                return `<input type="date" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'time') {
                return `<input type="time" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'number' || field.type === 'calc') {
                return `<input type="number" step="any" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
            }

            return `<input type="text" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
        }

        function renderDirectoryFilterSecondControl(field, normalizedValue) {
            if (!supportsAdvancedDirectoryFilter(field)) {
                return '';
            }

            let key = escapeHtml(field.key || '');
            let valueTo = normalizedValue.value_to ?? '';
            let hiddenClass = normalizedValue.operator === 'between' ? '' : ' d-none';

            if (field.type === 'date') {
                return `<input type="date" class="form-control form-control-sm directory-value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
            }

            if (field.type === 'time') {
                return `<input type="time" class="form-control form-control-sm directory-value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
            }

            if (field.type === 'number' || field.type === 'calc') {
                return `<input type="number" step="any" class="form-control form-control-sm directory-value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
            }

            return `<input type="text" class="form-control form-control-sm directory-value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
        }

        renderDirectoryValueFilters = function (schema, visibleKeys = null, presetValues = {}) {
            if (!schema || !schema.length) {
                $('#directoryValueFiltersCard').addClass('d-none');
                $('#directoryValueFiltersPanel').addClass('d-none');
                $('#directoryValueFilters').html('');
                return;
            }

            schema = buildDirectoryFilterSchema(schema, presetValues);

            if (Array.isArray(visibleKeys) && visibleKeys.length) {
                schema = schema.filter(function (field) {
                    return visibleKeys.includes(String(field.key || ''));
                });
            }

            let html = '';

            schema.forEach(function (field) {
                let label = escapeHtml(field.label || field.key || '');
                let normalizedValue = normalizeDirectoryFilterValue(presetValues[field.key]);
                html += `<div class="col-md-4">`;
                html += `<label class="form-label small mb-1">${label}</label>`;
                html += renderDirectoryFilterOperator(field, normalizedValue.operator);
                html += renderDirectoryFilterMainControl(field, normalizedValue);
                html += renderDirectoryFilterSecondControl(field, normalizedValue);
                html += `</div>`;
            });

            $('#directoryValueFilters').html(html);
            ensureDirectoryFiltersPanel();
            $('#directoryValueFiltersCard').removeClass('d-none');
            $('#directoryValueFiltersPanel').toggleClass('d-none', !directoryFiltersVisible);
            updateDirectoryFiltersButton();
            initSearchableSelects(document.getElementById('directoryValueFilters'));
        };

        collectDirectoryValueFilters = function () {
            let filters = {};

            $('.directory-value-filter').each(function () {
                let key = $(this).data('key');
                let value = $(this).val();
                let operatorField = $(`.directory-value-filter-operator[data-key="${key}"]`);
                let betweenField = $(`.directory-value-filter-between[data-key="${key}"]`);
                let operator = operatorField.length ? (operatorField.val() || 'eq') : 'eq';
                let valueTo = betweenField.length ? betweenField.val() : '';

                if (key && value !== null && String(value).trim() !== '') {
                    filters[key] = {
                        operator: operator,
                        value: String(value).trim(),
                    };
                }

                if (key && operator === 'between' && valueTo !== null && String(valueTo).trim() !== '') {
                    filters[key] = filters[key] || {
                        operator: operator,
                        value: String(value || '').trim(),
                    };
                    filters[key].value_to = String(valueTo).trim();
                }
            });

            return filters;
        };

        function renderValuesTableHead(schema, directory = null) {
            let html = '';
            let visibleSchema = getVisibleTableSchema(schema);
            let tableSettings = getDirectoryTableSettings(directory || selectedDirectory);

            if (visibleSchema.length) {
                visibleSchema.forEach(function (field) {
                    html += `<th>${escapeHtml(field.label)}</th>`;
                });
            } else {
                html += '<th>\u0417\u043d\u0430\u0447\u0435\u043d\u0438\u0435</th>';
            }

            if (tableSettings.show_code !== false) {
                html += '\u003cth\u003e\u041a\u043e\u0434\u003c/th\u003e';
            }

            if (tableSettings.show_sort_order !== false) {
                html += '<th>\u0421\u043e\u0440\u0442\u0438\u0440\u043e\u0432\u043a\u0430</th>';
            }

            html += '<th>\u0421\u043e\u0437\u0434\u0430\u043d\u043e</th>';

            if (canManageDirectoryValues) {
                html += '<th class="text-end">\u0414\u0435\u0439\u0441\u0442\u0432\u0438\u044f</th>';
            }

            $('#directoryValuesHead').html(html);
        }

        function renderValues(items) {
            if (!selectedDirectory) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">
                            \u0412\u044b\u0431\u0435\u0440\u0438\u0442\u0435 \u0441\u043f\u0440\u0430\u0432\u043e\u0447\u043d\u0438\u043a
                        </td>
                    </tr>
                `);
                return;
            }

            let schema = selectedDirectory.schema || [];
            let visibleSchema = getVisibleTableSchema(schema);
            let tableSettings = getDirectoryTableSettings(selectedDirectory);
            let totalColumns = getDirectoryValuesTableColumnCount(schema, selectedDirectory);

            if (!items || items.length === 0) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="${totalColumns}" class="text-center text-secondary py-5">
                            \u0417\u043d\u0430\u0447\u0435\u043d\u0438\u044f \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let templateListBlocks = schema.length ? collectTemplateListRowBlocks(schema, item.data || {}) : [];
                html += '<tr>';

                if (visibleSchema.length) {
                    visibleSchema.forEach(function (field) {
                        let value = item.data && item.data[field.key] !== undefined ? item.data[field.key] : '';

                        if ((field.type === 'directory' || field.type === 'parent') && value !== null && value !== '') {
                            let values = directoryValuesCache[field.directory_id] || [];
                            let directoryItem = values.find(function (entry) {
                                return String(entry.id) === String(value);
                            });

                            if (directoryItem) {
                                value = getDirectoryOptionLabel(field, directoryItem);
                            }
                        }

                        if (field.type === 'image') {
                            let imageUrl = item.image_urls && item.image_urls[field.key] ? item.image_urls[field.key] : '';
                            value = imageUrl
                                ? `<a href="${escapeHtml(imageUrl)}" target="_blank"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(field.label)}" class="img-thumbnail" style="max-width:72px;max-height:72px;"></a>`
                                : '<span class="text-secondary">—</span>';
                            html += `<td>${value}</td>`;
                            return;
                        }

                        html += `<td>${value === null || value === '' ? '<span class="text-secondary">—</span>' : escapeHtml(String(value))}</td>`;
                    });
                } else {
                    html += `<td>${escapeHtml(item.value || '')}</td>`;
                }

                if (tableSettings.show_code !== false) {
                    html += `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">—</span>'}</td>`;
                }

                if (tableSettings.show_sort_order !== false) {
                    html += `<td>${escapeHtml(String(item.sort_order ?? 0))}</td>`;
                }

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
                html += renderTemplateListRowBlocks(templateListBlocks, totalColumns);
            });

            $('#directoryValuesBody').html(html);
        }

        renderDirectoryFilterMainControl = function (field, normalizedValue) {
            let key = escapeHtml(field.key || '');
            let value = normalizedValue.value ?? '';

            if (field.type === 'list') {
                let html = `<select class="form-select form-select-sm directory-value-filter" data-key="${key}"><option value="">Р 'РЎРѓР Вµ</option>`;
                (field.options || []).forEach(function (option) {
                    let selected = String(option) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'template_list') {
                let templateList = findTemplateListById(field.template_list_id);
                let html = `<select class="form-select form-select-sm directory-value-filter directory-template-list-filter" data-key="${key}"><option value="">Все варианты</option>`;
                (templateList?.items || []).forEach(function (item) {
                    let selected = String(item.key) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(item.key)}" ${selected}>${escapeHtml(item.name)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'directory') {
                let html = `<select class="form-select form-select-sm directory-value-filter" data-key="${key}">`;
                html += renderDirectoryFieldOptions(field, value);
                html += `</select>`;
                loadDirectoryValuesForField(field, value);
                return html;
            }

            if (field.type === 'parent') {
                loadDirectoryValuesForField(field, value);
                return renderParentFilterSelect(field, value);
            }

            if (field.type === 'date') {
                return `<input type="date" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'time') {
                return `<input type="time" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'number' || field.type === 'calc') {
                return `<input type="number" step="any" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
            }

            return `<input type="text" class="form-control form-control-sm directory-value-filter" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
        };

        renderValues = function (items) {
            if (!selectedDirectory) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">
                            \u0412\u044b\u0431\u0435\u0440\u0438\u0442\u0435 \u0441\u043f\u0440\u0430\u0432\u043e\u0447\u043d\u0438\u043a
                        </td>
                    </tr>
                `);
                return;
            }

            let schema = selectedDirectory.schema || [];
            let visibleSchema = getVisibleTableSchema(schema);
            let tableSettings = getDirectoryTableSettings(selectedDirectory);
            let totalColumns = getDirectoryValuesTableColumnCount(schema, selectedDirectory);

            if (!items || items.length === 0) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="${totalColumns}" class="text-center text-secondary py-5">
                            \u0417\u043d\u0430\u0447\u0435\u043d\u0438\u044f \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let templateListBlocks = schema.length ? collectTemplateListRowBlocks(schema, item.data || {}) : [];
                html += '<tr>';

                if (visibleSchema.length) {
                    visibleSchema.forEach(function (field) {
                        let value = item.data && item.data[field.key] !== undefined ? item.data[field.key] : '';

                        if ((field.type === 'directory' || field.type === 'parent') && value !== null && value !== '') {
                            let values = directoryValuesCache[field.directory_id] || [];
                            let directoryItem = values.find(function (entry) {
                                return String(entry.id) === String(value);
                            });

                            if (directoryItem) {
                                value = getDirectoryOptionLabel(field, directoryItem);
                            }
                        }

                        if (field.type === 'image') {
                            let imageUrl = item.image_urls && item.image_urls[field.key] ? item.image_urls[field.key] : '';
                            value = imageUrl
                                ? `<a href="${escapeHtml(imageUrl)}" target="_blank"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(field.label)}" class="img-thumbnail" style="max-width:72px;max-height:72px;"></a>`
                                : '<span class="text-secondary">-</span>';
                            html += `<td>${value}</td>`;
                            return;
                        }

                        if (field.type === 'template_list') {
                            let selectedItem = getTemplateListSelectedItem(field, item.data || {});
                            let cellHtml = selectedItem
                                ? `<div class="fw-semibold">${escapeHtml(selectedItem.name)}</div>`
                                : '<span class="text-secondary">-</span>';

                            html += `<td>${cellHtml}</td>`;
                            return;
                        }

                        html += `<td>${value === null || value === '' ? '<span class="text-secondary">-</span>' : escapeHtml(String(value))}</td>`;
                    });
                } else {
                    html += `<td>${escapeHtml(item.value || '')}</td>`;
                }

                if (tableSettings.show_code !== false) {
                    html += `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>`;
                }

                if (tableSettings.show_sort_order !== false) {
                    html += `<td>${escapeHtml(String(item.sort_order ?? 0))}</td>`;
                }

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
                html += renderTemplateListRowBlocks(templateListBlocks, totalColumns);
            });

            $('#directoryValuesBody').html(html);
        };

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
                        renderValuesTableHead(selectedDirectory.schema || [], selectedDirectory);
                        renderDirectoryValueFilters(selectedDirectory.schema || []);
                        loadValues();
                    } else {
                        renderValuesTableHead([], null);
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
            let columnCount = getDirectoryValuesTableColumnCount(selectedDirectory.schema || [], selectedDirectory);

            $('#directoryValuesBody').html(`
                <tr>
                    <td colspan="${columnCount}" class="text-center text-secondary py-5">
                        \u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...
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
                    renderValuesTableHead(selectedDirectory.schema || [], selectedDirectory);
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

        function renderDirectoryValueFieldControl(field, value = '', imageUrl = '', removeImage = false) {
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
            } else if (field.type === 'template') {
                html += `<input type="text" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" readonly>`;
            } else if (field.type === 'template_list') {
                let templateList = findTemplateListById(field.template_list_id);
                html += `<select class="form-select directory-value-field directory-template-list-selector" data-key="${field.key}" ${required}>`;
                html += `<option value="">Выберите вариант</option>`;

                (templateList?.items || []).forEach(function (item) {
                    let selected = String(value) === String(item.key) ? 'selected' : '';
                    html += `<option value="${escapeHtml(item.key)}" ${selected}>${escapeHtml(item.name)}</option>`;
                });

                html += `</select>`;
            } else if (field.type === 'directory' || field.type === 'parent') {
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
            } else if (field.type === 'image') {
                html += `
                    <input type="hidden" class="directory-value-image-remove-flag" data-key="${field.key}" value="${removeImage ? 1 : 0}">
                    <input type="file"
                           class="form-control directory-value-file-field"
                           data-key="${field.key}"
                           accept=".png,.jpg,.jpeg"
                           ${required}>
                `;

                if (imageUrl) {
                    html += `
                        <div class="mt-2 directory-image-preview" data-key="${field.key}">
                            <a href="${escapeHtml(imageUrl)}" target="_blank">
                                <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(field.label)}" class="img-thumbnail" style="max-width:160px;max-height:160px;">
                            </a>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger clear-directory-image-field" data-key="${field.key}">
                                    Удалить рисунок
                                </button>
                            </div>
                        </div>
                    `;
                }
            } else {
                html += `<input type="text" class="form-control directory-value-field" data-key="${field.key}" value="${escapeHtml(value)}" ${required}>`;
            }

            html += `</div>`;

            return html;
        }

        function renderDirectoryValueForm(item = null, removeImages = {}) {
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

            let sourceData = item && item.data ? item.data : {};
            let runtimeData = buildRuntimeFieldValues(schema, sourceData);
            let html = '';
            let tabs = groupSchemaFieldsByTab(schema);
            let dynamicTabs = collectTemplateListDynamicFields(schema, sourceData);

            if (dynamicTabs.length || tabs.length > 1 || tabs[0].name !== 'Основное') {
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
                dynamicTabs.forEach(function (tab, index) {
                    let targetIndex = tabs.length + index;
                    html += `
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#directory-value-tab-${targetIndex}" role="tab">
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
                        let value = runtimeData[field.key] !== undefined && runtimeData[field.key] !== null
                            ? runtimeData[field.key]
                            : '';
                        let imageUrl = item && item.image_urls && item.image_urls[field.key]
                            ? item.image_urls[field.key]
                            : '';
                        html += renderDirectoryValueFieldControl(field, value, imageUrl, !!removeImages[field.key]);
                    });
                    html += '</div>';
                });

                dynamicTabs.forEach(function (tab, index) {
                    let targetIndex = tabs.length + index;
                    html += `<div class="tab-pane fade" id="directory-value-tab-${targetIndex}" role="tabpanel">`;
                    tab.fields.forEach(function (field) {
                        let value = runtimeData[field.key] !== undefined && runtimeData[field.key] !== null
                            ? runtimeData[field.key]
                            : '';
                        html += renderDirectoryValueFieldControl(field, value, '', false);
                    });
                    html += '</div>';
                });

                html += '</div>';
            } else {
                schema.forEach(function (field) {
                    let value = runtimeData[field.key] !== undefined && runtimeData[field.key] !== null
                        ? runtimeData[field.key]
                        : '';
                    let imageUrl = item && item.image_urls && item.image_urls[field.key]
                        ? item.image_urls[field.key]
                        : '';
                    html += renderDirectoryValueFieldControl(field, value, imageUrl, !!removeImages[field.key]);
                });
            }

            $('#directoryValueDynamicFields').html(html);
            initSearchableSelects(document.getElementById('directoryValueDynamicFields'));
            renderDirectoryValueScripts();
        }

        function fillDirectoryValueForm(item = null) {
            let modalDirectory = getDirectoryValueModalDirectory();
            $('#directoryValueId').val(item ? item.id : '');
            $('#directoryValueCode').val(item ? (item.code || '') : '');
            $('#directoryValueSortOrder').val(item ? (item.sort_order ?? 0) : 0);

            renderDirectoryValueForm(item);

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

                if (field.type === 'image') {
                    return;
                }

                if (field.type === 'directory' || field.type === 'parent') {
                    loadDirectoryValuesForField(field, value);
                }

                $(`.directory-value-field[data-key="${field.key}"]`).val(value);
            });
        }

        function collectDirectoryValuePayload() {
            let modalDirectory = getDirectoryValueModalDirectory();
            let payload = new FormData();
            payload.append('code', $('#directoryValueCode').val());
            payload.append('sort_order', $('#directoryValueSortOrder').val() || 0);

            let schema = modalDirectory ? (modalDirectory.schema || []) : [];

            if (!schema.length) {
                payload.append('value', $('.directory-value-field[data-key="value"]').val() || '');
                return payload;
            }

            $('.directory-value-field').each(function () {
                payload.append(`data[${$(this).data('key')}]`, $(this).val());
            });

            $('.directory-value-file-field').each(function () {
                let file = this.files && this.files[0] ? this.files[0] : null;

                if (file) {
                    payload.append(`data[${$(this).data('key')}]`, file);
                }
            });

            $('.directory-value-image-remove-flag').each(function () {
                payload.append(`image_remove[${$(this).data('key')}]`, $(this).val() || 0);
            });

            return payload;
        }

        function collectDirectoryValuePayloadSnapshot() {
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

            payload.image_remove = {};

            $('.directory-value-image-remove-flag').each(function () {
                payload.image_remove[$(this).data('key')] = $(this).val() || 0;
            });

            return payload;
        }

        function snapshotDirectoryValueModalState() {
            return {
                directory: getDirectoryValueModalDirectory(),
                valueId: $('#directoryValueId').val(),
                title: $('#directoryValueModalTitle').text(),
                submitText: $('#directoryValueSubmitText').text(),
                payload: collectDirectoryValuePayloadSnapshot()
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

            renderDirectoryValueForm(null, snapshot.payload?.image_remove || {});

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

        function renderDirectoryValueScripts() {
            let modalDirectory = getDirectoryValueModalDirectory();
            let scripts = modalDirectory && Array.isArray(modalDirectory.scripts) ? modalDirectory.scripts : [];
            let box = $('#directoryValueScriptsBox');
            let select = $('#directoryValueScriptSelect');

            if (!scripts.length) {
                box.addClass('d-none');
                select.html('<option value="">Выберите JS-код</option>');
                $('#directoryValueScriptDescription').text('');
                return;
            }

            let html = '<option value="">Выберите JS-код</option>';
            scripts.forEach(function (script) {
                html += `<option value="${script.id}">${escapeHtml(script.name)}</option>`;
            });
            select.html(html);
            box.removeClass('d-none');
            $('#directoryValueScriptDescription').text('');
        }

        function runDirectoryValueScript() {
            let modalDirectory = getDirectoryValueModalDirectory();
            let scripts = modalDirectory && Array.isArray(modalDirectory.scripts) ? modalDirectory.scripts : [];
            let scriptId = $('#directoryValueScriptSelect').val();
            let script = scripts.find(function (item) {
                return String(item.id) === String(scriptId);
            });

            if (!script) {
                showToast('Выберите JS-код', 'warning');
                return;
            }

            $('#directoryValueScriptDescription').text(script.description || '');

            try {
                let api = {
                    directory: modalDirectory,
                    recordId: $('#directoryValueId').val() || null,
                    mode: $('#directoryValueId').val() ? 'edit' : 'create',
                    getField(key) {
                        return $(`.directory-value-field[data-key="${key}"]`).val();
                    },
                    setField(key, value) {
                        $(`.directory-value-field[data-key="${key}"]`).val(value).trigger('change');
                    },
                    getAllFields() {
                        let result = {};
                        $('.directory-value-field').each(function () {
                            result[$(this).data('key')] = $(this).val();
                        });
                        return result;
                    },
                    setFields(values) {
                        Object.keys(values || {}).forEach(function (key) {
                            api.setField(key, values[key]);
                        });
                    },
                    showToast(message, type = 'info') {
                        showToast(message, type);
                    }
                };

                let executor = new Function('api', `"use strict"; const {directory, recordId, mode, getField, setField, getAllFields, setFields, showToast} = api; ${script.code}`);
                executor(api);
                showToast(`JS-код «${script.name}» выполнен`, 'success');
            } catch (error) {
                showToast(`Ошибка JS-кода: ${error.message}`, 'danger');
            }
        }

        function initDirectoryValueToolsTab() {
            let modalBody = $('#directoryValueModal .modal-body');

            if (!modalBody.length || modalBody.find('.directory-value-tools-tabs').length) {
                return;
            }

            let codeBlock = $('#directoryValueCode').closest('.mb-3');
            let sortBlock = $('#directoryValueSortOrder').closest('.mb-3');
            let fieldsBlock = $('#directoryValueDynamicFields');
            let scriptsBox = $('#directoryValueScriptsBox');

            let nav = $(`
                <ul class="nav nav-tabs mb-3 directory-value-tools-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#directoryValueMainTab" role="tab">Основное</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#directoryValueToolsTab" role="tab">Инструменты</button>
                    </li>
                </ul>
            `);
            let content = $('<div class="tab-content"></div>');
            let mainTab = $('<div class="tab-pane fade show active" id="directoryValueMainTab" role="tabpanel"></div>');
            let toolsTab = $('<div class="tab-pane fade" id="directoryValueToolsTab" role="tabpanel"></div>');
            toolsTab.append('<div class="text-secondary small mb-3">Здесь можно запускать дополнительные инструменты для заполнения формы.</div>');

            modalBody.append(nav);
            modalBody.append(content);
            content.append(mainTab);
            content.append(toolsTab);
            mainTab.append(codeBlock);
            mainTab.append(sortBlock);
            mainTab.append(fieldsBlock);
            toolsTab.append(scriptsBox);
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
                renderValuesTableHead(selectedDirectory ? (selectedDirectory.schema || []) : [], selectedDirectory);
            renderDirectoryValueFilters(selectedDirectory ? (selectedDirectory.schema || []) : []);
            toggleAddButton();
            loadValues(1);
        });

        $('#searchDirectoriesBtn').on('click', function () {
            loadDirectories();
        });

        $('#toggleDirectoriesSidebarBtn').on('click', function () {
            directoriesSidebarVisible = !directoriesSidebarVisible;
            localStorage.setItem('userDirectoriesSidebarVisible', directoriesSidebarVisible ? '1' : '0');
            updateDirectoriesSidebarLayout();
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

        $('#savedDirectoryFilterSelect').on('change', function () {
            applySavedDirectoryFilter($(this).val(), true);
        });

        $('#openDirectoryFiltersBuilderBtn').on('click', function () {
            if (!selectedDirectory) {
                return;
            }

            window.location.href = `/directories/${selectedDirectory.id}/filters`;
        });

        $(document).on('change keyup', '.directory-value-filter', function (e) {
            if (e.type === 'change' || e.key === 'Enter') {
                loadValues(1);
            }
        });

        $(document).on('change', '.directory-value-filter-operator', function () {
            let key = $(this).data('key');
            let showBetween = $(this).val() === 'between';
            $(`.directory-value-filter-between[data-key="${key}"]`).toggleClass('d-none', !showBetween);

            if (!showBetween) {
                $(`.directory-value-filter-between[data-key="${key}"]`).val('');
            }

            loadValues(1);
        });

        $(document).on('change', '.directory-template-list-filter', function () {
            if (!selectedDirectory) {
                return;
            }

            let values = collectDirectoryValueFilters();
            values[$(this).data('key')] = {
                operator: 'eq',
                value: $(this).val() || '',
                value_to: '',
            };

            renderDirectoryValueFilters(selectedDirectory.schema || [], null, values);
            loadValues(1);
        });

        $(document).on('change', '.directory-parent-filter-mode', function () {
            if (!selectedDirectory) {
                return;
            }

            let key = String($(this).data('key') || '');
            let field = (selectedDirectory.schema || []).find(function (item) {
                return String(item.key || '') === key;
            });

            directoryParentFilterModes[key] = $(this).val() || 'list';

            if (!field) {
                return;
            }

            let currentValue = $(`.directory-value-filter[data-key="${key}"]`).val() || '';
            refreshParentFilterOptions(field, currentValue);
            initSearchableSelects(document.getElementById('directoryValueFilters'));
        });

        $(document).on('click', '#toggleDirectoryFiltersBtn', function () {
            directoryFiltersVisible = !directoryFiltersVisible;
            $('#directoryValueFiltersPanel').toggleClass('d-none', !directoryFiltersVisible);
            updateDirectoryFiltersButton();
        });

        $(document).on('change keyup', '.directory-value-filter, .directory-value-filter-between, .directory-value-filter-operator', function () {
            updateDirectoryFiltersButton();
        });

        $('#resetDirectoryValueFiltersBtn').on('click', function () {
            currentSavedDirectoryFilterId = '';
            $('#savedDirectoryFilterSelect').val('');
            renderDirectoryValueFilters(selectedDirectory ? (selectedDirectory.schema || []) : []);
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

        $(document).on('change', '.directory-template-list-selector', function () {
            let modalDirectory = getDirectoryValueModalDirectory();

            if (!modalDirectory) {
                return;
            }

            let payload = collectDirectoryValuePayloadSnapshot();
            payload.data = payload.data || {};
            payload.data[$(this).data('key')] = $(this).val() || '';

            renderDirectoryValueForm({
                data: payload.data,
                image_urls: {},
            }, payload.image_remove || {});
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
                    processData: false,
                    contentType: false,
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

        $(document).on('click', '.clear-directory-image-field', function () {
            let key = $(this).data('key');
            $(`.directory-value-image-remove-flag[data-key="${key}"]`).val(1);
            $(`.directory-value-file-field[data-key="${key}"]`).val('');
            $(`.directory-image-preview[data-key="${key}"]`).remove();
        });

        $(document).on('change', '.directory-value-file-field', function () {
            let key = $(this).data('key');

            if (this.files && this.files.length) {
                $(`.directory-value-image-remove-flag[data-key="${key}"]`).val(0);
            }
        });

        $('#directoryValueScriptSelect').on('change', function () {
            let modalDirectory = getDirectoryValueModalDirectory();
            let scripts = modalDirectory && Array.isArray(modalDirectory.scripts) ? modalDirectory.scripts : [];
            let script = scripts.find(function (item) {
                return String(item.id) === String($('#directoryValueScriptSelect').val());
            });

            $('#directoryValueScriptDescription').text(script && script.description ? script.description : '');
        });

        $('#runDirectoryValueScriptBtn').on('click', function () {
            runDirectoryValueScript();
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

        toggleAddButton = function () {
            $('#printDirectoryBtn, #printDirectoryBarcodesBtn, #exportDirectoryCsvBtn, #exportDirectoryXmlBtn').prop('disabled', !selectedDirectory);
            $('#toggleDeletedDirectoryValuesBtn').prop('disabled', !selectedDirectory);

            if (canManageDirectoryValues) {
                $('#addDirectoryValueBtn, #importDirectoryBtn').prop('disabled', !selectedDirectory || showDeletedDirectoryValues);
            }

            $('#toggleDeletedDirectoryValuesBtn')
                .toggleClass('btn-outline-danger', !showDeletedDirectoryValues)
                .toggleClass('btn-warning', showDeletedDirectoryValues)
                .html(
                    showDeletedDirectoryValues
                        ? '<i class="bi bi-arrow-counterclockwise"></i> Активные'
                        : '<i class="bi bi-trash3"></i> Удалённые'
                );
        };

        renderValues = function (items) {
            if (!selectedDirectory) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">\u0421\u043f\u0440\u0430\u0432\u043e\u0447\u043d\u0438\u043a \u043d\u0435 \u0432\u044b\u0431\u0440\u0430\u043d</td>
                    </tr>
                `);
                return;
            }

            let schema = selectedDirectory.schema || [];
            let visibleSchema = getVisibleTableSchema(schema);
            let tableSettings = getDirectoryTableSettings(selectedDirectory);
            let totalColumns = getDirectoryValuesTableColumnCount(schema, selectedDirectory);

            if (!items || items.length === 0) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="${totalColumns}" class="text-center text-secondary py-5">
                            ${showDeletedDirectoryValues ? '\u0423\u0434\u0430\u043b\u0451\u043d\u043d\u044b\u0435 \u0437\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b' : '\u0417\u043d\u0430\u0447\u0435\u043d\u0438\u044f \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b'}
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let templateListBlocks = schema.length ? collectTemplateListRowBlocks(schema, item.data || {}) : [];
                html += '<tr>';

                if (visibleSchema.length) {
                    visibleSchema.forEach(function (field) {
                        let value = item.data && item.data[field.key] !== undefined ? item.data[field.key] : '';

                        if ((field.type === 'directory' || field.type === 'parent') && value !== null && value !== '') {
                            let values = directoryValuesCache[field.directory_id] || [];
                            let directoryItem = values.find(function (entry) {
                                return String(entry.id) === String(value);
                            });

                            if (directoryItem) {
                                value = getDirectoryOptionLabel(field, directoryItem);
                            }
                        }

                        if (field.type === 'image') {
                            let imageUrl = item.image_urls && item.image_urls[field.key] ? item.image_urls[field.key] : '';
                            value = imageUrl
                                ? `<a href="${escapeHtml(imageUrl)}" target="_blank"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(field.label)}" class="img-thumbnail" style="max-width:72px;max-height:72px;"></a>`
                                : '<span class="text-secondary">-</span>';
                            html += `<td>${value}</td>`;
                            return;
                        }

                        if (field.type === 'template_list') {
                            let selectedItem = getTemplateListSelectedItem(field, item.data || {});
                            let cellHtml = selectedItem
                                ? `<div class="fw-semibold">${escapeHtml(selectedItem.name)}</div>`
                                : '<span class="text-secondary">-</span>';

                            html += `<td>${cellHtml}</td>`;
                            return;
                        }

                        html += `<td>${value === null || value === '' ? '<span class="text-secondary">-</span>' : escapeHtml(String(value))}</td>`;
                    });
                } else {
                    html += `<td>${escapeHtml(item.value || '')}</td>`;
                }

                if (tableSettings.show_code !== false) {
                    html += `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>`;
                }

                if (tableSettings.show_sort_order !== false) {
                    html += `<td>${escapeHtml(String(item.sort_order ?? 0))}</td>`;
                }

                html += `<td>${escapeHtml(formatDateTime(showDeletedDirectoryValues ? item.deleted_at : item.created_at))}</td>`;

                if (canManageDirectoryValues) {
                    html += `<td class="text-end">`;

                    if (showDeletedDirectoryValues && canDeleteDirectoryValues) {
                        html += `<button class="btn btn-sm btn-outline-success restore-directory-value" data-id="${item.id}"><i class="bi bi-arrow-counterclockwise"></i></button>`;
                    } else {
                        html += `<button class="btn btn-sm btn-outline-info edit-directory-value" data-id="${item.id}"><i class="bi bi-pencil"></i></button>`;
                    }

                    if (!showDeletedDirectoryValues && canDeleteDirectoryValues) {
                        html += ` <button class="btn btn-sm btn-outline-danger delete-directory-value" data-id="${item.id}"><i class="bi bi-trash"></i></button>`;
                    }

                    html += `</td>`;
                }

                html += '</tr>';
                html += renderTemplateListRowBlocks(templateListBlocks, totalColumns);
            });

            $('#directoryValuesBody').html(html);
        };

        loadValues = function (page = 1) {
            if (!selectedDirectory) {
                return;
            }

            currentValuesPage = page;
            let columnCount = getDirectoryValuesTableColumnCount(selectedDirectory.schema || [], selectedDirectory);

            $('#directoryValuesBody').html(`
                <tr>
                    <td colspan="${columnCount}" class="text-center text-secondary py-5">
                        \u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...
                    </td>
                </tr>
            `);

            $.ajax({
                url: `/directories/${selectedDirectory.id}/values`,
                method: "GET",
                data: {
                    page: page,
                    search: $('#directoryValuesSearchInput').val(),
                    filters: collectDirectoryValueFilters(),
                    show_deleted: showDeletedDirectoryValues ? 1 : 0
                },
                success: function (response) {
                    selectedDirectory = response.directory;
                    currentDirectoryValues = response.items || [];
                    directoryValuesCache[selectedDirectory.id] = currentDirectoryValues;
                    renderDirectories(directories);
                    updateSelectedDirectoryInfo();
                    renderValuesTableHead(selectedDirectory.schema || [], selectedDirectory);
                    renderValues(currentDirectoryValues);
                    renderValuesPagination(response.pagination);
                    toggleAddButton();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        };

        if (canDeleteDirectoryValues) {
            $(document).on('click', '.restore-directory-value', function () {
                let id = $(this).data('id');

                $.ajax({
                    url: `/directory-values/${id}/restore`,
                    method: "POST",
                    success: function (response) {
                        showToast(response.message, 'success');
                        loadValues(1);
                    },
                    error: function (xhr) {
                        showAjaxErrors(xhr);
                    }
                });
            });

            $('#toggleDeletedDirectoryValuesBtn').on('click', function () {
                if (!selectedDirectory) {
                    return;
                }

                showDeletedDirectoryValues = !showDeletedDirectoryValues;
                toggleAddButton();
                loadValues(1);
            });
        }

        renderValues = function (items) {
            if (!selectedDirectory) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">\u0421\u043f\u0440\u0430\u0432\u043e\u0447\u043d\u0438\u043a \u043d\u0435 \u0432\u044b\u0431\u0440\u0430\u043d</td>
                    </tr>
                `);
                return;
            }

            let schema = selectedDirectory.schema || [];
            let visibleSchema = getVisibleTableSchema(schema);
            let tableSettings = getDirectoryTableSettings(selectedDirectory);
            let totalColumns = getDirectoryValuesTableColumnCount(schema, selectedDirectory);

            if (!items || items.length === 0) {
                $('#directoryValuesBody').html(`
                    <tr>
                        <td colspan="${totalColumns}" class="text-center text-secondary py-5">
                            ${showDeletedDirectoryValues ? '\u0423\u0434\u0430\u043b\u0451\u043d\u043d\u044b\u0435 \u0437\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b' : '\u0417\u043d\u0430\u0447\u0435\u043d\u0438\u044f \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b'}
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let templateListBlocks = schema.length ? collectTemplateListRowBlocks(schema, item.data || {}) : [];
                html += '<tr>';

                if (visibleSchema.length) {
                    visibleSchema.forEach(function (field) {
                        let value = item.data && item.data[field.key] !== undefined ? item.data[field.key] : '';

                        if ((field.type === 'directory' || field.type === 'parent') && value !== null && value !== '') {
                            let values = directoryValuesCache[field.directory_id] || [];
                            let directoryItem = values.find(function (entry) {
                                return String(entry.id) === String(value);
                            });

                            if (directoryItem) {
                                value = getDirectoryOptionLabel(field, directoryItem);
                            }
                        }

                        if (field.type === 'image') {
                            let imageUrl = item.image_urls && item.image_urls[field.key] ? item.image_urls[field.key] : '';
                            value = imageUrl
                                ? `<a href="${escapeHtml(imageUrl)}" target="_blank"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(field.label)}" class="img-thumbnail" style="max-width:72px;max-height:72px;"></a>`
                                : '<span class="text-secondary">-</span>';
                            html += `<td>${value}</td>`;
                            return;
                        }

                        if (field.type === 'template_list') {
                            let selectedItem = getTemplateListSelectedItem(field, item.data || {});
                            let cellHtml = selectedItem
                                ? `<div class="fw-semibold">${escapeHtml(selectedItem.name)}</div>`
                                : '<span class="text-secondary">-</span>';

                            html += `<td>${cellHtml}</td>`;
                            return;
                        }

                        html += `<td>${value === null || value === '' ? '<span class="text-secondary">-</span>' : escapeHtml(String(value))}</td>`;
                    });
                } else {
                    let meta = [];

                    if (item.created_by_name) {
                        meta.push(`создал: ${escapeHtml(item.created_by_name)}`);
                    }

                    if (item.updated_by_name && item.updated_by_name !== item.created_by_name) {
                        meta.push(`РёР·Рј.: ${escapeHtml(item.updated_by_name)}`);
                    }

                    html += `<td>
                        <div>${escapeHtml(item.value || '')}</div>
                        ${meta.length ? `<div class="text-secondary" style="font-size: 11px; line-height: 1.2;">${meta.join(' • ')}</div>` : ''}
                    </td>`;
                }

                if (tableSettings.show_code !== false) {
                    html += `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>`;
                }

                if (tableSettings.show_sort_order !== false) {
                    html += `<td>${escapeHtml(String(item.sort_order ?? 0))}</td>`;
                }

                html += `<td>${escapeHtml(formatDateTime(showDeletedDirectoryValues ? item.deleted_at : item.created_at))}</td>`;

                if (canManageDirectoryValues) {
                    html += `<td class="text-end">`;

                    if (showDeletedDirectoryValues && canDeleteDirectoryValues) {
                        html += `<button class="btn btn-sm btn-outline-success restore-directory-value" data-id="${item.id}"><i class="bi bi-arrow-counterclockwise"></i></button>`;
                    } else {
                        html += `<button class="btn btn-sm btn-outline-info edit-directory-value" data-id="${item.id}"><i class="bi bi-pencil"></i></button>`;
                    }

                    if (!showDeletedDirectoryValues && canDeleteDirectoryValues) {
                        html += ` <button class="btn btn-sm btn-outline-danger delete-directory-value" data-id="${item.id}"><i class="bi bi-trash"></i></button>`;
                    }

                    html += `</td>`;
                }

                html += '</tr>';
                html += renderTemplateListRowBlocks(templateListBlocks, totalColumns);
            });

            $('#directoryValuesBody').html(html);
        };

        initDirectoryValueToolsTab();
        updateDirectoriesSidebarLayout();
        loadDirectories();
    </script>
@endpush
