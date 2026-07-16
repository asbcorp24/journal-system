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

                        <div class="w-50">
                            <input type="text"
                                   id="directoryValuesSearchInput"
                                   class="form-control"
                                   placeholder="Поиск по значениям">
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
                </div>
            </div>
        </div>
    </div>

    @if(in_array(session('user_role'), ['foreman', 'admin']))
        <div class="modal fade" id="directoryValueModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" id="directoryValueForm">
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
    @endif
@endsection

@push('scripts')
    <script>
        const canManageDirectoryValues = @json(in_array(session('user_role'), ['foreman', 'admin']));
        const canDeleteDirectoryValues = @json(session('user_role') === 'admin');
        let directoryValueModal = canManageDirectoryValues
            ? new bootstrap.Modal(document.getElementById('directoryValueModal'))
            : null;
        let directories = @json($directories);
        let selectedDirectory = null;
        let currentDirectoryValues = [];

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
                        <div class="fw-semibold">${escapeHtml(item.name)}</div>
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
                return;
            }

            $('#selectedDirectoryTitle').text(selectedDirectory.name);
            $('#selectedDirectoryDescription').text(selectedDirectory.description || '');
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
            if (!canManageDirectoryValues) {
                return;
            }

            $('#addDirectoryValueBtn').prop('disabled', !selectedDirectory);
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
                        loadValues();
                    } else {
                        renderValuesTableHead([]);
                        renderValues([]);
                    }
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function loadValues() {
            if (!selectedDirectory) {
                return;
            }

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
                    search: $('#directoryValuesSearchInput').val()
                },
                success: function (response) {
                    selectedDirectory = response.directory;
                    currentDirectoryValues = response.items || [];
                    renderDirectories(directories);
                    updateSelectedDirectoryInfo();
                    renderValuesTableHead(selectedDirectory.schema || []);
                    renderValues(currentDirectoryValues);
                    toggleAddButton();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderDirectoryValueForm() {
            if (!selectedDirectory) {
                $('#directoryValueDynamicFields').html('');
                return;
            }

            let schema = selectedDirectory.schema || [];

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

            schema.forEach(function (field) {
                let required = field.required ? 'required' : '';
                let requiredMark = field.required ? ' <span class="text-danger">*</span>' : '';

                html += `<div class="mb-3">`;
                html += `<label class="form-label">${escapeHtml(field.label)}${requiredMark}</label>`;

                if (field.type === 'number') {
                    html += `<input type="number" step="any" class="form-control directory-value-field" data-key="${field.key}" ${required}>`;
                } else if (field.type === 'date') {
                    html += `<input type="date" class="form-control directory-value-field" data-key="${field.key}" ${required}>`;
                } else if (field.type === 'time') {
                    html += `<input type="time" class="form-control directory-value-field" data-key="${field.key}" ${required}>`;
                } else if (field.type === 'list') {
                    html += `<select class="form-select directory-value-field" data-key="${field.key}" ${required}>`;
                    html += `<option value="">Выберите значение</option>`;

                    (field.options || []).forEach(function (option) {
                        html += `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`;
                    });

                    html += `</select>`;
                } else {
                    html += `<input type="text" class="form-control directory-value-field" data-key="${field.key}" ${required}>`;
                }

                html += `</div>`;
            });

            $('#directoryValueDynamicFields').html(html);
            initSearchableSelects(document.getElementById('directoryValueDynamicFields'));
        }

        function fillDirectoryValueForm(item = null) {
            $('#directoryValueId').val(item ? item.id : '');
            $('#directoryValueCode').val(item ? (item.code || '') : '');
            $('#directoryValueSortOrder').val(item ? (item.sort_order ?? 0) : 0);

            renderDirectoryValueForm();

            if (!item) {
                return;
            }

            let schema = selectedDirectory ? (selectedDirectory.schema || []) : [];

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

                $(`.directory-value-field[data-key="${field.key}"]`).val(value);
            });
        }

        function collectDirectoryValuePayload() {
            let payload = {
                code: $('#directoryValueCode').val(),
                sort_order: $('#directoryValueSortOrder').val() || 0
            };

            let schema = selectedDirectory ? (selectedDirectory.schema || []) : [];

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

        $(document).on('click', '.directory-select-btn', function () {
            let id = $(this).data('id');

            selectedDirectory = directories.find(function (item) {
                return String(item.id) === String(id);
            }) || null;

            renderDirectories(directories);
            updateSelectedDirectoryInfo();
            renderValuesTableHead(selectedDirectory ? (selectedDirectory.schema || []) : []);
            toggleAddButton();
            loadValues();
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
                loadValues();
            }
        });

        if (canManageDirectoryValues) {
            $('#addDirectoryValueBtn').on('click', function () {
                if (!selectedDirectory) {
                    return;
                }

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

                $('#directoryValueModalTitle').text(`Редактировать значение: ${selectedDirectory.name}`);
                $('#directoryValueSubmitText').text('Сохранить изменения');
                $('#directoryValueForm')[0].reset();
                fillDirectoryValueForm(item);
                directoryValueModal.show();
            });

            $('#directoryValueForm').on('submit', function (e) {
                e.preventDefault();

                if (!selectedDirectory) {
                    return;
                }

                let valueId = $('#directoryValueId').val();

                $.ajax({
                    url: valueId
                        ? `/directory-values/${valueId}`
                        : `/directories/${selectedDirectory.id}/values`,
                    method: "POST",
                    data: collectDirectoryValuePayload(),
                    success: function (response) {
                        showToast(response.message, 'success');
                        directoryValueModal.hide();
                        loadValues();
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

        loadDirectories();
    </script>
@endpush
