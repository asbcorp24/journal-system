@extends('admin.layouts.app')

@section('title', 'Справочники')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Справочники</h2>
            <div class="text-secondary">
                Конструктор справочников, значения и импорт из CSV
            </div>
        </div>

        <button class="btn btn-primary" id="addDirectoryBtn">
            <i class="bi bi-plus-lg"></i>
            Создать справочник
        </button>
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
                                <th>Значений</th>
                                <th class="text-end">Действия</th>
                            </tr>
                            </thead>

                            <tbody id="directoriesTableBody">
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-5">
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
                            <i class="bi bi-list-check" style="font-size: 48px;"></i>
                            <div class="mt-3">
                                Выберите справочник слева, чтобы редактировать его значения
                            </div>
                        </div>
                    </div>

                    <div id="valuesBlock" class="d-none">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold mb-1" id="selectedDirectoryName">Справочник</h5>
                                <div class="text-secondary small" id="selectedDirectoryDescription"></div>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-success btn-sm" id="importCsvBtn">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                    CSV
                                </button>

                                <button class="btn btn-primary btn-sm" id="addValueBtn">
                                    <i class="bi bi-plus-lg"></i>
                                    Значение
                                </button>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-9">
                                <input type="text"
                                       id="valueSearchInput"
                                       class="form-control"
                                       placeholder="Поиск значения">
                            </div>

                            <div class="col-md-3">
                                <button class="btn btn-outline-info w-100" id="valueSearchBtn">
                                    Найти
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Значение</th>
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

    {{-- Модальное окно справочника --}}
    <div class="modal fade" id="directoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="directoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="directoryModalTitle">
                        Создать справочник
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="directoryId" name="id">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Название справочника</label>
                            <input type="text"
                                   name="name"
                                   id="directoryName"
                                   class="form-control"
                                   placeholder="Например: Оборудование">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Код</label>
                            <input type="text"
                                   name="code"
                                   id="directoryCode"
                                   class="form-control"
                                   placeholder="equipment">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Описание</label>
                            <textarea name="description"
                                      id="directoryDescription"
                                      class="form-control"
                                      rows="3"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Доступен подразделениям</label>

                            <select name="division_ids[]"
                                    id="directoryDivisions"
                                    class="form-select"
                                    multiple
                                    size="5">
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}">
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="text-secondary small mt-1">
                                Если ничего не выбрано — справочник считается общим, но в дальнейшем можно ограничить доступ.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Модальное окно значения --}}
    <div class="modal fade" id="valueModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="valueForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="valueModalTitle">
                        Добавить значение
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="valueId" name="id">

                    <div class="mb-3">
                        <label class="form-label">Значение</label>
                        <input type="text"
                               name="value"
                               id="valueName"
                               class="form-control"
                               placeholder="Например: Станок №1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Код</label>
                        <input type="text"
                               name="code"
                               id="valueCode"
                               class="form-control"
                               placeholder="machine_1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Сортировка</label>
                        <input type="number"
                               name="sort_order"
                               id="valueSortOrder"
                               class="form-control"
                               value="0"
                               min="0">
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               name="is_active"
                               id="valueIsActive"
                               value="1"
                               checked>

                        <label class="form-check-label" for="valueIsActive">
                            Активно
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Модальное окно импорта CSV --}}
    <div class="modal fade" id="csvModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="csvForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Импорт CSV
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info">
                        Формат CSV:
                        <br>
                        <code>value;code;sort_order</code>
                        <br>
                        Минимально достаточно первой колонки:
                        <br>
                        <code>Станок №1</code>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CSV-файл</label>
                        <input type="file"
                               name="csv_file"
                               id="csvFile"
                               class="form-control"
                               accept=".csv,.txt">
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
                        <input class="form-check-input"
                               type="checkbox"
                               name="has_header"
                               id="csvHasHeader"
                               value="1"
                               checked>

                        <label class="form-check-label" for="csvHasHeader">
                            Первая строка — заголовки
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-light"
                            data-bs-dismiss="modal">
                        Отмена
                    </button>

                    <button type="submit" class="btn btn-success">
                        Импортировать
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        let directoryModal = new bootstrap.Modal(document.getElementById('directoryModal'));
        let valueModal = new bootstrap.Modal(document.getElementById('valueModal'));
        let csvModal = new bootstrap.Modal(document.getElementById('csvModal'));

        let currentDirectoryPage = 1;
        let currentValuePage = 1;
        let selectedDirectoryId = null;
        let selectedDirectoryData = null;

        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }

            return $('<div>').text(text).html();
        }

        function renderBadgeActive(isActive) {
            if (isActive) {
                return '<span class="badge bg-success">Активно</span>';
            }

            return '<span class="badge bg-danger">Отключено</span>';
        }

        function renderPagination(target, pagination, type) {
            let links = '';

            if (!pagination || pagination.last_page <= 1) {
                $(target).html('');
                return;
            }

            let current = pagination.current_page;
            let last = pagination.last_page;

            links += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link ${type}-page" href="#" data-page="${current - 1}">
                    Назад
                </a>
            </li>
        `;

            let start = Math.max(1, current - 2);
            let end = Math.min(last, current + 2);

            for (let i = start; i <= end; i++) {
                links += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link ${type}-page" href="#" data-page="${i}">
                        ${i}
                    </a>
                </li>
            `;
            }

            links += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <a class="page-link ${type}-page" href="#" data-page="${current + 1}">
                    Вперёд
                </a>
            </li>
        `;

            $(target).html(links);
        }

        function loadDirectories(page = 1) {
            currentDirectoryPage = page;

            $('#directoriesTableBody').html(`
            <tr>
                <td colspan="4" class="text-center text-secondary py-5">
                    Загрузка...
                </td>
            </tr>
        `);

            $.ajax({
                url: "{{ route('admin.directories.list') }}",
                method: "GET",
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
            let html = '';

            if (!items || items.length === 0) {
                html = `
                <tr>
                    <td colspan="4" class="text-center text-secondary py-5">
                        Справочники не найдены
                    </td>
                </tr>
            `;

                $('#directoriesTableBody').html(html);
                return;
            }

            items.forEach(function (item) {
                let activeClass = selectedDirectoryId === item.id ? 'table-active' : '';

                html += `
                <tr class="${activeClass}">
                    <td>${item.id}</td>

                    <td>
                        <div class="fw-semibold">
                            <a href="#" class="text-decoration-none text-info select-directory" data-id="${item.id}">
                                ${escapeHtml(item.name)}
                            </a>
                        </div>

                        <div class="text-secondary small">
                            ${item.code ? escapeHtml(item.code) : 'без кода'}
                        </div>
                    </td>

                    <td>
                        <span class="badge bg-secondary">
                            ${item.values_count}
                        </span>
                    </td>

                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info edit-directory" data-id="${item.id}">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-danger delete-directory" data-id="${item.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            });

            $('#directoriesTableBody').html(html);
        }

        function renderDirectoriesPagination(pagination) {
            let info = pagination.total > 0
                ? `Показано ${pagination.from}–${pagination.to} из ${pagination.total}`
                : 'Нет записей';

            $('#directoriesPaginationInfo').text(info);

            renderPagination('#directoriesPaginationLinks', pagination, 'directory');
        }

        function loadValues(page = 1) {
            if (!selectedDirectoryId) {
                return;
            }

            currentValuePage = page;

            $('#valuesTableBody').html(`
            <tr>
                <td colspan="6" class="text-center text-secondary py-5">
                    Загрузка...
                </td>
            </tr>
        `);

            $.ajax({
                url: "/admin/directories/" + selectedDirectoryId + "/values",
                method: "GET",
                data: {
                    page: page,
                    search: $('#valueSearchInput').val()
                },
                success: function (response) {
                    selectedDirectoryData = response.directory;

                    $('#emptyValuesBlock').addClass('d-none');
                    $('#valuesBlock').removeClass('d-none');

                    $('#selectedDirectoryName').text(response.directory.name);
                    $('#selectedDirectoryDescription').text(response.directory.description || '');

                    renderValues(response.items);
                    renderValuesPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderValues(items) {
            let html = '';

            if (!items || items.length === 0) {
                html = `
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">
                        Значения не найдены
                    </td>
                </tr>
            `;

                $('#valuesTableBody').html(html);
                return;
            }

            items.forEach(function (item) {
                html += `
                <tr>
                    <td>${item.id}</td>

                    <td>
                        ${escapeHtml(item.value)}
                    </td>

                    <td>
                        ${item.code ? escapeHtml(item.code) : '<span class="text-secondary">—</span>'}
                    </td>

                    <td>
                        ${item.sort_order}
                    </td>

                    <td>
                        ${renderBadgeActive(item.is_active)}
                    </td>

                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info edit-value" data-id="${item.id}">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-danger delete-value" data-id="${item.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            });

            $('#valuesTableBody').html(html);
        }

        function renderValuesPagination(pagination) {
            let info = pagination.total > 0
                ? `Показано ${pagination.from}–${pagination.to} из ${pagination.total}`
                : 'Нет записей';

            $('#valuesPaginationInfo').text(info);

            renderPagination('#valuesPaginationLinks', pagination, 'value');
        }

        function clearDirectoryForm() {
            $('#directoryForm')[0].reset();
            $('#directoryId').val('');
            $('#directoryDivisions').val([]);
        }

        function clearValueForm() {
            $('#valueForm')[0].reset();
            $('#valueId').val('');
            $('#valueSortOrder').val(0);
            $('#valueIsActive').prop('checked', true);
        }

        $('#addDirectoryBtn').on('click', function () {
            clearDirectoryForm();

            $('#directoryModalTitle').text('Создать справочник');
            directoryModal.show();
        });

        $('#directoryForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#directoryId').val();

            let url = id
                ? "/admin/directories/" + id
                : "{{ route('admin.directories.store') }}";

            $.ajax({
                url: url,
                method: "POST",
                data: $(this).serialize(),
                success: function (response) {
                    showToast(response.message, 'success');
                    directoryModal.hide();
                    loadDirectories(currentDirectoryPage);

                    if (selectedDirectoryId && parseInt(id) === selectedDirectoryId) {
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
                url: "/admin/directories/" + id,
                method: "GET",
                success: function (response) {
                    let item = response.directory;

                    $('#directoryModalTitle').text('Редактировать справочник');

                    $('#directoryId').val(item.id);
                    $('#directoryName').val(item.name);
                    $('#directoryCode').val(item.code);
                    $('#directoryDescription').val(item.description);
                    $('#directoryDivisions').val(item.division_ids);

                    directoryModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-directory', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить справочник вместе со всеми значениями?')) {
                return;
            }

            $.ajax({
                url: "/admin/directories/" + id,
                method: "DELETE",
                success: function (response) {
                    showToast(response.message, 'success');

                    if (selectedDirectoryId === id) {
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

            loadDirectories(currentDirectoryPage);
            loadValues(1);
        });

        $('#addValueBtn').on('click', function () {
            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            clearValueForm();

            $('#valueModalTitle').text('Добавить значение');
            valueModal.show();
        });

        $('#valueForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            let id = $('#valueId').val();

            let url = id
                ? "/admin/directory-values/" + id
                : "/admin/directories/" + selectedDirectoryId + "/values";

            $.ajax({
                url: url,
                method: "POST",
                data: $(this).serialize(),
                success: function (response) {
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

            clearValueForm();

            $.ajax({
                url: "/admin/directory-values/" + id,
                method: "GET",
                success: function (response) {
                    let item = response.value;

                    $('#valueModalTitle').text('Редактировать значение');

                    $('#valueId').val(item.id);
                    $('#valueName').val(item.value);
                    $('#valueCode').val(item.code);
                    $('#valueSortOrder').val(item.sort_order);
                    $('#valueIsActive').prop('checked', item.is_active);

                    valueModal.show();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $(document).on('click', '.delete-value', function () {
            let id = $(this).data('id');

            if (!confirm('Удалить значение справочника?')) {
                return;
            }

            $.ajax({
                url: "/admin/directory-values/" + id,
                method: "DELETE",
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

        $('#importCsvBtn').on('click', function () {
            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            $('#csvForm')[0].reset();
            $('#csvHasHeader').prop('checked', true);

            csvModal.show();
        });

        $('#csvForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedDirectoryId) {
                showToast('Сначала выберите справочник', 'warning');
                return;
            }

            let formData = new FormData(this);

            $.ajax({
                url: "/admin/directories/" + selectedDirectoryId + "/import-csv",
                method: "POST",
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

        loadDirectories();
    </script>
@endpush
