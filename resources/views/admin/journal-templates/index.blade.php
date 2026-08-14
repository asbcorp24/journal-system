@extends($journalTemplatePageLayout ?? 'admin.layouts.app')

@section('title', $journalTemplatePageTitle ?? 'Конструктор журналов')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">{{ $journalTemplatePageTitle ?? 'Конструктор журналов' }}</h2>
            <div class="text-secondary">
                Создание шаблонов журналов и настройка динамических полей
            </div>
        </div>

        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-magic"></i>
                    Мастер создания
                </button>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><button class="dropdown-item journal-preset-btn" type="button" data-preset="warehouse_receipt">Создать журнал прихода</button></li>
                    <li><button class="dropdown-item journal-preset-btn" type="button" data-preset="warehouse_issue">Создать журнал расхода</button></li>
                </ul>
            </div>
            <button class="btn btn-outline-light" id="importTemplateBtn">
                <i class="bi bi-upload"></i>
                Импорт
            </button>
            <button class="btn btn-primary" id="addTemplateBtn">
                <i class="bi bi-plus-lg"></i>
                Создать журнал
            </button>
        </div>
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
                        <th>Автор</th>
                        <th class="text-end">Действия</th>
                        <th>РЎРѕРіР»Р°СЃСѓРµС‚</th>
                    </tr>
                    </thead>

                    <tbody id="templatesTableBody">
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-5">
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

                        <div class="col-md-12">
                            <label class="form-label">РЎРѕРіР»Р°СЃСѓСЋС‰РёР№</label>
                            <select class="form-select" id="templateApproverUserId" name="approver_user_id">
                                <option value="">РќРµ РЅР°Р·РЅР°С‡РµРЅ</option>
                                @foreach($approvers as $approver)
                                    <option value="{{ $approver->id }}">
                                        {{ $approver->name }} / {{ $approver->role }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="text-secondary small mt-1">
                                Р­С‚РѕС‚ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊ СЃРјРѕР¶РµС‚ СЃРѕРіР»Р°СЃРѕРІС‹РІР°С‚СЊ Р·Р°РїРёСЃРё РїРѕ СЌС‚РѕРјСѓ Р¶СѓСЂРЅР°Р»Сѓ, РґР°Р¶Рµ РµСЃР»Рё РѕРЅ РёР· РґСЂСѓРіРѕРіРѕ РїРѕРґСЂР°Р·РґРµР»РµРЅРёСЏ.
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

    <div class="modal fade" id="importTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="importTemplateForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт шаблона журнала</h5>
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
                        Будет создан новый журнал-копия. Подразделения и связанные справочники подтянутся по названию или коду.
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
        let templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
        let importTemplateModal = new bootstrap.Modal(document.getElementById('importTemplateModal'));

        let currentPage = 1;
        let fields = [];
        let fieldIndex = 0;

        const directories = @json($directories);
        const approvers = @json($approvers);
        const journalTemplateRoutes = @json($journalTemplateRoutes);
        const journalTemplateCanModifyUsed = @json($journalTemplateCanModifyUsed ?? true);
        const journalWizardPresets = {
            warehouse_receipt: {
                title: 'Складской журнал: приход',
                name: 'Получение номенклатуры на склад',
                code: 'warehouse_receipt',
                description: 'Приход номенклатуры на склад от поставщика или внутреннего источника',
                schema: [
                    { key: 'receipt_number', label: 'Номер документа', type: 'string', tab: 'Основное', required: true, filterable: true },
                    { key: 'operation_date', label: 'Дата', type: 'date', tab: 'Основное', required: true, filterable: true },
                    { key: 'item', label: 'Номенклатура', type: 'directory', tab: 'Основное', required: true, filterable: true, directory_code: 'warehouse_nomenclature', directory_display_field: 'name' },
                    { key: 'quantity', label: 'Количество', type: 'number', tab: 'Основное', required: true, filterable: false, validation: { min: 0 } },
                    { key: 'source', label: 'Откуда пришло', type: 'directory', tab: 'Основное', required: true, filterable: true, directory_code: 'warehouse_sources', directory_display_field: 'name' }
                ]
            },
            warehouse_issue: {
                title: 'Складской журнал: расход',
                name: 'Выдача номенклатуры со склада',
                code: 'warehouse_issue',
                description: 'Выдача номенклатуры со склада в подразделения и цеха',
                schema: [
                    { key: 'item', label: 'Номенклатура', type: 'directory', tab: 'Основное', required: true, filterable: true, directory_code: 'warehouse_nomenclature', directory_display_field: 'name' },
                    { key: 'item_number', label: 'Номер номенклатуры', type: 'sql', tab: 'Основное', required: true, filterable: true, sql_query: "SELECT json_extract(data, '$.item_number') FROM directory_values WHERE id = :item" },
                    { key: 'unit', label: 'Единица измерения', type: 'sql', tab: 'Основное', required: true, filterable: true, sql_query: "SELECT json_extract(data, '$.unit') FROM directory_values WHERE id = :item" },
                    { key: 'operation_date', label: 'Дата', type: 'date', tab: 'Основное', required: true, filterable: true },
                    { key: 'quantity', label: 'Сколько отпущено', type: 'number', tab: 'Основное', required: true, filterable: false, validation: { min: 0 } },
                    { key: 'workshop', label: 'В какой цех', type: 'directory', tab: 'Основное', required: true, filterable: true, directory_code: 'warehouse_workshops', directory_display_field: 'name' },
                    { key: 'stock_balance', label: 'Сколько осталось на складе', type: 'sql', tab: 'Основное', required: true, filterable: false, sql_query: "SELECT ROUND(\nCOALESCE((\n    SELECT SUM(CAST(json_extract(data, '$.quantity') AS REAL))\n    FROM journal_entries\n    WHERE journal_template_id = (SELECT id FROM journal_templates WHERE code = 'warehouse_receipt' LIMIT 1)\n      AND deleted_at IS NULL\n      AND status != 'rejected'\n      AND CAST(json_extract(data, '$.item') AS INTEGER) = CAST(:item AS INTEGER)\n), 0)\n-\nCOALESCE((\n    SELECT SUM(CAST(json_extract(data, '$.quantity') AS REAL))\n    FROM journal_entries\n    WHERE journal_template_id = (SELECT id FROM journal_templates WHERE code = 'warehouse_issue' LIMIT 1)\n      AND deleted_at IS NULL\n      AND status != 'rejected'\n      AND CAST(json_extract(data, '$.item') AS INTEGER) = CAST(:item AS INTEGER)\n      AND (:entry_id IS NULL OR id != :entry_id)\n), 0)\n- COALESCE(CAST(:quantity AS REAL), 0), 3)" }
                ]
            }
        };

        function journalTemplateRoute(name, id = null) {
            let url = journalTemplateRoutes[name] || '';

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

        function loadTemplates(page = 1) {
            currentPage = page;

            $('#templatesTableBody').html(`
            <tr>
                <td colspan="8" class="text-center text-secondary py-5">
                    Загрузка...
                </td>
            </tr>
        `);

            $.ajax({
                url: journalTemplateRoute('list'),
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
                    <td colspan="9" class="text-center text-secondary py-5">
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

                let entriesCount = Number(item.entries_count || 0);
                let usedDisabled = !journalTemplateCanModifyUsed && entriesCount > 0
                    ? 'disabled title="Нельзя менять шаблон: в журнале уже есть записи"'
                    : '';
                let authorName = item.creator && item.creator.name
                    ? escapeHtml(item.creator.name)
                    : '<span class="text-secondary">Суперадмин</span>';

                let approverName = item.approver && item.approver.name
                    ? escapeHtml(item.approver.name)
                    : '<span class="text-secondary">РќРµ РЅР°Р·РЅР°С‡РµРЅ</span>';

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

                    <td>${authorName}</td>

                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info edit-template" data-id="${item.id}" ${usedDisabled}>
                            <i class="bi bi-pencil"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-success export-template" data-id="${item.id}">
                            <i class="bi bi-download"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-danger delete-template" data-id="${item.id}" ${usedDisabled}>
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>

                    <td>${approverName}</td>
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
            $('#templateApproverUserId').val('');
            $('#templateIsActive').prop('checked', true);

            fields = [];
            fieldIndex = 0;

            renderFields();
        }

        function findDirectoryByCode(code) {
            return directories.find(function (directory) {
                return String(directory.code || '') === String(code || '');
            }) || null;
        }

        function applyJournalPreset(presetKey) {
            let preset = journalWizardPresets[presetKey];

            if (!preset) {
                return;
            }

            clearTemplateForm();
            $('#templateModalTitle').text(preset.title || 'Создать журнал');
            $('#templateName').val(preset.name || '');
            $('#templateCode').val(preset.code || '');
            $('#templateDescription').val(preset.description || '');
            $('#templateIsActive').prop('checked', true);

            fields = [];
            fieldIndex = 0;

            let missingDirectories = [];

            (preset.schema || []).forEach(function (field) {
                let preparedField = Object.assign({}, field);

                if (preparedField.directory_code) {
                    let linkedDirectory = findDirectoryByCode(preparedField.directory_code);

                    if (linkedDirectory) {
                        preparedField.directory_id = linkedDirectory.id;
                    } else {
                        preparedField.directory_id = '';
                        missingDirectories.push(preparedField.directory_code);
                    }

                    delete preparedField.directory_code;
                }

                addField(preparedField);
            });

            if (!(preset.schema || []).length) {
                renderFields();
            }

            templateModal.show();

            if (missingDirectories.length) {
                showToast('Часть связанных справочников не найдена: ' + missingDirectories.join(', '), 'warning');
            }
        }

        function addField(data = null) {
            fieldIndex++;

            fields.push({
                uid: fieldIndex,
                key: data?.key || '',
                label: data?.label || '',
                type: data?.type || 'string',
                tab: data?.tab || '',
                required: data?.required || false,
                filterable: data?.filterable || false,
                directory_id: data?.directory_id || '',
                directory_display_field: data?.directory_display_field || '',
                options: data?.options || [],
                formula: data?.formula || '',
                default_value: data?.default_value || '',
                sql_query: data?.sql_query || '',
                validation: data?.validation || {
                    min: '',
                    max: '',
                    greater_than_field: '',
                    less_than_field: ''
                }
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
                field.tab = $(`${prefix} .field-tab`).val();
                field.required = $(`${prefix} .field-required`).is(':checked');
                field.filterable = $(`${prefix} .field-filterable`).is(':checked');
                field.directory_id = $(`${prefix} .field-directory`).val();
                field.directory_display_field = $(`${prefix} .field-directory-display`).val();
                field.formula = $(`${prefix} .field-formula`).val();
                field.default_value = $(`${prefix} .field-default-value`).val();
                field.sql_query = $(`${prefix} .field-sql-query`).val();
                field.validation = {
                    min: $(`${prefix} .field-validation-min`).val(),
                    max: $(`${prefix} .field-validation-max`).val(),
                    greater_than_field: $(`${prefix} .field-validation-greater`).val(),
                    less_than_field: $(`${prefix} .field-validation-less`).val()
                };

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
                let displayFieldOptions = `<option value="">Выберите поле</option>`;
                let selectedDirectory = directories.find(function (directory) {
                    return String(field.directory_id) === String(directory.id);
                });

                directories.forEach(function (directory) {
                    let selected = String(field.directory_id) === String(directory.id) ? 'selected' : '';

                    directoriesOptions += `
                    <option value="${directory.id}" ${selected}>
                        ${escapeHtml(directory.name)}
                    </option>
                `;
                });

                if (selectedDirectory && selectedDirectory.schema && selectedDirectory.schema.length) {
                    selectedDirectory.schema.forEach(function (directoryField) {
                        let selected = String(field.directory_display_field) === String(directoryField.key) ? 'selected' : '';

                        displayFieldOptions += `
                        <option value="${escapeHtml(directoryField.key)}" ${selected}>
                            ${escapeHtml(directoryField.label)} (${escapeHtml(directoryField.key)})
                        </option>
                    `;
                    });
                }

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

                            <div class="col-md-2">
                                <label class="form-label">Вкладка</label>
                                <input type="text"
                                       class="form-control field-tab"
                                       value="${escapeHtml(field.tab || '')}"
                                       placeholder="Основное">
                            </div>

                            <div class="col-md-2">
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
                                    <option value="hidden" ${field.type === 'hidden' ? 'selected' : ''}>Скрытое</option>
                                    <option value="list" ${field.type === 'list' ? 'selected' : ''}>Список</option>
                                    <option value="directory" ${field.type === 'directory' ? 'selected' : ''}>Справочник ID</option>
                                    <option value="directory_text" ${field.type === 'directory_text' ? 'selected' : ''}>Справочник текстом</option>
                                    <option value="calc" ${field.type === 'calc' ? 'selected' : ''}>Вычисляемое</option>
                                    <option value="sql" ${field.type === 'sql' ? 'selected' : ''}>SQL-запрос</option>
                                </select>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-flex flex-column gap-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input field-required"
                                               type="checkbox"
                                               ${field.required ? 'checked' : ''}>
                                        <label class="form-check-label">
                                            Обязательное
                                        </label>
                                    </div>

                                    <div class="form-check form-switch">
                                        <input class="form-check-input field-filterable"
                                               type="checkbox"
                                               ${field.filterable ? 'checked' : ''}
                                               ${field.type === 'hidden' ? 'disabled' : ''}>
                                        <label class="form-check-label">
                                            Фильтр
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 field-default-block ${field.type === 'hidden' ? '' : 'd-none'}">
                                <label class="form-label">Значение по умолчанию</label>
                                <input type="text"
                                       class="form-control field-default-value"
                                       value="${escapeHtml(field.default_value || '')}"
                                       placeholder="например system">
                                <div class="text-secondary small mt-1">
                                    Это значение сохранится автоматически. В форме ввода поле видно не будет.
                                </div>
                            </div>

                            <div class="col-md-6 field-directory-block ${['directory', 'directory_text'].includes(field.type) ? '' : 'd-none'}">
                                <label class="form-label">Справочник</label>
                                <select class="form-select field-directory">
                                    ${directoriesOptions}
                                </select>
                            </div>

                            <div class="col-md-6 field-directory-display-block ${['directory', 'directory_text'].includes(field.type) ? '' : 'd-none'}">
                                <label class="form-label">Поле для отображения</label>
                                <select class="form-select field-directory-display">
                                    ${displayFieldOptions}
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
         placeholder="Например: count * price">

    <div class="text-secondary small mt-1">
        Используйте ключи других числовых полей и математические операторы:
        <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code>, <code>()</code>.
    </div>

    <div class="alert alert-info mt-2 mb-0 py-2 small">
        <div class="fw-bold mb-1">Примеры формул:</div>

        <div>
            <code>count * price</code>
            — количество × цена
        </div>

        <div>
            <code>good_count + defect_count</code>
            — сумма двух полей
        </div>

        <div>
            <code>(length * width) / 1000</code>
            — расчёт с группировкой
        </div>

        <div>
            <code>total / hours</code>
            — деление одного поля на другое
        </div>
    </div>

    <div class="text-warning small mt-2">
        Важно: в формуле нужно использовать именно <b>ключи полей</b>, например
        <code>count</code>, <code>price</code>, <code>hours</code>, а не русские названия.
    </div>
</div>
<div class="col-md-12 field-sql-block ${field.type === 'sql' ? '' : 'd-none'}">
    <label class="form-label">SQL-запрос</label>
    <textarea class="form-control field-sql-query"
              rows="4"
              placeholder="SELECT value FROM table_name WHERE id = :field_key">${escapeHtml(field.sql_query || '')}</textarea>
    <div class="alert alert-info small mt-2 mb-0">
        <div class="fw-bold mb-1">Подсказка по SQL-полю</div>
        <div>
            Запрос выполняется при сохранении записи, если значение ещё не заполнено. При редактировании уже сохранённое
            значение не пересчитывается автоматически; для этого в записи есть кнопка <b>Пересчитать</b>.
        </div>
        <ul class="mb-2 mt-2 ps-3">
            <li>Нужен один безопасный <code>SELECT</code> без точки с запятой и дополнительных команд.</li>
            <li>Запрос должен вернуть одну колонку из первой строки. Если строк нет, поле сохранится пустым.</li>
            <li>Параметры из полей записи указываются по ключам: <code>:part</code>, <code>:quantity</code>, <code>:operation_date</code>.</li>
            <li>Также доступны системные параметры: <code>:entry_id</code>, <code>:journal_id</code>, <code>:division_id</code>, <code>:user_id</code>.</li>
        </ul>
        <div>
            Пример:
            <code>SELECT name FROM directory_values WHERE id = :part</code>
        </div>
        <div class="text-muted mt-1">
            Если нужен <code>:entry_id</code> для новой записи, сначала сохраните запись, потом нажмите <b>Пересчитать</b>.
        </div>
    </div>
</div>
<div class="col-md-12 field-validation-block ${['number', 'calc'].includes(field.type) ? '' : 'd-none'}">
    <div class="card mt-2">
        <div class="card-body">
            <div class="fw-bold mb-2">
                Ограничения значения
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Не меньше числа</label>
                    <input type="number"
                           step="any"
                           class="form-control field-validation-min"
                           value="${escapeHtml(field.validation?.min || '')}"
                           placeholder="например 0">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Не больше числа</label>
                    <input type="number"
                           step="any"
                           class="form-control field-validation-max"
                           value="${escapeHtml(field.validation?.max || '')}"
                           placeholder="например 100">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Больше другого поля</label>
                    <select class="form-select field-validation-greater">
                        <option value="">Не задано</option>
                        ${fieldKeyOptions(field.validation?.greater_than_field || '', field.uid)}
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Меньше другого поля</label>
                    <select class="form-select field-validation-less">
                        <option value="">Не задано</option>
                        ${fieldKeyOptions(field.validation?.less_than_field || '', field.uid)}
                    </select>
                </div>
            </div>

            <div class="text-secondary small mt-2">
                Сравнение с другим полем работает только если другое поле тоже числовое.
            </div>
        </div>
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
            let schema = [];

            $('.field-card').each(function () {
                let card = $(this);

                let key = card.find('.field-key').val();
                let label = card.find('.field-label').val();
                let type = card.find('.field-type').val();

                let item = {
                    key: key,
                    label: label,
                    type: type,
                    tab: (card.find('.field-tab').val() || '').trim(),
                    required: card.find('.field-required').is(':checked') ? 1 : 0,
                    filterable: card.find('.field-filterable').is(':checked') ? 1 : 0
                };

                if (type === 'directory' || type === 'directory_text') {
                    item.directory_id = card.find('.field-directory').val();
                    item.directory_display_field = card.find('.field-directory-display').val();
                }

                if (type === 'list') {
                    let optionsText = card.find('.field-options').val() || '';

                    item.options = optionsText
                        .split('\n')
                        .map(function (item) {
                            return item.trim();
                        })
                        .filter(function (item) {
                            return item.length > 0;
                        });
                }

                if (type === 'calc') {
                    item.formula = card.find('.field-formula').val() || '';
                }

                if (type === 'hidden') {
                    item.default_value = card.find('.field-default-value').val() || '';
                    item.filterable = 0;
                }

                if (type === 'sql') {
                    item.sql_query = card.find('.field-sql-query').val() || '';
                }

                if (type === 'number' || type === 'calc') {
                    let validation = {};

                    let min = card.find('.field-validation-min').val();
                    let max = card.find('.field-validation-max').val();
                    let greater = card.find('.field-validation-greater').val();
                    let less = card.find('.field-validation-less').val();

                    if (min !== undefined && min !== '') {
                        validation.min = min;
                    }

                    if (max !== undefined && max !== '') {
                        validation.max = max;
                    }

                    if (greater !== undefined && greater !== '') {
                        validation.greater_than_field = greater;
                    }

                    if (less !== undefined && less !== '') {
                        validation.less_than_field = less;
                    }

                    if (Object.keys(validation).length > 0) {
                        item.validation = validation;
                    }
                }

                schema.push(item);
            });

            return schema;
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

        $(document).on('click', '.journal-preset-btn', function () {
            applyJournalPreset($(this).data('preset'));
        });

        $('#importTemplateBtn').on('click', function () {
            $('#importTemplateForm')[0].reset();
            importTemplateModal.show();
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

        $(document).on('change', '.field-type, .field-directory', function () {
            readFieldsFromDom();
            renderFields();
        });

        $(document).on('change blur', '.field-key', function () {
            readFieldsFromDom();
            renderFields();
        });

        $(document).on('input change', '.field-label, .field-tab, .field-required, .field-filterable, .field-directory, .field-directory-display, .field-options, .field-formula, .field-sql-query, .field-validation-min, .field-validation-max, .field-validation-greater, .field-validation-less', function () {
            updateSchemaPreview();
        });

        $('#templateForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#templateId').val();

            let url = id
                ? journalTemplateRoute('template', id)
                : journalTemplateRoute('store');

            let payload = {
                name: $('#templateName').val(),
                code: $('#templateCode').val(),
                description: $('#templateDescription').val(),
                is_active: $('#templateIsActive').is(':checked') ? 1 : 0,
                division_ids: $('#templateDivisions').val() || [],
                approver_user_id: $('#templateApproverUserId').val() || '',
                schema: buildSchemaForSubmit()
            };
            console.log('SCHEMA TO SAVE:', payload.schema);
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
                url: journalTemplateRoute('template', id),
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
                    $('#templateApproverUserId').val(item.approver_user_id || '');

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
                url: journalTemplateRoute('template', id),
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

        $(document).on('click', '.export-template', function () {
            let id = $(this).data('id');
            window.open(journalTemplateRoute('templateExport', id), '_blank');
        });

        $('#importTemplateForm').on('submit', function (e) {
            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: journalTemplateRoute('templateImport'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    showToast(response.message, 'success');
                    importTemplateModal.hide();
                    loadTemplates(1);
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
        function fieldKeyOptions(selectedKey = '', currentUid = null) {
            let html = '';

            fields.forEach(function (field) {
                if (currentUid && field.uid === currentUid) {
                    return;
                }

                if (!['number', 'calc', 'sql', 'directory'].includes(field.type)) {
                    return;
                }

                if (!field.key) {
                    return;
                }

                let selected = String(selectedKey) === String(field.key) ? 'selected' : '';

                html += `
            <option value="${escapeHtml(field.key)}" ${selected}>
                ${escapeHtml(field.label || field.key)}
            </option>
        `;
            });

            return html;
        }
        loadTemplates();
    </script>
@endpush
