@extends('user.layouts.app')

@section('title', 'На проверку')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">На проверку</h2>
            <div class="text-secondary">
                Все записи, ожидающие подтверждения
            </div>
        </div>

        <button class="btn btn-outline-light" id="refreshBtn">
            <i class="bi bi-arrow-clockwise"></i>
            Обновить
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                @if(session('user_role') === 'admin')
                    <div class="col-md-3">
                        <label class="form-label">Подразделение</label>
                        <select id="divisionFilter" class="form-select">
                            <option value="">Моё подразделение</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}">
                                    {{ $division->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label">Дата от</label>
                    <input type="date" id="dateFrom" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Дата до</label>
                    <input type="date" id="dateTo" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Журнал, автор, данные">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-info w-100" id="applyFilters">
                        Применить
                    </button>
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
            <div id="reviewList">
                <div class="text-center text-secondary py-5">
                    Загрузка...
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-secondary" id="paginationInfo"></div>
                <ul class="pagination mb-0" id="paginationLinks"></ul>
            </div>
        </div>
    </div>

    <div class="modal fade" id="entryViewModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Просмотр записи
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="entryDetails">
                        <div class="text-center text-secondary py-4">
                            Загрузка...
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-success" id="modalApproveBtn">
                        <i class="bi bi-check-lg"></i>
                        Подтвердить
                    </button>

                    <button class="btn btn-warning" id="modalRejectBtn">
                        <i class="bi bi-x-lg"></i>
                        Отклонить
                    </button>

                    <button class="btn btn-outline-light" data-bs-dismiss="modal">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        let currentPage = 1;
        let currentEntryId = null;
        let entryViewModal = new bootstrap.Modal(document.getElementById('entryViewModal'));

        function statusBadge(status) {
            if (status === 'approved') {
                return '<span class="badge bg-success">Подтверждено</span>';
            }

            if (status === 'rejected') {
                return '<span class="badge bg-danger">Отклонено</span>';
            }

            return '<span class="badge bg-warning text-dark">На проверке</span>';
        }

        function loadReview(page = 1) {
            currentPage = page;

            $('#reviewList').html(`
            <div class="text-center text-secondary py-5">
                Загрузка...
            </div>
        `);

            $.ajax({
                url: "{{ route('user.review.list') }}",
                method: "GET",
                data: {
                    page: page,
                    division_id: $('#divisionFilter').length ? $('#divisionFilter').val() : '',
                    date_from: $('#dateFrom').val(),
                    date_to: $('#dateTo').val(),
                    search: $('#searchInput').val()
                },
                success: function (response) {
                    renderReviewList(response.items);
                    renderPagination(response.pagination);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderReviewList(items) {
            if (!items || items.length === 0) {
                $('#reviewList').html(`
                <div class="text-center text-secondary py-5">
                    Записей на проверку нет
                </div>
            `);
                return;
            }

            let html = '';

            items.forEach(function (entry) {
                let shortData = '';

                if (entry.data) {
                    let keys = Object.keys(entry.data).slice(0, 4);

                    shortData = keys.map(function (key) {
                        return `<span class="badge bg-secondary me-1">${escapeHtml(key)}: ${escapeHtml(entry.data[key])}</span>`;
                    }).join('');
                }

                html += `
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold fs-5">
                                    ${entry.template ? escapeHtml(entry.template.name) : 'Журнал'}
                                </div>

                                <div class="text-secondary small mb-2">
                                    Запись #${entry.id}
                                    ${entry.entry_date ? ' / ' + escapeHtml(entry.entry_date.substring(0, 10)) : ''}
                                    ${entry.division ? ' / ' + escapeHtml(entry.division.name) : ''}
                                    ${entry.user ? ' / Автор: ' + escapeHtml(entry.user.name) : ''}
                                </div>

                                <div class="mb-2">
                                    ${statusBadge(entry.status)}
                                </div>

                                <div>
                                    ${shortData}
                                </div>

                                ${entry.last_comment ? `
                                    <div class="alert alert-secondary py-2 mt-3 mb-0">
                                        <div class="small text-secondary">Последний комментарий:</div>
                                        ${escapeHtml(entry.last_comment.comment)}
                                    </div>
                                ` : ''}
                            </div>

                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-info view-entry mb-2"
                                        data-id="${entry.id}">
                                    Открыть
                                </button>

                                <br>

                                <button class="btn btn-sm btn-success approve-entry mb-2"
                                        data-id="${entry.id}">
                                    Подтвердить
                                </button>

                                <br>

                                <button class="btn btn-sm btn-warning reject-entry"
                                        data-id="${entry.id}">
                                    Отклонить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            });

            $('#reviewList').html(html);
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

            let html = '';
            let current = pagination.current_page;
            let last = pagination.last_page;

            html += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a href="#" class="page-link review-page" data-page="${current - 1}">
                    Назад
                </a>
            </li>
        `;

            for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
                html += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a href="#" class="page-link review-page" data-page="${i}">
                        ${i}
                    </a>
                </li>
            `;
            }

            html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <a href="#" class="page-link review-page" data-page="${current + 1}">
                    Вперёд
                </a>
            </li>
        `;

            $('#paginationLinks').html(html);
        }

        function getDirectoryText(directoryValues, directoryId, valueId) {
            let list = directoryValues[directoryId] || [];

            let item = list.find(function (value) {
                return String(value.id) === String(valueId);
            });

            return item ? item.value : valueId;
        }

        function renderEntryDetails(entry, schema, directoryValues) {
            let html = `
            <div class="mb-3">
                <h5 class="fw-bold">${entry.template ? escapeHtml(entry.template.name) : 'Журнал'}</h5>
                <div class="text-secondary">
                    Запись #${entry.id}
                    ${entry.entry_date ? ' / ' + escapeHtml(entry.entry_date.substring(0, 10)) : ''}
                    ${entry.user ? ' / Автор: ' + escapeHtml(entry.user.name) : ''}
                    ${entry.division ? ' / ' + escapeHtml(entry.division.name) : ''}
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-bordered align-middle">
                    <tbody>
        `;

            schema.forEach(function (field) {
                let value = entry.data ? entry.data[field.key] : '';

                if (value === null || value === undefined || value === '') {
                    value = '—';
                } else if (field.type === 'directory') {
                    value = getDirectoryText(directoryValues, field.directory_id, value);
                }

                html += `
                <tr>
                    <th style="width: 260px;">${escapeHtml(field.label)}</th>
                    <td>${escapeHtml(value)}</td>
                </tr>
            `;
            });

            html += `
                    </tbody>
                </table>
            </div>
        `;

            if (entry.last_comment) {
                html += `
                <div class="alert alert-secondary">
                    <div class="small text-secondary">Последний комментарий:</div>
                    ${escapeHtml(entry.last_comment.comment)}
                </div>
            `;
            }

            $('#entryDetails').html(html);
        }

        function openEntry(entryId) {
            currentEntryId = entryId;

            $('#entryDetails').html(`
            <div class="text-center text-secondary py-4">
                Загрузка...
            </div>
        `);

            entryViewModal.show();

            $.ajax({
                url: `/review/entries/${entryId}`,
                method: "GET",
                success: function (response) {
                    renderEntryDetails(
                        response.entry,
                        response.schema,
                        response.directory_values
                    );
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function approveEntry(entryId) {
            let comment = prompt('Комментарий к подтверждению. Можно оставить пустым.');

            if (comment === null) {
                return;
            }

            $.ajax({
                url: `/review/entries/${entryId}/approve`,
                method: "POST",
                data: {
                    comment: comment
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    entryViewModal.hide();
                    loadReview(currentPage);
                    loadNotificationsCount();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function rejectEntry(entryId) {
            let comment = prompt('Причина отклонения');

            if (comment === null) {
                return;
            }

            comment = comment.trim();

            if (!comment) {
                showToast('При отклонении нужно указать причину', 'warning');
                return;
            }

            $.ajax({
                url: `/review/entries/${entryId}/reject`,
                method: "POST",
                data: {
                    comment: comment
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    entryViewModal.hide();
                    loadReview(currentPage);
                    loadNotificationsCount();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        $(document).on('click', '.view-entry', function () {
            openEntry($(this).data('id'));
        });

        $(document).on('click', '.approve-entry', function () {
            approveEntry($(this).data('id'));
        });

        $(document).on('click', '.reject-entry', function () {
            rejectEntry($(this).data('id'));
        });

        $('#modalApproveBtn').on('click', function () {
            if (currentEntryId) {
                approveEntry(currentEntryId);
            }
        });

        $('#modalRejectBtn').on('click', function () {
            if (currentEntryId) {
                rejectEntry(currentEntryId);
            }
        });

        $('#applyFilters').on('click', function () {
            loadReview(1);
        });

        $('#resetFilters').on('click', function () {
            $('#dateFrom').val('');
            $('#dateTo').val('');
            $('#searchInput').val('');

            if ($('#divisionFilter').length) {
                $('#divisionFilter').val('');
            }

            loadReview(1);
        });

        $('#refreshBtn').on('click', function () {
            loadReview(currentPage);
        });

        $('#searchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadReview(1);
            }
        });

        $(document).on('click', '.review-page', function (e) {
            e.preventDefault();

            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadReview(page);
            }
        });

        loadReview();
    </script>
@endpush
