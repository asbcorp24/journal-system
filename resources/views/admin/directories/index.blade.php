@extends($directoryPageLayout ?? 'admin.layouts.app')

@section('title', $directoryPageTitle ?? 'Справочники')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">{{ $directoryPageTitle ?? 'Справочники' }}</h2>
            <div class="text-secondary">
                Управляйте шаблонами полей, значениями справочников и импортом или экспортом JSON
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" type="button" id="toggleDirectoriesSidebarBtn">
                <i class="bi bi-layout-sidebar-inset"></i>
                Список справочников
            </button>
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
            <button class="btn btn-outline-info" id="manageTemplateListsBtn">
                <i class="bi bi-collection"></i>
                Списки шаблонов
            </button>
            <button class="btn btn-primary" id="addDirectoryBtn">
                <i class="bi bi-plus-lg"></i>
                Создать справочник
            </button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5" id="directoriesSidebarCol">
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

        <div class="col-md-7" id="directoriesContentCol">
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
                                <select id="savedAdminDirectoryFilterSelect" class="form-select form-select-sm" style="min-width: 220px;" disabled>
                                    <option value="">Все поля</option>
                                </select>

                                <button class="btn btn-outline-warning btn-sm" id="openAdminDirectoryFiltersBuilderBtn" disabled>
                                    <i class="bi bi-funnel"></i>
                                    Конструктор
                                </button>

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

                                <button class="btn btn-outline-info btn-sm" id="manageScriptsBtn">
                                    <i class="bi bi-braces"></i>
                                    JS-код
                                </button>

                                <button class="btn btn-outline-danger btn-sm" id="toggleDeletedValuesBtn" disabled>
                                    <i class="bi bi-trash3"></i>
                                    Удалённые
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
                                <tr id="valuesTableHead">
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

                        <div class="col-md-12">
                            <label class="form-label">Колонки таблицы</label>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="directoryShowCode" checked>
                                    <label class="form-check-label" for="directoryShowCode">Код</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="directoryShowSortOrder" checked>
                                    <label class="form-check-label" for="directoryShowSortOrder">Сорт.</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="directoryShowStatus" checked>
                                    <label class="form-check-label" for="directoryShowStatus">Статус</label>
                                </div>
                            </div>
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

                    <div class="alert alert-info mb-3 d-none">
                        <div class="fw-semibold mb-2">Подсказка по типу "Шаблон"</div>
                        <div class="small mb-2">
                            Поле <code>Шаблон</code> собирает текст автоматически из других полей записи.
                        </div>
                        <div class="small mb-1">Синтаксис:</div>
                        <div class="small"><code>@{{name}}</code></div>
                        <div class="small"><code>@{{code}}</code></div>
                        <div class="small mb-2"><code>@{{name}} / @{{code}}</code></div>
                        <div class="small mb-1">Готовые примеры:</div>
                        <div class="small"><code>@{{last_name}} @{{first_name}}</code></div>
                        <div class="small"><code>№ @{{item_number}} - @{{name}}</code></div>
                        <div class="small"><code>@{{warehouse}} / @{{cell}}</code></div>
                        <div class="small"><code>@{{brand}} @{{model}} (@{{year}})</code></div>
                        <div class="small mt-2">
                            Если подставляется поле типа <code>Справочник</code>, система берёт его отображаемое значение.
                        </div>
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
                    <div class="card bg-dark border-secondary mb-3 d-none" id="valueScriptsBox">
                        <div class="card-body py-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label">JS-код</label>
                                    <select class="form-select" id="valueScriptSelect">
                                        <option value="">Выберите JS-код</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-info w-100" id="runValueScriptBtn">
                                        Выполнить
                                    </button>
                                </div>
                            </div>
                            <div class="small text-secondary mt-2" id="valueScriptDescription"></div>
                        </div>
                    </div>
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

    <div class="modal fade" id="directoryScriptsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">JS-код справочника</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-secondary small" id="directoryScriptsHint">Выберите справочник</div>
                        <button type="button" class="btn btn-primary btn-sm" id="addDirectoryScriptBtn">
                            <i class="bi bi-plus-lg"></i>
                            Добавить JS-код
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Сорт.</th>
                                <th>Статус</th>
                                <th class="text-end">Действия</th>
                            </tr>
                            </thead>
                            <tbody id="directoryScriptsTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">Скрипты не загружены</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="directoryScriptModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" id="directoryScriptForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="directoryScriptModalTitle">Добавить JS-код</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="directoryScriptId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Название</label>
                            <input type="text" class="form-control" id="directoryScriptName" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Сорт.</label>
                            <input type="number" class="form-control" id="directoryScriptSortOrder" min="0" value="0">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="directoryScriptIsActive" checked>
                                <label class="form-check-label" for="directoryScriptIsActive">Активен</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Описание</label>
                            <input type="text" class="form-control" id="directoryScriptDescriptionInput">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Код JavaScript</label>
                            <textarea class="form-control font-monospace" id="directoryScriptCode" rows="14" required></textarea>
                            <div class="form-text">
                                Доступно: <code>getField(key)</code>, <code>setField(key, value)</code>, <code>getAllFields()</code>, <code>setFields({...})</code>, <code>showToast(text, type)</code>, <code>directory</code>, <code>recordId</code>, <code>mode</code>.
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
                        Выберите ранее экспортированный JSON-файл. После загрузки система создаст новый шаблон справочника по данным из файла.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Импортировать</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="templateListsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Списки шаблонов</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-secondary small">Варианты выбора с набором простых полей для справочников.</div>
                        <button type="button" class="btn btn-outline-light btn-sm" id="importTemplateListBtn">
                            <i class="bi bi-upload"></i>
                            Import
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="addTemplateListBtn">
                            <i class="bi bi-plus-lg"></i>
                            Добавить список
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Код</th>
                                <th>Вариантов</th>
                                <th>Автор</th>
                                <th class="text-end">Действия</th>
                            </tr>
                            </thead>
                            <tbody id="templateListsTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">Загрузка...</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="templateListEditorModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="templateListEditorForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateListEditorTitle">Добавить список шаблонов</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="templateListId">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Название</label>
                            <input type="text" class="form-control" id="templateListName" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Код</label>
                            <input type="text" class="form-control" id="templateListCode" placeholder="material_set">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Описание</label>
                            <input type="text" class="form-control" id="templateListDescription">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-semibold">Варианты списка</div>
                            <div class="text-secondary small">У каждого варианта своё название и свой набор простых полей.</div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm" id="addTemplateListItemBtn">
                            <i class="bi bi-plus-lg"></i>
                            Добавить вариант
                        </button>
                    </div>
                    <div id="templateListItemsBuilder"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>


    <div class="modal fade" id="templateListImportModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="templateListImportForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Import Template List</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">JSON file</label>
                        <input type="file" class="form-control" name="template_file" accept=".json,application/json" required>
                    </div>
                    <div class="small text-secondary">Supported format: exported template list JSON.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
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
        let directoryScriptsModal = new bootstrap.Modal(document.getElementById('directoryScriptsModal'));
        let directoryScriptModal = new bootstrap.Modal(document.getElementById('directoryScriptModal'));
        let templateListsModal = new bootstrap.Modal(document.getElementById('templateListsModal'));
        let templateListEditorModal = new bootstrap.Modal(document.getElementById('templateListEditorModal'));
        let templateListImportModal = new bootstrap.Modal(document.getElementById('templateListImportModal'));

        let currentDirectoryPage = 1;
        let currentValuePage = 1;
        let currentSavedAdminDirectoryFilterId = '';
        let adminDirectoryFiltersVisible = false;
        let adminDirectoryParentFilterModes = {};
        let directoriesSidebarVisible = localStorage.getItem('adminDirectoriesSidebarVisible') !== '0';
        let showDeletedValues = false;
        let selectedDirectoryId = null;
        let selectedDirectoryData = null;
        let valueModalDirectory = null;
        let valueModalStack = [];
        let schemaFields = [];
        let schemaFieldIndex = 0;
        let currentDirectoryScripts = [];
        let templateLists = @json($templateLists ?? []);
        let templateListItems = [];
        let templateListItemIndex = 0;
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

        function updateDeletedValuesButton() {
            let hasDirectory = !!selectedDirectoryId;

            $('#toggleDeletedValuesBtn')
                .prop('disabled', !hasDirectory)
                .toggleClass('btn-outline-danger', !showDeletedValues)
                .toggleClass('btn-warning', showDeletedValues)
                .html(
                    showDeletedValues
                        ? '<i class="bi bi-arrow-counterclockwise"></i> Активные'
                        : '<i class="bi bi-trash3"></i> Удалённые'
                );

            $('#addValueBtn').prop('disabled', !hasDirectory || showDeletedValues);
        }

        function updateDirectoriesSidebarLayout() {
            $('#directoriesSidebarCol').toggleClass('d-none', !directoriesSidebarVisible);
            $('#directoriesContentCol')
                .toggleClass('col-md-7', directoriesSidebarVisible)
                .toggleClass('col-md-12', !directoriesSidebarVisible);

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

        function buildTemplateListOptions(selectedId = '') {
            let html = '<option value="">Выберите список шаблонов</option>';

            templateLists.forEach(function (item) {
                let selected = String(selectedId || '') === String(item.id) ? 'selected' : '';
                html += `<option value="${item.id}" ${selected}>${escapeHtml(item.name)}</option>`;
            });

            return html;
        }

        function buildTemplateListDataKey(fieldKey, itemKey, subFieldKey) {
            return `${fieldKey}__${itemKey}__${subFieldKey}`;
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

        function getAdminTemplateListSelectedItem(field, data = {}) {
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

        function getAdminTemplateListDisplayLines(field, data = {}) {
            let selectedItem = getAdminTemplateListSelectedItem(field, data);

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

        function stringifyAdminTemplateFieldValue(field, value, data = {}) {
            if (field.type === 'template_list') {
                let parts = [];

                getAdminTemplateListDisplayLines(field, data).forEach(function (line) {
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

        function buildAdminRuntimeFieldValues(schema, data = {}) {
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

                    return stringifyAdminTemplateFieldValue(sourceField, runtimeData[key], runtimeData);
                });
            });

            return runtimeData;
        }

        function collectAdminTemplateListRowBlocks(schema, data = {}) {
            let blocks = [];

            (schema || []).forEach(function (field) {
                if (field.type !== 'template_list') {
                    return;
                }

                if (field.show_in_table === false) {
                    return;
                }

                let selectedItem = getAdminTemplateListSelectedItem(field, data);
                let lines = getAdminTemplateListDisplayLines(field, data);

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

        function renderAdminTemplateListRowBlocks(blocks = [], colspan = 6) {
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

        function getAdminValuesTableColumnCount(settings = null) {
            let tableSettings = normalizeDirectoryTableSettings(settings || getSelectedDirectoryTableSettings());

            return 3
                + (tableSettings.show_code !== false ? 1 : 0)
                + (tableSettings.show_sort_order !== false ? 1 : 0)
                + (tableSettings.show_status !== false ? 1 : 0);
        }

        function buildAdminDirectoryFilterSchema(schema, filterValues = {}) {
            let expanded = [];

            (schema || []).forEach(function (field) {
                expanded.push(field);

                if (field.type !== 'template_list') {
                    return;
                }

                let selectedItem = getAdminTemplateListSelectedItem(field, filterValues);
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

        function resetTemplateListEditorForm() {
            $('#templateListEditorForm')[0].reset();
            $('#templateListId').val('');
            templateListItems = [];
            templateListItemIndex = 0;
            renderTemplateListItemsBuilder();
        }

        function addTemplateListItem(data = null) {
            templateListItemIndex++;
            templateListItems.push({
                uid: templateListItemIndex,
                key: data?.key || '',
                name: data?.name || '',
                fields: Array.isArray(data?.fields) ? data.fields.map(function (field, index) {
                    return {
                        uid: index + 1,
                        key: field.key || '',
                        label: field.label || '',
                        type: field.type || 'text',
                        options: Array.isArray(field.options) ? field.options : [],
                        required: !!field.required,
                    };
                }) : [],
            });
            renderTemplateListItemsBuilder();
        }

        function syncTemplateListItemsFromDom() {
            let items = [];

            $('#templateListItemsBuilder .template-list-item-card').each(function () {
                let card = $(this);
                let fields = [];

                card.find('.template-list-subfield-row').each(function (index) {
                    let row = $(this);
                    fields.push({
                        uid: index + 1,
                        key: (row.find('.template-list-subfield-key').val() || '').trim(),
                        label: (row.find('.template-list-subfield-label').val() || '').trim(),
                        type: row.find('.template-list-subfield-type').val() || 'text',
                        options: (row.find('.template-list-subfield-options').val() || '')
                            .split('\n')
                            .map(item => item.trim())
                            .filter(Boolean),
                        required: row.find('.template-list-subfield-required').is(':checked'),
                    });
                });

                items.push({
                    uid: Number(card.data('uid')),
                    key: (card.find('.template-list-item-key').val() || '').trim(),
                    name: (card.find('.template-list-item-name').val() || '').trim(),
                    fields: fields,
                });
            });

            templateListItems = items;
        }

        function renderTemplateListItemsBuilder() {
            if (!templateListItems.length) {
                $('#templateListItemsBuilder').html('<div class="text-secondary text-center border rounded py-4">Пока нет вариантов</div>');
                return;
            }

            let html = '';

            templateListItems.forEach(function (item) {
                html += `
                    <div class="card border-secondary mb-3 template-list-item-card" data-uid="${item.uid}">
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Название варианта</label>
                                    <input type="text" class="form-control template-list-item-name" value="${escapeHtml(item.name)}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Ключ</label>
                                    <input type="text" class="form-control template-list-item-key" value="${escapeHtml(item.key)}" placeholder="metal">
                                </div>
                                <div class="col-md-5 d-flex align-items-end justify-content-end">
                                    <button type="button" class="btn btn-outline-success btn-sm me-2 add-template-list-subfield">Добавить поле</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-template-list-item">Удалить вариант</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Название поля</th>
                                        <th>Ключ</th>
                                        <th>Тип</th>
                                        <th>Обяз.</th>
                                        <th class="text-end">Действия</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                `;

                (item.fields || []).forEach(function (field) {
                    html += `
                        <tr class="template-list-subfield-row">
                            <td><input type="text" class="form-control form-control-sm template-list-subfield-label" value="${escapeHtml(field.label)}"></td>
                            <td><input type="text" class="form-control form-control-sm template-list-subfield-key" value="${escapeHtml(field.key)}"></td>
                            <td>
                                <select class="form-select form-select-sm template-list-subfield-type">
                                    <option value="text" ${field.type === 'text' ? 'selected' : ''}>Текст</option>
                                    <option value="number" ${field.type === 'number' ? 'selected' : ''}>Число</option>
                                    <option value="date" ${field.type === 'date' ? 'selected' : ''}>Дата</option>
                                    <option value="time" ${field.type === 'time' ? 'selected' : ''}>Время</option>
                                </select>
                            </td>
                            <td><input type="checkbox" class="form-check-input template-list-subfield-required" ${field.required ? 'checked' : ''}></td>
                            <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-template-list-subfield">Удалить</button></td>
                        </tr>
                    `;
                });

                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#templateListItemsBuilder').html(html);
            enhanceTemplateListSubfieldListOptions();
        }

        function enhanceTemplateListSubfieldListOptions() {
            $('#templateListItemsBuilder .template-list-item-card').each(function () {
                let table = $(this).find('table');
                let headRow = table.find('thead tr');

                if (!headRow.find('.template-list-options-head').length) {
                    $('<th class="template-list-options-head">Варианты</th>').insertAfter(headRow.find('th').eq(2));
                }

                table.find('.template-list-subfield-row').each(function () {
                    let row = $(this);
                    let typeSelect = row.find('.template-list-subfield-type');

                    if (!typeSelect.find('option[value="list"]').length) {
                        typeSelect.append('<option value="list">Список</option>');
                    }

                    if (!row.find('.template-list-subfield-options-cell').length) {
                        $('<td class="template-list-subfield-options-cell"><textarea class="form-control form-control-sm template-list-subfield-options d-none" rows="2" placeholder="Один вариант на строку"></textarea></td>')
                            .insertAfter(row.find('td').eq(2));
                    }

                    let optionsTextarea = row.find('.template-list-subfield-options');
                    if (!optionsTextarea.val()) {
                        let rowIndex = row.index();
                        let parentUid = Number(row.closest('.template-list-item-card').data('uid'));
                        let parentItem = templateListItems.find(item => Number(item.uid) === parentUid);
                        let parentField = parentItem && Array.isArray(parentItem.fields) ? parentItem.fields[rowIndex] : null;

                        if (parentField && Array.isArray(parentField.options) && parentField.options.length) {
                            optionsTextarea.val(parentField.options.join('\n'));
                        }
                    }

                    optionsTextarea.toggleClass('d-none', (typeSelect.val() || 'text') !== 'list');
                });
            });
        }

        function collectTemplateListPayload() {
            syncTemplateListItemsFromDom();

            return {
                name: $('#templateListName').val(),
                code: $('#templateListCode').val(),
                description: $('#templateListDescription').val(),
                items: templateListItems.map(function (item) {
                    return {
                        key: item.key,
                        name: item.name,
                        fields: (item.fields || []).map(function (field) {
                            return {
                                key: field.key,
                                label: field.label,
                                type: field.type,
                                options: field.options || [],
                                required: field.required,
                            };
                        }),
                    };
                }),
            };
        }

        function renderTemplateListsTable() {
            let body = $('#templateListsTableBody');

            if (!templateLists.length) {
                body.html('<tr><td colspan="6" class="text-center text-secondary py-4">Списков шаблонов пока нет</td></tr>');
                return;
            }

            let html = '';
            templateLists.forEach(function (item) {
                html += `
                    <tr>
                        <td>${item.id}</td>
                        <td>${escapeHtml(item.name)}</td>
                        <td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>
                        <td>${Array.isArray(item.items) ? item.items.length : 0}</td>
                        <td>${item.creator_name ? escapeHtml(item.creator_name) : '<span class="text-secondary">-</span>'}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-light export-template-list" data-id="${item.id}" title="Export"><i class="bi bi-download"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-info edit-template-list" data-id="${item.id}"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-template-list" data-id="${item.id}"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
            });

            body.html(html);
        }

        function loadTemplateLists() {
            $.ajax({
                url: directoryRoute('templateLists'),
                method: 'GET',
                success: function (response) {
                    templateLists = response.items || [];
                    renderTemplateListsTable();
                    renderSchemaFields();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
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

        function buildAdminParentTreeOptionLabel(field, item, valuesMap) {
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

        function getAdminParentTreeOrderedValues(field, values) {
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
                    label: buildAdminParentTreeOptionLabel(field, item, valuesMap),
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
        function getAdminDirectoryFilterMode(field) {
            if (field.type !== 'parent') {
                return 'list';
            }

            return adminDirectoryParentFilterModes[field.key] || 'list';
        }

        function renderDirectoryFieldOptions(field, selectedValue, mode = 'list') {
            let html = '<option value="">Выберите значение</option>';
            let values = directoryValuesCache[field.directory_id] || [];

            if (field.type === 'parent' && mode === 'tree') {
                getAdminParentTreeOrderedValues(field, values).forEach(function (entry) {
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

        function renderAdminParentFilterSelect(field, selectedValue) {
            let key = escapeHtml(field.key || '');
            let mode = getAdminDirectoryFilterMode(field);
            let html = `<div class="d-grid gap-2">`;
            html += `<select class="form-select form-select-sm value-parent-filter-mode" data-key="${key}">`;
            html += `<option value="list" ${mode === 'list' ? 'selected' : ''}>Список</option>`;
            html += `<option value="tree" ${mode === 'tree' ? 'selected' : ''}>Дерево</option>`;
            html += `</select>`;
            html += `<select class="form-select form-select-sm value-filter-field" data-key="${key}">`;
            html += renderDirectoryFieldOptions(field, selectedValue, mode);
            html += `</select>`;
            html += `</div>`;
            return html;
        }

        function refreshAdminParentFilterOptions(field, selectedValue = '') {
            let mode = getAdminDirectoryFilterMode(field);
            $(`.value-filter-field[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue, mode));
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

                    if (field.type === 'parent') {
                        refreshAdminParentFilterOptions(field, selectedValue);
                    } else {
                        $(`.value-filter-field[data-key="${field.key}"]`).html(renderDirectoryFieldOptions(field, selectedValue));
                    }
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
                        <td colspan="4" class="text-center text-secondary py-5">Справочники РЅРµ найдены</td>
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

        function renderValueFilters(schema, visibleKeys = null, presetValues = {}) {
            if (!schema || !schema.length) {
                $('#valueFiltersCard').addClass('d-none');
                $('#valueFilters').html('');
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
                } else if (field.type === 'parent') {
                    html += renderAdminParentFilterSelect(field, '');
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
            Object.keys(presetValues || {}).forEach(function (key) {
                $(`.value-filter-field[data-key="${key}"]`).val(presetValues[key]);
            });
            initSearchableSelects(document.getElementById('valueFilters'));
        }

        function populateSavedAdminDirectoryFilterSelect() {
            let select = $('#savedAdminDirectoryFilterSelect');
            let filters = selectedDirectoryData && Array.isArray(selectedDirectoryData.filter_presets) ? selectedDirectoryData.filter_presets : [];
            let html = '<option value="">Все поля</option>';

            filters.forEach(function (filter) {
                html += `<option value="${filter.id}">${escapeHtml(filter.name)}</option>`;
            });

            select.html(html);
            select.prop('disabled', !selectedDirectoryData);

            if (currentSavedAdminDirectoryFilterId) {
                select.val(currentSavedAdminDirectoryFilterId);
            }

            $('#openAdminDirectoryFiltersBuilderBtn').prop('disabled', !selectedDirectoryData);
        }

        function ensureAdminDirectoryFiltersPanel() {
            let card = $('#valueFiltersCard');

            if (!card.length) {
                return;
            }

            let header = card.find('.d-flex.justify-content-between.align-items-center.gap-2.mb-2').first();

            if (header.length && !$('#toggleAdminDirectoryFiltersBtn').length) {
                $('<button type="button" class="btn btn-sm btn-outline-light" id="toggleAdminDirectoryFiltersBtn">Показать</button>')
                    .insertBefore($('#resetValueFiltersBtn'));
            }

            let filters = $('#valueFilters');

            if (filters.length && !$('#adminDirectoryFiltersPanel').length) {
                filters.before('<div class="text-secondary small mb-2">Фильтры продолжают работать, даже ° панель скрыта.</div>');
                filters.wrap('<div class="d-none" id="adminDirectoryFiltersPanel"></div>');
            }
        }

        function updateAdminDirectoryFiltersButton() {
            let btn = $('#toggleAdminDirectoryFiltersBtn');

            if (!btn.length) {
                return;
            }

            let activeCount = getActiveAdminDirectoryFiltersCount();
            let label = adminDirectoryFiltersVisible ? 'Скрыть' : 'Показать';
            let badge = activeCount > 0
                ? ` <span class="badge bg-info text-dark ms-1">${activeCount}</span>`
                : '';

            btn.html(`${label}${badge}`);
        }

        function getActiveAdminDirectoryFiltersCount() {
            return Object.keys(collectValueFilters()).length;
        }

        function applySavedAdminDirectoryFilter(filterId, shouldLoad = true) {
            currentSavedAdminDirectoryFilterId = String(filterId || '');
            let filter = selectedDirectoryData && Array.isArray(selectedDirectoryData.filter_presets)
                ? selectedDirectoryData.filter_presets.find(function (item) {
                    return String(item.id) === currentSavedAdminDirectoryFilterId;
                })
                : null;

            if (!selectedDirectoryData) {
                renderValueFilters([]);
            } else if (!filter) {
                renderValueFilters(selectedDirectoryData.schema || []);
            } else {
                renderValueFilters(selectedDirectoryData.schema || [], filter.visible_fields || [], filter.values || {});
            }

            if (shouldLoad && selectedDirectoryId) {
                loadValues(1);
            }
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

        function normalizeAdminDirectoryFilterValue(rawValue) {
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

        function supportsAdvancedAdminDirectoryFilter(field) {
            return ['text', 'number', 'date', 'time', 'directory_text', 'calc', 'template'].includes(field.type);
        }

        function renderAdminDirectoryFilterOperator(field, operator = 'eq') {
            if (!supportsAdvancedAdminDirectoryFilter(field)) {
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

            let html = `<select class="form-select form-select-sm value-filter-operator mb-2" data-key="${escapeHtml(field.key || '')}">`;
            options.forEach(function (item) {
                html += `<option value="${item.value}" ${item.value === operator ? 'selected' : ''}>${item.label}</option>`;
            });
            html += `</select>`;
            return html;
        }

        function renderAdminDirectoryFilterMainControl(field, normalizedValue) {
            let key = escapeHtml(field.key || '');
            let value = normalizedValue.value ?? '';

            if (field.type === 'list') {
                let html = `<select class="form-select form-select-sm value-filter-field" data-key="${key}"><option value="">Все</option>`;
                (field.options || []).forEach(function (option) {
                    let selected = String(option) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'directory') {
                let html = `<select class="form-select form-select-sm value-filter-field" data-key="${key}">`;
                html += renderDirectoryFieldOptions(field, value);
                html += `</select>`;
                loadDirectoryValuesForField(field, value);
                return html;
            }

            if (field.type === 'parent') {
                loadDirectoryValuesForField(field, value);
                return renderAdminParentFilterSelect(field, value);
            }

            if (field.type === 'date') {
                return `<input type="date" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'time') {
                return `<input type="time" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'number' || field.type === 'calc') {
                return `<input type="number" step="any" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
            }

            return `<input type="text" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
        }

        function renderAdminDirectoryFilterSecondControl(field, normalizedValue) {
            if (!supportsAdvancedAdminDirectoryFilter(field)) {
                return '';
            }

            let key = escapeHtml(field.key || '');
            let valueTo = normalizedValue.value_to ?? '';
            let hiddenClass = normalizedValue.operator === 'between' ? '' : ' d-none';

            if (field.type === 'date') {
                return `<input type="date" class="form-control form-control-sm value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
            }

            if (field.type === 'time') {
                return `<input type="time" class="form-control form-control-sm value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
            }

            if (field.type === 'number' || field.type === 'calc') {
                return `<input type="number" step="any" class="form-control form-control-sm value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
            }

            return `<input type="text" class="form-control form-control-sm value-filter-between mt-2${hiddenClass}" data-key="${key}" value="${escapeHtml(String(valueTo || ''))}" placeholder="До">`;
        }

        renderAdminDirectoryFilterMainControl = function (field, normalizedValue) {
            let key = escapeHtml(field.key || '');
            let value = normalizedValue.value ?? '';

            if (field.type === 'list') {
                let html = `<select class="form-select form-select-sm value-filter-field" data-key="${key}"><option value="">Все</option>`;
                (field.options || []).forEach(function (option) {
                    let selected = String(option) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(option)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'template_list') {
                let templateList = findTemplateListById(field.template_list_id);
                let html = `<select class="form-select form-select-sm value-filter-field admin-template-list-filter" data-key="${key}"><option value="">Все варианты</option>`;
                (templateList?.items || []).forEach(function (item) {
                    let selected = String(item.key) === String(value) ? 'selected' : '';
                    html += `<option value="${escapeHtml(item.key)}" ${selected}>${escapeHtml(item.name)}</option>`;
                });
                html += `</select>`;
                return html;
            }

            if (field.type === 'directory') {
                let html = `<select class="form-select form-select-sm value-filter-field" data-key="${key}">`;
                html += renderDirectoryFieldOptions(field, value);
                html += `</select>`;
                loadDirectoryValuesForField(field, value);
                return html;
            }

            if (field.type === 'parent') {
                loadDirectoryValuesForField(field, value);
                return renderAdminParentFilterSelect(field, value);
            }

            if (field.type === 'date') {
                return `<input type="date" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'time') {
                return `<input type="time" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}">`;
            }

            if (field.type === 'number' || field.type === 'calc') {
                return `<input type="number" step="any" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
            }

            return `<input type="text" class="form-control form-control-sm value-filter-field" data-key="${key}" value="${escapeHtml(String(value || ''))}" placeholder="Значение">`;
        };

        renderValueFilters = function (schema, visibleKeys = null, presetValues = {}) {
            if (!schema || !schema.length) {
                $('#valueFiltersCard').addClass('d-none');
                $('#adminDirectoryFiltersPanel').addClass('d-none');
                $('#valueFilters').html('');
                return;
            }

            schema = buildAdminDirectoryFilterSchema(schema, presetValues);

            if (Array.isArray(visibleKeys) && visibleKeys.length) {
                schema = schema.filter(function (field) {
                    return visibleKeys.includes(String(field.key || ''));
                });
            }

            let html = '';

            schema.forEach(function (field) {
                let label = escapeHtml(field.label || field.key || '');
                let normalizedValue = normalizeAdminDirectoryFilterValue(presetValues[field.key]);
                html += `<div class="col-md-4">`;
                html += `<label class="form-label small mb-1">${label}</label>`;
                html += renderAdminDirectoryFilterOperator(field, normalizedValue.operator);
                html += renderAdminDirectoryFilterMainControl(field, normalizedValue);
                html += renderAdminDirectoryFilterSecondControl(field, normalizedValue);
                html += `</div>`;
            });

            $('#valueFilters').html(html);
            ensureAdminDirectoryFiltersPanel();
            $('#valueFiltersCard').removeClass('d-none');
            $('#adminDirectoryFiltersPanel').toggleClass('d-none', !adminDirectoryFiltersVisible);
            updateAdminDirectoryFiltersButton();
            initSearchableSelects(document.getElementById('valueFilters'));
        };

        collectValueFilters = function () {
            let filters = {};

            $('.value-filter-field').each(function () {
                let key = $(this).data('key');
                let value = $(this).val();
                let operatorField = $(`.value-filter-operator[data-key="${key}"]`);
                let betweenField = $(`.value-filter-between[data-key="${key}"]`);
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

        function loadValues(page = 1) {
            if (!selectedDirectoryId) {
                return;
            }

            currentValuePage = page;
            let columnCount = getAdminValuesTableColumnCount();

            $('#valuesTableBody').html(`
                <tr>
                    <td colspan="${columnCount}" class="text-center text-secondary py-5">\u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...</td>
                </tr>
            `);

            $.ajax({
                url: directoryRoute('directoryValues', selectedDirectoryId),
                method: 'GET',
                data: {
                    page: page,
                    search: $('#valueSearchInput').val(),
                    filters: collectValueFilters(),
                    show_deleted: showDeletedValues ? 1 : 0
                },
                success: function (response) {
                    selectedDirectoryData = response.directory;

                    $('#emptyValuesBlock').addClass('d-none');
                    $('#valuesBlock').removeClass('d-none');
                    $('#selectedDirectoryName').text(response.directory.name);
                    $('#selectedDirectoryDescription').text(response.directory.description || '');
                    $('#selectedDirectorySchemaSummary').text(schemaSummary(response.directory.schema || []));
                    renderAdminValuesTableHead(response.directory.table_settings || {});
                    updateDeletedValuesButton();
                    populateSavedAdminDirectoryFilterSelect();
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

        renderRecordPreview = function (item) {
            if (!item.data || Array.isArray(item.data) && item.data.length === 0) {
                return '';
            }

            let lines = [];
            let schema = selectedDirectoryData && selectedDirectoryData.schema ? selectedDirectoryData.schema : [];

            schema.forEach(function (field) {
                if (field.show_in_table === false) {
                    return;
                }

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

                if (field.type === 'image') {
                    value = item.image_urls && item.image_urls[field.key]
                        ? `[изображение] ${item.image_urls[field.key]}`
                        : '-';
                }

                if (field.type === 'template_list') {
                    return;
                }

                lines.push(`${field.label}: ${value}`);
            });

            if (!lines.length) {
                return '';
            }

            return `<div class="text-secondary small mt-1">${escapeHtml(lines.join(' | '))}</div>`;
        };

        function renderValues(items) {
            let tableSettings = getSelectedDirectoryTableSettings();
            let columnCount = getAdminValuesTableColumnCount(tableSettings);

            if (!items || !items.length) {
                $('#valuesTableBody').html(`
                    <tr>
                        <td colspan="${columnCount}" class="text-center text-secondary py-5">\u0417\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b</td>
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
                        ${tableSettings.show_code !== false ? `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>` : ''}
                        ${tableSettings.show_sort_order !== false ? `<td>${item.sort_order}</td>` : ''}
                        ${tableSettings.show_status !== false ? `<td>${renderBadgeActive(item.is_active)}</td>` : ''}
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

        function getDefaultDirectoryTableSettings() {
            return {
                show_code: true,
                show_sort_order: true,
                show_status: true,
            };
        }

        function normalizeDirectoryTableSettings(settings = {}) {
            return Object.assign({}, getDefaultDirectoryTableSettings(), settings || {});
        }

        function collectDirectoryTableSettings() {
            return {
                show_code: $('#directoryShowCode').is(':checked'),
                show_sort_order: $('#directoryShowSortOrder').is(':checked'),
                show_status: $('#directoryShowStatus').is(':checked'),
            };
        }

        function fillDirectoryTableSettings(settings = {}) {
            let normalized = normalizeDirectoryTableSettings(settings);
            $('#directoryShowCode').prop('checked', normalized.show_code !== false);
            $('#directoryShowSortOrder').prop('checked', normalized.show_sort_order !== false);
            $('#directoryShowStatus').prop('checked', normalized.show_status !== false);
        }

        function getSelectedDirectoryTableSettings() {
            return normalizeDirectoryTableSettings(selectedDirectoryData && selectedDirectoryData.table_settings ? selectedDirectoryData.table_settings : {});
        }

        function renderAdminValuesTableHead(settings = null) {
            let tableSettings = normalizeDirectoryTableSettings(settings || {});
            let html = '<th>ID</th>';
            html += '<th>Запись</th>';

            if (tableSettings.show_code !== false) {
                html += '<th>Код</th>';
            }

            if (tableSettings.show_sort_order !== false) {
                html += '<th>Сорт.</th>';
            }

            if (tableSettings.show_status !== false) {
                html += '<th>Статус</th>';
            }

            html += '<th class="text-end">Действия</th>';
            $('#valuesTableHead').html(html);
        }

        function clearDirectoryForm() {
            $('#directoryForm')[0].reset();
            $('#directoryId').val('');
            $('#directoryDivisions').val([]);
            fillDirectoryTableSettings(getDefaultDirectoryTableSettings());
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
            fillDirectoryTableSettings(preset.table_settings || getDefaultDirectoryTableSettings());

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
                show_in_table: data?.show_in_table !== undefined ? !!data?.show_in_table : true,
                auto_generate: !!data?.auto_generate,
                formula: data?.formula || '',
                template: data?.template || '',
                template_list_id: data?.template_list_id || '',
                directory_id: data?.directory_id || '',
                directory_display_field: data?.directory_display_field || '',
                parent_display_field: data?.parent_display_field || '',
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
                let templateListOptions = buildTemplateListOptions(field.template_list_id);
                let parentDisplayOptions = '<option value="">Выберите поле</option>';
                schemaFields.forEach(function (schemaField) {
                    if (!schemaField.key) {
                        return;
                    }

                    let selected = String(schemaField.key) === String(field.parent_display_field || '') ? 'selected' : '';
                    parentDisplayOptions += `<option value="${escapeHtml(schemaField.key)}" ${selected}>${escapeHtml(schemaField.label || schemaField.key)}</option>`;
                });

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
                                        <option value="image" ${field.type === 'image' ? 'selected' : ''}>Рисунок</option>
                                        <option value="directory" ${field.type === 'directory' ? 'selected' : ''}>Справочник</option>
                                        <option value="parent" ${field.type === 'parent' ? 'selected' : ''}>Родитель</option>
                                        <option value="calc" ${field.type === 'calc' ? 'selected' : ''}>Формула</option>
                                        <option value="template" ${field.type === 'template' ? 'selected' : ''}>Шаблон</option>
                                        <option value="template_list" ${field.type === 'template_list' ? 'selected' : ''}>Список шаблонов</option>
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

                                    <div class="form-check mt-4">
                                        <input class="form-check-input schema-show-in-table" type="checkbox" ${field.show_in_table !== false ? 'checked' : ''}>
                                        <label class="form-check-label">В таблице</label>
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
                                    <label class="form-label">Формула</label>
                                    <input type="text" class="form-control schema-formula" value="${escapeHtml(field.formula || '')}" placeholder="price * quantity">
                                    <div class="form-text">Используйте ключи других полей и математику: <code>price * quantity</code>, <code>(width + height) / 2</code>.</div>
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

                                <div class="col-md-6 schema-parent-block ${field.type === 'parent' ? '' : 'd-none'}">
                                    <label class="form-label">Поле для отображения родителя</label>
                                    <select class="form-select schema-parent-display">
                                        ${parentDisplayOptions}
                                    </select>
                                </div>

                                <div class="col-md-6 schema-template-list-block ${field.type === 'template_list' ? '' : 'd-none'}">
                                    <label class="form-label">Список шаблонов</label>
                                    <select class="form-select schema-template-list">
                                        ${templateListOptions}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#schemaBuilder').html(html);
            enhanceTemplateSchemaFields();
            normalizeSchemaBuilderTexts();
            $('#schemaBuilder .schema-type option[value="calc"]').text('Формула');
            $('#schemaBuilder .schema-calc-block .form-label').text('Формула');
            $('#schemaBuilder .schema-calc-block .form-text').html('Используйте ключи других полей и математику: <code>price * quantity</code>, <code>(width + height) / 2</code>.');
        }

        function normalizeSchemaBuilderTexts() {
            $('#schemaBuilder .schema-field-card').each(function () {
                let card = $(this);
                let typeSelect = card.find('.schema-type');

                typeSelect.prev('.form-label').text('Тип');
                card.find('.schema-required').closest('.form-check').find('.form-check-label').text('Обяз.');
                card.find('.schema-unique').closest('.form-check').find('.form-check-label').text('Уник.');
                card.find('.schema-show-in-table').closest('.form-check').find('.form-check-label').text('В таблице');

                const optionLabels = {
                    text: 'Текст',
                    number: 'Число',
                    date: 'Дата',
                    time: 'Время',
                    list: 'Список',
                    qr: 'QR/штрихкод',
                    image: 'Рисунок',
                    directory: 'Справочник',
                    parent: 'Родитель',
                    calc: 'Формула',
                    template: 'Шаблон',
                    template_list: 'Список шаблонов',
                };

                Object.entries(optionLabels).forEach(function ([value, label]) {
                    typeSelect.find(`option[value="${value}"]`).text(label);
                });
            });
        }

        function enhanceTemplateSchemaFields() {
            $('#schemaBuilder .schema-field-card').each(function () {
                let card = $(this);
                let uid = Number(card.data('uid'));
                let field = schemaFields.find(function (item) {
                    return Number(item.uid) === uid;
                }) || {};
                let typeSelect = card.find('.schema-type');

                if (!typeSelect.find('option[value="template"]').length) {
                    typeSelect.append('<option value="template">Шаблон</option>');
                }

                if (!typeSelect.find('option[value="template_list"]').length) {
                    typeSelect.append('<option value="template_list">Список шаблонов</option>');
                }

                if ((field.type || '') === 'template') {
                    typeSelect.val('template');
                }

                if ((field.type || '') === 'template_list') {
                    typeSelect.val('template_list');
                }

                if (!card.find('.schema-template-block').length) {
                    card.find('.schema-calc-block').after(`
                        <div class="col-md-12 schema-template-block d-none">
                            <label class="form-label">Шаблон</label>
                            <input type="text" class="form-control schema-template" placeholder="@{{name}} / @{{code}}">
                            <div class="form-text">Используйте текст и ключи других полей в фигурных скобках: <code>@{{name}}</code>, <code>@{{code}}</code>, <code>@{{name}} / @{{code}}</code>.</div>
                        </div>
                    `);
                }

                card.find('.schema-template').val(field.template || '');
                card.find('.schema-template-block').toggleClass('d-none', typeSelect.val() !== 'template');
                card.find('.schema-template-list-block').toggleClass('d-none', typeSelect.val() !== 'template_list');
                card.find('.schema-parent-block').toggleClass('d-none', typeSelect.val() !== 'parent');
            });

            normalizeSchemaBuilderTexts();
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
                    show_in_table: card.find('.schema-show-in-table').is(':checked'),
                    auto_generate: card.find('.schema-auto-generate').is(':checked'),
                    formula: (card.find('.schema-formula').val() || '').trim(),
                    template: (card.find('.schema-template').val() || '').trim(),
                    template_list_id: card.find('.schema-template-list').val(),
                    directory_id: card.find('.schema-directory').val(),
                    directory_display_field: card.find('.schema-directory-display').val(),
                    parent_display_field: card.find('.schema-parent-display').val(),
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
                    unique: field.unique,
                    show_in_table: field.show_in_table !== false
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

                if (field.type === 'template') {
                    item.template = field.template || '';
                }

                if (field.type === 'template_list') {
                    item.template_list_id = field.template_list_id;
                }

                if (field.type === 'directory' || field.type === 'parent') {
                    item.directory_id = field.directory_id;
                    item.directory_display_field = field.directory_display_field;
                }

                if (field.type === 'parent') {
                    item.parent_display_field = field.parent_display_field;
                }

                return item;
            });

            return {
                name: $('#directoryName').val(),
                code: $('#directoryCode').val(),
                description: $('#directoryDescription').val(),
                division_ids: $('#directoryDivisions').val() || [],
                table_settings: collectDirectoryTableSettings(),
                schema: schema
            };
        }

        function clearValueForm() {
            $('#valueForm')[0].reset();
            $('#valueId').val('');
            $('#valueSortOrder').val(0);
            $('#valueIsActive').prop('checked', true);
            let modalDirectory = getValueModalDirectory();
            renderValueFields({}, modalDirectory ? modalDirectory.schema || [] : [], {}, {});
        }

        function snapshotValueModalState() {
            return {
                title: $('#valueModalTitle').text(),
                valueId: $('#valueId').val(),
                code: $('#valueCode').val(),
                sortOrder: $('#valueSortOrder').val(),
                isActive: $('#valueIsActive').is(':checked'),
                payload: buildValuePayloadSnapshot()
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
                renderValueFields(data, modalDirectory.schema, {}, snapshot.payload?.image_remove || {});
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
            renderValueFields({}, nestedDirectory.schema || [], {}, {});
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

        function renderValueFieldControl(field, value, imageUrl = '', removeImage = false) {
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
            } else if (field.type === 'template') {
                html += `<input type="text" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" readonly>`;
            } else if (field.type === 'qr') {
                let qrRequired = field.auto_generate ? '' : required;
                let placeholder = field.auto_generate ? 'Оставьте пустым для автогенерации' : 'Введите QR/штрихкод';
                html += `
                    <div class="input-group">
                        <input type="text" class="form-control value-data-field" data-key="${field.key}" value="${escapeHtml(value)}" placeholder="${placeholder}" ${qrRequired}>
                        <button type="button" class="btn btn-outline-info generate-qr-value">Сгенерировать</button>
                    </div>
                `;
            } else if (field.type === 'image') {
                html += `
                    <input type="hidden"
                           class="value-image-remove-flag"
                           data-key="${field.key}"
                           value="${removeImage ? 1 : 0}">
                    <input type="file"
                           class="form-control value-file-field"
                           data-key="${field.key}"
                           accept=".png,.jpg,.jpeg"
                           ${required}>
                `;

                if (imageUrl && !removeImage) {
                    html += `
                        <div class="mt-2 value-image-preview" data-key="${field.key}">
                            <a href="${escapeHtml(imageUrl)}" target="_blank">
                                <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(field.label)}" class="img-thumbnail" style="max-width:160px;max-height:160px;">
                            </a>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger clear-value-image-field" data-key="${field.key}">
                                    Удалить рисунок
                                </button>
                            </div>
                        </div>
                    `;
                }
            } else if (field.type === 'directory' || field.type === 'parent') {
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
                            title="Добавить значение РІ связанный справочник"
                        >
                            +
                        </button>
                    </div>
                `;
                loadDirectoryValuesForField(field, value);
            } else if (field.type === 'template_list') {
                let templateList = findTemplateListById(field.template_list_id);
                html += `<select class="form-select value-data-field template-list-selector" data-key="${field.key}" ${required}>`;
                html += `<option value="">Выберите вариант</option>`;

                (templateList?.items || []).forEach(function (item) {
                    let selected = String(value) === String(item.key) ? 'selected' : '';
                    html += `<option value="${escapeHtml(item.key)}" ${selected}>${escapeHtml(item.name)}</option>`;
                });

                html += `</select>`;
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

        function renderValueFields(data = {}, schema = [], imageUrls = {}, removeImages = {}) {
            if (!schema.length) {
                $('#valueDynamicFields').html(`
                    <div class="mb-3">
                        <label class="form-label">Значение</label>
                        <input type="text" class="form-control value-data-field" data-key="value" value="${escapeHtml(data.value || '')}" required>
                    </div>
                `);
                renderValueScripts();
                return;
            }

            let tabs = groupSchemaFieldsByTab(schema);
            let dynamicTabs = collectTemplateListDynamicFields(schema, data);
            let runtimeData = buildAdminRuntimeFieldValues(schema, data);
            let html = '';

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
                        html += renderValueFieldControl(field, runtimeData[field.key] ?? '', imageUrls[field.key] || '', !!removeImages[field.key]);
                    });
                    html += '</div>';
                });

                dynamicTabs.forEach(function (tab, index) {
                    let targetIndex = tabs.length + index;
                    html += `<div class="tab-pane fade" id="directory-value-tab-${targetIndex}" role="tabpanel">`;
                    tab.fields.forEach(function (field) {
                        html += renderValueFieldControl(field, runtimeData[field.key] ?? '', imageUrls[field.key] || '', !!removeImages[field.key]);
                    });
                    html += '</div>';
                });

                html += '</div>';
            } else {
                schema.forEach(function (field) {
                    html += renderValueFieldControl(field, runtimeData[field.key] ?? '', imageUrls[field.key] || '', !!removeImages[field.key]);
                });
            }

            $('#valueDynamicFields').html(html);
            initSearchableSelects(document.getElementById('valueDynamicFields'));
            renderValueScripts();
        }

        function buildValuePayload() {
            let modalDirectory = getValueModalDirectory();
            let schema = modalDirectory && modalDirectory.schema ? modalDirectory.schema : [];
            let payload = new FormData();
            payload.append('code', $('#valueCode').val());
            payload.append('sort_order', $('#valueSortOrder').val());
            payload.append('is_active', $('#valueIsActive').is(':checked') ? 1 : 0);

            if (!schema.length) {
                payload.append('value', $('.value-data-field[data-key="value"]').val());
                return payload;
            }

            $('.value-data-field').each(function () {
                payload.append(`data[${$(this).data('key')}]`, $(this).val());
            });

            $('.value-file-field').each(function () {
                let file = this.files && this.files[0] ? this.files[0] : null;

                if (file) {
                    payload.append(`data[${$(this).data('key')}]`, file);
                }
            });

            $('.value-image-remove-flag').each(function () {
                payload.append(`image_remove[${$(this).data('key')}]`, $(this).val() || 0);
            });

            return payload;
        }

        function buildValuePayloadSnapshot() {
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
            payload.image_remove = {};

            $('.value-image-remove-flag').each(function () {
                payload.image_remove[$(this).data('key')] = $(this).val() || 0;
            });

            return payload;
        }

        function renderValueScripts() {
            let modalDirectory = getValueModalDirectory();
            let scripts = modalDirectory && Array.isArray(modalDirectory.scripts) ? modalDirectory.scripts : [];
            let box = $('#valueScriptsBox');
            let select = $('#valueScriptSelect');

            if (!scripts.length) {
                box.addClass('d-none');
                select.html('<option value="">Выберите JS-код</option>');
                $('#valueScriptDescription').text('');
                return;
            }

            let html = '<option value="">Выберите JS-код</option>';
            scripts.forEach(function (script) {
                html += `<option value="${script.id}">${escapeHtml(script.name)}</option>`;
            });

            select.html(html);
            box.removeClass('d-none');
            $('#valueScriptDescription').text('');
        }

        function runValueScript() {
            let modalDirectory = getValueModalDirectory();
            let scripts = modalDirectory && Array.isArray(modalDirectory.scripts) ? modalDirectory.scripts : [];
            let scriptId = $('#valueScriptSelect').val();
            let script = scripts.find(function (item) {
                return String(item.id) === String(scriptId);
            });

            if (!script) {
                showToast('Выберите JS-код', 'warning');
                return;
            }

            $('#valueScriptDescription').text(script.description || '');

            try {
                let api = {
                    directory: modalDirectory,
                    recordId: $('#valueId').val() || null,
                    mode: $('#valueId').val() ? 'edit' : 'create',
                    getField(key) {
                        return $(`.value-data-field[data-key="${key}"]`).val();
                    },
                    setField(key, value) {
                        $(`.value-data-field[data-key="${key}"]`).val(value).trigger('change');
                    },
                    getAllFields() {
                        let result = {};
                        $('.value-data-field').each(function () {
                            result[$(this).data('key')] = $(this).val();
                        });
                        return result;
                    },
                    setFields(values) {
                        Object.keys(values || {}).forEach(function (key) {
                            $(`.value-data-field[data-key="${key}"]`).val(values[key]).trigger('change');
                        });
                    },
                    showToast(text, type = 'info') {
                        showToast(text, type);
                    },
                };

                let runner = new Function('api', `"use strict"; const { directory, recordId, mode, getField, setField, getAllFields, setFields, showToast } = api; ${script.code}`);
                runner(api);
            } catch (error) {
                console.error(error);
                showToast(`Ошибка JS-кода: ${error.message}`, 'danger');
            }
        }

        function initAdminDirectoryValueToolsTab() {
            let modalBody = $('#valueModal .modal-body');

            if (!modalBody.length || modalBody.find('.admin-directory-value-tools-tabs').length) {
                return;
            }

            let dynamicFields = $('#valueDynamicFields');
            let metaRow = $('#valueCode').closest('.row');
            let scriptsBox = $('#valueScriptsBox');

            let nav = $(`
                <ul class="nav nav-tabs mb-3 admin-directory-value-tools-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#adminDirectoryValueMainTab" role="tab">Основное</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#adminDirectoryValueToolsTab" role="tab">Инструменты</button>
                    </li>
                </ul>
            `);
            let content = $('<div class="tab-content"></div>');
            let mainTab = $('<div class="tab-pane fade show active" id="adminDirectoryValueMainTab" role="tabpanel"></div>');
            let toolsTab = $('<div class="tab-pane fade" id="adminDirectoryValueToolsTab" role="tabpanel"></div>');
            toolsTab.append('<div class="text-secondary small mb-3">Здесь можно запускать JS-код для быстрого заполнения полей.</div>');

            modalBody.append(nav);
            modalBody.append(content);
            content.append(mainTab);
            content.append(toolsTab);
            mainTab.append(dynamicFields);
            mainTab.append(metaRow);
            toolsTab.append(scriptsBox);
        }

        function renderDirectoryScriptsTable() {
            let body = $('#directoryScriptsTableBody');

            if (!currentDirectoryScripts.length) {
                body.html('<tr><td colspan="6" class="text-center text-secondary py-4">Скриптов пока нет</td></tr>');
                return;
            }

            let html = '';

            currentDirectoryScripts.forEach(function (script) {
                html += `
                    <tr>
                        <td>${script.id}</td>
                        <td>${escapeHtml(script.name)}</td>
                        <td>${script.description ? escapeHtml(script.description) : '<span class="text-secondary">-</span>'}</td>
                        <td>${script.sort_order}</td>
                        <td>${renderBadgeActive(script.is_active)}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info edit-directory-script" data-id="${script.id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-directory-script" data-id="${script.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            body.html(html);
        }

        function loadDirectoryScripts() {
            if (!selectedDirectoryId) {
                currentDirectoryScripts = [];
                renderDirectoryScriptsTable();
                return;
            }

            $.ajax({
                url: directoryRoute('directoryScripts', selectedDirectoryId),
                method: 'GET',
                success: function (response) {
                    currentDirectoryScripts = response.items || [];

                    if (selectedDirectoryData) {
                        selectedDirectoryData.scripts = currentDirectoryScripts.slice();
                    }

                    $('#directoryScriptsHint').text(selectedDirectoryData ? `Справочник: ${selectedDirectoryData.name}` : 'Выберите справочник');
                    renderDirectoryScriptsTable();
                    renderValueScripts();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function resetDirectoryScriptForm() {
            $('#directoryScriptForm')[0].reset();
            $('#directoryScriptId').val('');
            $('#directoryScriptSortOrder').val(0);
            $('#directoryScriptIsActive').prop('checked', true);
            $('#directoryScriptCode').val('');
            $('#directoryScriptDescriptionInput').val('');
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
            card.find('.schema-template-block').toggleClass('d-none', $(this).val() !== 'template');
            card.find('.schema-template-list-block').toggleClass('d-none', $(this).val() !== 'template_list');
            card.find('.schema-directory-block').toggleClass('d-none', $(this).val() !== 'directory');
            card.find('.schema-parent-block').toggleClass('d-none', $(this).val() !== 'parent');
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
                    fillDirectoryTableSettings(item.table_settings || {});

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
            currentSavedAdminDirectoryFilterId = '';
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

        $('#manageScriptsBtn').on('click', function () {
            if (!selectedDirectoryData) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            $('#directoryScriptsHint').text(`Справочник: ${selectedDirectoryData.name}`);
            currentDirectoryScripts = Array.isArray(selectedDirectoryData.scripts) ? selectedDirectoryData.scripts.slice() : [];
            renderDirectoryScriptsTable();
            directoryScriptsModal.show();
            loadDirectoryScripts();
        });

        $('#addDirectoryScriptBtn').on('click', function () {
            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            resetDirectoryScriptForm();
            $('#directoryScriptModalTitle').text('Добавить JS-код');
            directoryScriptModal.show();
        });

        $('#importDirectoryTemplateBtn').on('click', function () {
            $('#importDirectoryTemplateForm')[0].reset();
            importDirectoryTemplateModal.show();
        });

        $('#manageTemplateListsBtn').on('click', function () {
            renderTemplateListsTable();
            templateListsModal.show();
            loadTemplateLists();
        });

        $('#addTemplateListBtn').on('click', function () {
            resetTemplateListEditorForm();
            $('#templateListEditorTitle').text('Добавить список шаблонов');
            templateListEditorModal.show();
        });

        $('#importTemplateListBtn').on('click', function () {
            $('#templateListImportForm')[0].reset();
            templateListImportModal.show();
        });

        $('#addTemplateListItemBtn').on('click', function () {
            syncTemplateListItemsFromDom();
            addTemplateListItem();
        });

        $(document).on('click', '.remove-template-list-item', function () {
            syncTemplateListItemsFromDom();
            let uid = Number($(this).closest('.template-list-item-card').data('uid'));
            templateListItems = templateListItems.filter(item => Number(item.uid) !== uid);
            renderTemplateListItemsBuilder();
        });

        $(document).on('click', '.add-template-list-subfield', function () {
            syncTemplateListItemsFromDom();
            let uid = Number($(this).closest('.template-list-item-card').data('uid'));
            let item = templateListItems.find(entry => Number(entry.uid) === uid);
            if (!item) {
                return;
            }
            item.fields = item.fields || [];
            item.fields.push({
                uid: item.fields.length + 1,
                key: '',
                label: '',
                type: 'text',
                options: [],
                required: false,
            });
            renderTemplateListItemsBuilder();
        });

        $(document).on('click', '.remove-template-list-subfield', function () {
            $(this).closest('.template-list-subfield-row').remove();
        });

        $(document).on('change', '.template-list-subfield-type', function () {
            let row = $(this).closest('.template-list-subfield-row');
            let textarea = row.find('.template-list-subfield-options');
            let isList = ($(this).val() || 'text') === 'list';

            textarea.toggleClass('d-none', !isList);

            if (!isList) {
                textarea.val('');
            }
        });

        $(document).on('click', '.edit-template-list', function () {
            let id = $(this).data('id');

            $.ajax({
                url: directoryRoute('templateList', id),
                method: 'GET',
                success: function (response) {
                    let item = response.item || {};
                    resetTemplateListEditorForm();
                    $('#templateListEditorTitle').text('Редактировать список шаблонов');
                    $('#templateListId').val(item.id || '');
                    $('#templateListName').val(item.name || '');
                    $('#templateListCode').val(item.code || '');
                    $('#templateListDescription').val(item.description || '');
                    templateListItems = [];
                    templateListItemIndex = 0;
                    (item.items || []).forEach(function (entry) {
                        addTemplateListItem(entry);
                    });
                    if (!(item.items || []).length) {
                        renderTemplateListItemsBuilder();
                    }
                    templateListEditorModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-template-list', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить список шаблонов?')) {
                return;
            }

            $.ajax({
                url: directoryRoute('templateList', id),
                method: 'DELETE',
                success: function (response) {
                    showToast(response.message, 'success');
                    loadTemplateLists();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.export-template-list', function () {
            let id = $(this).data('id');
            window.open(directoryRoute('templateListExport', id), '_blank');
        });

        $('#templateListEditorForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#templateListId').val();
            let url = id ? directoryRoute('templateList', id) : directoryRoute('templateLists');

            $.ajax({
                url: url,
                method: 'POST',
                data: collectTemplateListPayload(),
                success: function (response) {
                    showToast(response.message, 'success');
                    templateListEditorModal.hide();
                    loadTemplateLists();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#templateListImportForm').on('submit', function (e) {
            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: directoryRoute('templateListsImport'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    showToast(response.message, 'success');
                    templateListImportModal.hide();
                    loadTemplateLists();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
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
                processData: false,
                contentType: false,
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
                        renderValueFields(item.data || {}, selectedDirectoryData.schema, item.image_urls || {});
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

        $(document).on('change', '.template-list-selector', function () {
            let modalDirectory = getValueModalDirectory();
            if (!modalDirectory) {
                return;
            }

            let snapshot = buildValuePayloadSnapshot();
            snapshot.data = snapshot.data || {};
            snapshot.data[$(this).data('key')] = $(this).val() || '';
            renderValueFields(snapshot.data, modalDirectory.schema || [], {}, snapshot.image_remove || {});
        });

        $(document).on('click', '.clear-value-image-field', function () {
            let key = $(this).data('key');
            $(`.value-image-remove-flag[data-key="${key}"]`).val(1);
            $(`.value-file-field[data-key="${key}"]`).val('');
            $(`.value-image-preview[data-key="${key}"]`).remove();
        });

        $(document).on('change', '.value-file-field', function () {
            let key = $(this).data('key');

            if (this.files && this.files.length) {
                $(`.value-image-remove-flag[data-key="${key}"]`).val(0);
            }
        });

        $('#valueScriptSelect').on('change', function () {
            let modalDirectory = getValueModalDirectory();
            let scripts = modalDirectory && Array.isArray(modalDirectory.scripts) ? modalDirectory.scripts : [];
            let script = scripts.find(function (item) {
                return String(item.id) === String($('#valueScriptSelect').val());
            });

            $('#valueScriptDescription').text(script && script.description ? script.description : '');
        });

        $('#runValueScriptBtn').on('click', function () {
            runValueScript();
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

        $('#directoryScriptForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            let scriptId = $('#directoryScriptId').val();
            let url = scriptId
                ? directoryRoute('script', scriptId)
                : directoryRoute('directoryScripts', selectedDirectoryId);

            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    name: $('#directoryScriptName').val(),
                    description: $('#directoryScriptDescriptionInput').val(),
                    code: $('#directoryScriptCode').val(),
                    sort_order: $('#directoryScriptSortOrder').val() || 0,
                    is_active: $('#directoryScriptIsActive').is(':checked') ? 1 : 0,
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    directoryScriptModal.hide();
                    loadDirectoryScripts();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.edit-directory-script', function () {
            let id = $(this).data('id');

            $.ajax({
                url: directoryRoute('script', id),
                method: 'GET',
                success: function (response) {
                    let script = response.script;
                    resetDirectoryScriptForm();
                    $('#directoryScriptModalTitle').text('Редактировать JS-код');
                    $('#directoryScriptId').val(script.id);
                    $('#directoryScriptName').val(script.name || '');
                    $('#directoryScriptDescriptionInput').val(script.description || '');
                    $('#directoryScriptCode').val(script.code || '');
                    $('#directoryScriptSortOrder').val(script.sort_order ?? 0);
                    $('#directoryScriptIsActive').prop('checked', !!script.is_active);
                    directoryScriptModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-directory-script', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить JS-код справочника?')) {
                return;
            }

            $.ajax({
                url: directoryRoute('script', id),
                method: 'DELETE',
                success: function (response) {
                    showToast(response.message, 'success');
                    loadDirectoryScripts();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
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

        $('#toggleDirectoriesSidebarBtn').on('click', function () {
            directoriesSidebarVisible = !directoriesSidebarVisible;
            localStorage.setItem('adminDirectoriesSidebarVisible', directoriesSidebarVisible ? '1' : '0');
            updateDirectoriesSidebarLayout();
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

        $('#savedAdminDirectoryFilterSelect').on('change', function () {
            applySavedAdminDirectoryFilter($(this).val(), true);
        });

        $(document).on('change', '.value-parent-filter-mode', function () {
            if (!selectedDirectoryData) {
                return;
            }

            let key = String($(this).data('key') || '');
            let field = (selectedDirectoryData.schema || []).find(function (item) {
                return String(item.key || '') === key;
            });

            adminDirectoryParentFilterModes[key] = $(this).val() || 'list';

            if (!field) {
                return;
            }

            let currentValue = $(`.value-filter-field[data-key="${key}"]`).val() || '';
            refreshAdminParentFilterOptions(field, currentValue);
            initSearchableSelects(document.getElementById('valueFilters'));
        });

        $('#openAdminDirectoryFiltersBuilderBtn').on('click', function () {
            if (!selectedDirectoryId) {
                return;
            }

            window.location.href = `/admin/directories/${selectedDirectoryId}/filters`;
        });

        $(document).on('change keyup', '.value-filter-field', function (e) {
            if (e.type === 'change' || e.key === 'Enter') {
                loadValues(1);
            }
        });

        $(document).on('change', '.value-filter-operator', function () {
            let key = $(this).data('key');
            let showBetween = $(this).val() === 'between';
            $(`.value-filter-between[data-key="${key}"]`).toggleClass('d-none', !showBetween);

            if (!showBetween) {
                $(`.value-filter-between[data-key="${key}"]`).val('');
            }

            loadValues(1);
        });

        $(document).on('change', '.admin-template-list-filter', function () {
            if (!selectedDirectoryData) {
                return;
            }

            let values = collectValueFilters();
            values[$(this).data('key')] = {
                operator: 'eq',
                value: $(this).val() || '',
                value_to: '',
            };

            renderValueFilters(selectedDirectoryData.schema || [], null, values);
            loadValues(1);
        });

        $(document).on('click', '#toggleAdminDirectoryFiltersBtn', function () {
            adminDirectoryFiltersVisible = !adminDirectoryFiltersVisible;
            $('#adminDirectoryFiltersPanel').toggleClass('d-none', !adminDirectoryFiltersVisible);
            updateAdminDirectoryFiltersButton();
        });

        $(document).on('change keyup', '.value-filter-field, .value-filter-between, .value-filter-operator', function () {
            updateAdminDirectoryFiltersButton();
        });

        $('#resetValueFiltersBtn').on('click', function () {
            currentSavedAdminDirectoryFilterId = '';
            $('#savedAdminDirectoryFilterSelect').val('');
            renderValueFilters(selectedDirectoryData ? (selectedDirectoryData.schema || []) : []);
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

        function renderAdminDirectoryValuesWithDeleted(items) {
            let tableSettings = getSelectedDirectoryTableSettings();
            let columnCount = getAdminValuesTableColumnCount(tableSettings);

            if (!items || !items.length) {
                $('#valuesTableBody').html(`
                    <tr>
                        <td colspan="${columnCount}" class="text-center text-secondary py-5">${showDeletedValues ? '\u0423\u0434\u0430\u043b\u0451\u043d\u043d\u044b\u0435 \u0437\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b' : '\u0417\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b'}</td>
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
                            ${showDeletedValues ? `<div class="text-secondary small mt-1">Удалено: ${escapeHtml(item.deleted_at || '-')} ${item.deleted_by_name ? '• ' + escapeHtml(item.deleted_by_name) : ''}</div>` : ''}
                        </td>
                        ${tableSettings.show_code !== false ? `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>` : ''}
                        ${tableSettings.show_sort_order !== false ? `<td>${item.sort_order}</td>` : ''}
                        ${tableSettings.show_status !== false ? `<td>${showDeletedValues ? '<span class="badge text-bg-danger">\u0423\u0434\u0430\u043b\u0435\u043d\u0430</span>' : renderBadgeActive(item.is_active)}</td>` : ''}
                        <td class="text-end">
                            ${showDeletedValues
                                ? `<button class="btn btn-sm btn-outline-success restore-value" data-id="${item.id}" title="Восстановить"><i class="bi bi-arrow-counterclockwise"></i></button>`
                                : `<button class="btn btn-sm btn-outline-info edit-value" data-id="${item.id}"><i class="bi bi-pencil"></i></button>
                                   <button class="btn btn-sm btn-outline-danger delete-value" data-id="${item.id}"><i class="bi bi-trash"></i></button>`}
                        </td>
                    </tr>
                `;
            });

            $('#valuesTableBody').html(html);
        }

        loadValues = function (page = 1) {
            if (!selectedDirectoryId) {
                return;
            }

            currentValuePage = page;
            let columnCount = getAdminValuesTableColumnCount();

            $('#valuesTableBody').html(`
                <tr>
                    <td colspan="${columnCount}" class="text-center text-secondary py-5">\u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...</td>
                </tr>
            `);

            $.ajax({
                url: directoryRoute('directoryValues', selectedDirectoryId),
                method: 'GET',
                data: {
                    page: page,
                    search: $('#valueSearchInput').val(),
                    filters: collectValueFilters(),
                    show_deleted: showDeletedValues ? 1 : 0
                },
                success: function (response) {
                    selectedDirectoryData = response.directory;

                    $('#emptyValuesBlock').addClass('d-none');
                    $('#valuesBlock').removeClass('d-none');
                    $('#selectedDirectoryName').text(response.directory.name);
                    $('#selectedDirectoryDescription').text(response.directory.description || '');
                    $('#selectedDirectorySchemaSummary').text(schemaSummary(response.directory.schema || []));
                    renderAdminValuesTableHead(response.directory.table_settings || {});
                    updateDeletedValuesButton();
                    populateSavedAdminDirectoryFilterSelect();

                    if (!$('#valueFilters').children().length) {
                        renderValueFilters(response.directory.schema || []);
                    }

                    renderAdminDirectoryValuesWithDeleted(response.items || []);
                    renderValuesPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        };

        $(document).on('click', '.restore-value', function () {
            let id = $(this).data('id');

            $.ajax({
                url: directoryRoute('valueRestore', id),
                method: 'POST',
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

        $('#toggleDeletedValuesBtn').on('click', function () {
            if (!selectedDirectoryId) {
                return;
            }

            showDeletedValues = !showDeletedValues;
            updateDeletedValuesButton();
            loadValues(1);
        });

        renderAdminDirectoryValuesWithDeleted = function (items) {
            let tableSettings = getSelectedDirectoryTableSettings();
            let columnCount = getAdminValuesTableColumnCount(tableSettings);

            if (!items || !items.length) {
                $('#valuesTableBody').html(`
                    <tr>
                        <td colspan="${columnCount}" class="text-center text-secondary py-5">${showDeletedValues ? '\u0423\u0434\u0430\u043b\u0451\u043d\u043d\u044b\u0435 \u0437\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b' : '\u0417\u0430\u043f\u0438\u0441\u0438 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b'}</td>
                    </tr>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let templateListBlocks = collectAdminTemplateListRowBlocks(
                    selectedDirectoryData && selectedDirectoryData.schema ? selectedDirectoryData.schema : [],
                    item.data || {}
                );
                let meta = [];

                if (item.created_by_name) {
                    meta.push(`создал: ${escapeHtml(item.created_by_name)}`);
                }

                if (item.updated_by_name && item.updated_by_name !== item.created_by_name) {
                    meta.push(`изм.: ${escapeHtml(item.updated_by_name)}`);
                }

                html += `
                    <tr>
                        <td>${item.id}</td>
                        <td>
                            <div>${escapeHtml(item.value)}</div>
                            ${meta.length ? `<div class="text-secondary" style="font-size: 11px; line-height: 1.2;">${meta.join(' • ')}</div>` : ''}
                            ${renderRecordPreview(item)}
                            ${showDeletedValues ? `<div class="text-secondary small mt-1">Удалено: ${escapeHtml(item.deleted_at || '-')} ${item.deleted_by_name ? '• ' + escapeHtml(item.deleted_by_name) : ''}</div>` : ''}
                        </td>
                        ${tableSettings.show_code !== false ? `<td>${item.code ? escapeHtml(item.code) : '<span class="text-secondary">-</span>'}</td>` : ''}
                        ${tableSettings.show_sort_order !== false ? `<td>${item.sort_order}</td>` : ''}
                        ${tableSettings.show_status !== false ? `<td>${showDeletedValues ? '<span class="badge text-bg-danger">\u0423\u0434\u0430\u043b\u0435\u043d\u0430</span>' : renderBadgeActive(item.is_active)}</td>` : ''}
                        <td class="text-end">
                            ${showDeletedValues
                                ? `<button class="btn btn-sm btn-outline-success restore-value" data-id="${item.id}" title="Восстановить"><i class="bi bi-arrow-counterclockwise"></i></button>`
                                : `<button class="btn btn-sm btn-outline-info edit-value" data-id="${item.id}"><i class="bi bi-pencil"></i></button>
                                   <button class="btn btn-sm btn-outline-danger delete-value" data-id="${item.id}"><i class="bi bi-trash"></i></button>`}
                        </td>
                    </tr>
                `;
                html += renderAdminTemplateListRowBlocks(templateListBlocks, columnCount);
            });

            $('#valuesTableBody').html(html);
        };

        initAdminDirectoryValueToolsTab();
        updateDirectoriesSidebarLayout();
        renderSchemaFields();
        loadDirectories();
    </script>
@endpush


