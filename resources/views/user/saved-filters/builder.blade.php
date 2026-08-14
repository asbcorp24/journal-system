@extends('user.layouts.app')

@section('title', $pageTitle)

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="mb-2">
                <a href="{{ $backUrl }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Назад
                </a>
            </div>
            <h2 class="fw-bold mb-1">{{ $pageTitle }}</h2>
            <div class="text-secondary">{{ $entityTitle }}</div>
        </div>

        <button type="button" class="btn btn-primary" id="createSavedFilterBtn">
            <i class="bi bi-plus-lg"></i>
            Новый фильтр
        </button>
    </div>

    <div class="alert alert-info">
        {{ $entityHelp }}
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">Сохранённые фильтры</div>
                    <div id="savedFiltersList" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <form class="card" id="savedFilterForm">
                <div class="card-body">
                    <input type="hidden" id="savedFilterId">

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label">Название фильтра</label>
                            <input type="text" class="form-control" id="savedFilterName" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Описание</label>
                            <input type="text" class="form-control" id="savedFilterDescription">
                        </div>
                    </div>

                    <div class="fw-semibold mb-2">Поля фильтра</div>
                    <div class="text-secondary small mb-3">
                        Отметьте поля, которые должны показываться в фильтре, и при необходимости задайте стартовые значения.
                    </div>

                    <div id="savedFilterFields" class="d-flex flex-column gap-3"></div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-light" id="resetSavedFilterFormBtn">
                        Очистить
                    </button>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger d-none" id="deleteSavedFilterBtn">
                            Удалить
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Сохранить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const availableFields = @json($availableFields);
        let savedFilters = @json($savedFilters);
        const storeUrl = @json($storeUrl);
        const updateUrlPattern = @json($updateUrlPattern);
        const deleteUrlPattern = @json($deleteUrlPattern);

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }

        function filterUrl(pattern, id) {
            return pattern.replace('__ID__', encodeURIComponent(id));
        }

        function normalizePresetValue(rawValue) {
            if (rawValue && typeof rawValue === 'object' && !Array.isArray(rawValue)) {
                return {
                    operator: rawValue.operator || 'eq',
                    value: rawValue.value || '',
                    value_to: rawValue.value_to || '',
                };
            }

            return {
                operator: 'eq',
                value: rawValue || '',
                value_to: '',
            };
        }

        function renderFilterOperatorControl(field, operator = 'eq') {
            let type = field.type || 'text';
            let options = [];

            if (type === 'number' || type === 'date' || type === 'time' || type === 'calc') {
                options = [
                    { value: 'eq', label: '=' },
                    { value: 'gt', label: '>' },
                    { value: 'lt', label: '<' },
                    { value: 'neq', label: '<>' },
                    { value: 'between', label: 'between' },
                ];
            } else if (type === 'list' || type === 'directory' || type === 'directory_text') {
                options = [
                    { value: 'eq', label: '=' },
                    { value: 'neq', label: '<>' },
                ];
            } else {
                options = [
                    { value: 'contains', label: 'contains' },
                    { value: 'eq', label: '=' },
                    { value: 'neq', label: '<>' },
                ];
            }

            let html = `<select class="form-select saved-filter-field-operator" data-key="${escapeHtml(field.key)}">`;
            options.forEach(function (item) {
                let selected = item.value === operator ? 'selected' : '';
                html += `<option value="${item.value}" ${selected}>${item.label}</option>`;
            });
            html += '</select>';
            return html;
        }

        function renderFilterFieldControl(field, normalizedValue) {
            let value = normalizedValue.value || '';
            if (field.type === 'list' || field.type === 'directory' || field.type === 'directory_text') {
                let html = '<select class="form-select saved-filter-field-value" data-key="' + escapeHtml(field.key) + '">';
                html += '<option value="">Без значения</option>';

                (field.options || []).forEach(function (option) {
                    let selected = String(option.value) === String(value) ? 'selected' : '';
                    html += '<option value="' + escapeHtml(option.value) + '" ' + selected + '>' + escapeHtml(option.label) + '</option>';
                });

                html += '</select>';
                return html;
            }

            if (field.type === 'date') {
                return '<input type="date" class="form-control saved-filter-field-value" data-key="' + escapeHtml(field.key) + '" value="' + escapeHtml(value) + '">';
            }

            if (field.type === 'time') {
                return '<input type="time" class="form-control saved-filter-field-value" data-key="' + escapeHtml(field.key) + '" value="' + escapeHtml(value) + '">';
            }

            if (field.type === 'number' || field.type === 'calc') {
                return '<input type="number" step="any" class="form-control saved-filter-field-value" data-key="' + escapeHtml(field.key) + '" value="' + escapeHtml(value) + '" placeholder="Например 10">';
            }

            return '<input type="text" class="form-control saved-filter-field-value" data-key="' + escapeHtml(field.key) + '" value="' + escapeHtml(value) + '" placeholder="Значение по умолчанию">';
        }

        function renderSavedFiltersList() {
            if (!savedFilters.length) {
                $('#savedFiltersList').html('<div class="text-secondary text-center py-4">Фильтров пока нет</div>');
                return;
            }

            let currentId = $('#savedFilterId').val();
            let html = '';

            savedFilters.forEach(function (filter) {
                let activeClass = String(currentId || '') === String(filter.id) ? 'border-info bg-info bg-opacity-10' : '';
                html += `
                    <button type="button" class="btn text-start border ${activeClass} saved-filter-item" data-id="${filter.id}">
                        <div class="fw-semibold">${escapeHtml(filter.name)}</div>
                        <div class="small text-secondary">${escapeHtml(filter.description || 'Без описания')}</div>
                        <div class="small text-secondary mt-1">Полей: ${(filter.visible_fields || []).length}</div>
                    </button>
                `;
            });

            $('#savedFiltersList').html(html);
        }

        function renderSavedFilterFields(values = {}, visibleFields = []) {
            if (!availableFields.length) {
                $('#savedFilterFields').html('<div class="text-secondary">Для этого раздела нет доступных полей фильтрации.</div>');
                return;
            }

            let visibleMap = {};
            (visibleFields || []).forEach(function (key) {
                visibleMap[String(key)] = true;
            });

            let html = '';

            availableFields.forEach(function (field) {
                let isVisible = !!visibleMap[String(field.key)];
                let normalizedValue = normalizePresetValue(values[field.key] || null);
                html += `
                    <div class="border rounded p-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input saved-filter-field-visible" type="checkbox" data-key="${escapeHtml(field.key)}" id="fieldVisible_${escapeHtml(field.key)}" ${isVisible ? 'checked' : ''}>
                                    <label class="form-check-label" for="fieldVisible_${escapeHtml(field.key)}">${escapeHtml(field.label)}</label>
                                </div>
                                <div class="small text-secondary mt-1">${escapeHtml(field.type)}</div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Режим</label>
                                ${renderFilterOperatorControl(field, normalizedValue.operator || 'eq')}
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Значение по умолчанию</label>
                                ${renderFilterFieldControl(field, normalizedValue)}
                            </div>
                            <div class="col-md-3 saved-filter-between-box ${normalizedValue.operator === 'between' ? '' : 'd-none'}" data-key="${escapeHtml(field.key)}">
                                <label class="form-label small">Второе значение</label>
                                <input type="text" class="form-control saved-filter-field-value-to" data-key="${escapeHtml(field.key)}" value="${escapeHtml(normalizedValue.value_to || '')}" placeholder="До">
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#savedFilterFields').html(html);
            initSearchableSelects(document.getElementById('savedFilterFields'));
        }

        function resetSavedFilterForm() {
            $('#savedFilterId').val('');
            $('#savedFilterName').val('');
            $('#savedFilterDescription').val('');
            $('#deleteSavedFilterBtn').addClass('d-none');
            renderSavedFilterFields({}, []);
            renderSavedFiltersList();
        }

        function fillSavedFilterForm(filter) {
            $('#savedFilterId').val(filter.id || '');
            $('#savedFilterName').val(filter.name || '');
            $('#savedFilterDescription').val(filter.description || '');
            $('#deleteSavedFilterBtn').toggleClass('d-none', !filter.id);
            renderSavedFilterFields(filter.values || {}, filter.visible_fields || []);
            renderSavedFiltersList();
        }

        function collectSavedFilterPayload() {
            let visibleFields = [];
            let values = {};

            $('.saved-filter-field-visible').each(function () {
                if ($(this).is(':checked')) {
                    visibleFields.push(String($(this).data('key')));
                }
            });

            $('.saved-filter-field-value').each(function () {
                let key = String($(this).data('key'));
                let value = $(this).val();
                let operator = $(`.saved-filter-field-operator[data-key="${key}"]`).val() || 'eq';
                let valueTo = $(`.saved-filter-field-value-to[data-key="${key}"]`).val() || '';

                if (value !== null && String(value).trim() !== '') {
                    values[key] = {
                        operator: operator,
                        value: String(value).trim(),
                        value_to: String(valueTo).trim(),
                    };
                }
            });

            return {
                name: $('#savedFilterName').val(),
                description: $('#savedFilterDescription').val(),
                visible_fields: visibleFields,
                values: values,
            };
        }

        $('#createSavedFilterBtn, #resetSavedFilterFormBtn').on('click', function () {
            resetSavedFilterForm();
        });

        $(document).on('click', '.saved-filter-item', function () {
            let id = $(this).data('id');
            let filter = savedFilters.find(function (item) {
                return String(item.id) === String(id);
            });

            if (filter) {
                fillSavedFilterForm(filter);
            }
        });

        $(document).on('change', '.saved-filter-field-operator', function () {
            let key = String($(this).data('key'));
            let isBetween = $(this).val() === 'between';
            $(`.saved-filter-between-box[data-key="${key}"]`).toggleClass('d-none', !isBetween);
        });

        $('#savedFilterForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#savedFilterId').val();
            let url = id ? filterUrl(updateUrlPattern, id) : storeUrl;

            $.ajax({
                url: url,
                method: 'POST',
                data: collectSavedFilterPayload(),
                success: function (response) {
                    let filter = response.filter;
                    let index = savedFilters.findIndex(function (item) {
                        return String(item.id) === String(filter.id);
                    });

                    if (index === -1) {
                        savedFilters.push(filter);
                    } else {
                        savedFilters[index] = filter;
                    }

                    savedFilters.sort(function (a, b) {
                        return String(a.name).localeCompare(String(b.name), 'ru');
                    });

                    fillSavedFilterForm(filter);
                    showToast(response.message, 'success');
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#deleteSavedFilterBtn').on('click', function () {
            let id = $('#savedFilterId').val();

            if (!id) {
                return;
            }

            if (!confirm('Удалить сохранённый фильтр?')) {
                return;
            }

            $.ajax({
                url: filterUrl(deleteUrlPattern, id),
                method: 'DELETE',
                success: function (response) {
                    savedFilters = savedFilters.filter(function (item) {
                        return String(item.id) !== String(id);
                    });
                    resetSavedFilterForm();
                    showToast(response.message, 'success');
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        resetSavedFilterForm();
    </script>
@endpush
