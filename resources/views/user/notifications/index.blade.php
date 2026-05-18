@extends('user.layouts.app')

@section('title', 'Уведомления')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Уведомления</h2>
            <div class="text-secondary">
                События по журналам, комментариям и проверкам
            </div>
        </div>

        <button class="btn btn-outline-light" id="markAllReadBtn">
            <i class="bi bi-check2-all"></i>
            Прочитать все
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <select id="readFilter" class="form-select">
                        <option value="">Все уведомления</option>
                        <option value="0">Непрочитанные</option>
                        <option value="1">Прочитанные</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-info w-100" id="applyFilterBtn">
                        Применить
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="notificationsList">
        <div class="text-center text-secondary py-5">
            Загрузка...
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-secondary" id="paginationInfo"></div>
        <ul class="pagination mb-0" id="paginationLinks"></ul>
    </div>

@endsection

@push('scripts')
    <script>
        let currentPage = 1;

        function notificationTypeClass(type) {
            if (type === 'success') {
                return 'border-success';
            }

            if (type === 'danger') {
                return 'border-danger';
            }

            if (type === 'warning') {
                return 'border-warning';
            }

            return 'border-info';
        }

        function notificationIcon(type) {
            if (type === 'success') {
                return 'bi-check-circle text-success';
            }

            if (type === 'danger') {
                return 'bi-x-circle text-danger';
            }

            if (type === 'warning') {
                return 'bi-exclamation-triangle text-warning';
            }

            return 'bi-info-circle text-info';
        }

        function loadNotifications(page = 1) {
            currentPage = page;

            $('#notificationsList').html(`
            <div class="text-center text-secondary py-5">
                Загрузка...
            </div>
        `);

            $.ajax({
                url: "{{ route('user.notifications.list') }}",
                method: "GET",
                data: {
                    page: page,
                    is_read: $('#readFilter').val()
                },
                success: function (response) {
                    renderNotifications(response.items);
                    renderPagination(response.pagination);
                    loadNotificationsCount();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderNotifications(items) {
            if (!items || items.length === 0) {
                $('#notificationsList').html(`
                <div class="card">
                    <div class="card-body text-center text-secondary py-5">
                        Уведомлений нет
                    </div>
                </div>
            `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                html += `
                <div class="card mb-3 ${notificationTypeClass(item.type)}" style="border-left-width: 5px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3">
                            <div class="d-flex gap-3">
                                <div>
                                    <i class="bi ${notificationIcon(item.type)} fs-3"></i>
                                </div>

                                <div>
                                    <div class="fw-bold">
                                        ${escapeHtml(item.title)}
                                        ${!item.is_read ? '<span class="badge bg-danger ms-2">Новое</span>' : ''}
                                    </div>

                                    <div class="text-secondary mt-1">
                                        ${escapeHtml(item.message || '')}
                                    </div>

                                    <div class="text-secondary small mt-2">
                                        ${escapeHtml(item.created_at || '')}
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                ${item.url ? `
                                    <a href="/notifications/${item.id}/open"
                                       class="btn btn-sm btn-outline-info mb-2">
                                        Открыть
                                    </a>
                                ` : ''}

                                ${!item.is_read ? `
                                    <button class="btn btn-sm btn-outline-light mark-read"
                                            data-id="${item.id}">
                                        Прочитано
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            });

            $('#notificationsList').html(html);
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
                <a href="#" class="page-link notification-page" data-page="${current - 1}">
                    Назад
                </a>
            </li>
        `;

            for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
                html += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a href="#" class="page-link notification-page" data-page="${i}">
                        ${i}
                    </a>
                </li>
            `;
            }

            html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <a href="#" class="page-link notification-page" data-page="${current + 1}">
                    Вперёд
                </a>
            </li>
        `;

            $('#paginationLinks').html(html);
        }

        $(document).on('click', '.notification-page', function (e) {
            e.preventDefault();

            let page = parseInt($(this).data('page'));

            if (page > 0) {
                loadNotifications(page);
            }
        });

        $(document).on('click', '.mark-read', function () {
            let id = $(this).data('id');

            $.ajax({
                url: `/notifications/${id}/read`,
                method: "POST",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadNotifications(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#markAllReadBtn').on('click', function () {
            $.ajax({
                url: "{{ route('user.notifications.read-all') }}",
                method: "POST",
                success: function (response) {
                    showToast(response.message, 'success');
                    loadNotifications(currentPage);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#applyFilterBtn, #readFilter').on('change click', function () {
            loadNotifications(1);
        });

        loadNotifications();
    </script>
@endpush
