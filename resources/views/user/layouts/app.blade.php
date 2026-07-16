<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'СЭПЖ')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery/bootstrap.bundle.min.js') }}"></script>


    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark px-3">
    <button class="btn btn-outline-light btn-sm me-2 mobile-menu-btn"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar"
            aria-controls="mobileSidebar">
        <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand fw-bold" href="{{ route('user.dashboard') }}">
        СЭПЖ
    </a>

    <div class="ms-auto d-flex align-items-center gap-3">


        <a href="{{ route('user.notifications.index') }}"
           class="btn btn-outline-light btn-sm position-relative">
            <i class="bi bi-bell"></i>

            <span id="notificationsBadge"
                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
        0
    </span>
        </a>
        <div class="user-badge d-none d-md-block">
            {{ session('user_name') }}
            <span class="text-secondary">
                / {{ session('user_role') }}
            </span>
        </div>

        <a href="{{ route('user.logout') }}" class="btn btn-outline-light btn-sm">
            Выйти
        </a>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar desktop-sidebar p-3">
            <a href="{{ route('user.dashboard') }}"
               class="{{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                Мои журналы
            </a>

            <a href="#">
                <i class="bi bi-clock-history"></i>
                Мои записи
            </a>
            <a href="{{ route('user.charts.index') }}"
               class="{{ request()->routeIs('user.charts.*') ? 'active' : '' }}">
                <i class="bi bi-graph-up"></i>
                Графики
            </a>
            <a href="{{ route('user.directories.index') }}"
               class="{{ request()->routeIs('user.directories.*') ? 'active' : '' }}">
                <i class="bi bi-card-list"></i>
                Справочники
            </a>
            @if(session('user_role') === 'foreman' || session('user_role') === 'admin')
                <a href="{{ route('user.review.index') }}"
                   class="{{ request()->routeIs('user.review.*') ? 'active' : '' }}">
                    <i class="bi bi-check2-square"></i>
                    На проверку
                </a>
            @endif
            <a href="{{ route('user.notifications.index') }}"
               class="{{ request()->routeIs('user.notifications.*') ? 'active' : '' }}">
                <i class="bi bi-bell"></i>
                Уведомления
            </a>
            @if(session('user_role') === 'admin')
                <a href="{{ route('user.reports.index') }}"
                   class="{{ request()->routeIs('user.reports.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    Отчёты
                </a>
            @endif
        </div>

        <div class="col-12 col-md-10 content">
            @yield('content')
        </div>
    </div>
</div>
<div class="offcanvas offcanvas-start mobile-sidebar"
     tabindex="-1"
     id="mobileSidebar"
     aria-labelledby="mobileSidebarLabel">

    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="mobileSidebarLabel">
            СЭПЖ
        </h5>

        <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas"
                aria-label="Закрыть"></button>
    </div>

    <div class="offcanvas-body">
        <div class="mb-3 user-badge">
            {{ session('user_name') }}
            <div class="text-secondary small">
                {{ session('user_role') }}
            </div>
        </div>

        <a href="{{ route('user.dashboard') }}"
           class="{{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i>
            Мои журналы
        </a>

        <a href="#">
            <i class="bi bi-clock-history"></i>
            Мои записи
        </a>

        <a href="{{ route('user.directories.index') }}"
           class="{{ request()->routeIs('user.directories.*') ? 'active' : '' }}">
            <i class="bi bi-card-list"></i>
            Справочники
        </a>

        @if(session('user_role') === 'foreman' || session('user_role') === 'admin')
            <a href="#">
                <i class="bi bi-check2-square"></i>
                Проверка записей
            </a>
        @endif
        <a href="{{ route('user.notifications.index') }}"
           class="{{ request()->routeIs('user.notifications.*') ? 'active' : '' }}">
            <i class="bi bi-bell"></i>
            Уведомления
        </a>

        @if(session('user_role') === 'admin')
            <a href="{{ route('user.reports.index') }}"
               class="{{ request()->routeIs('user.reports.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                Отчёты
            </a>
        @endif

        <hr class="border-secondary">
        <a href="{{ route('user.notifications.index') }}"
           class="btn btn-outline-light btn-sm position-relative">
            <i class="bi bi-bell"></i>

            <span id="notificationsBadge"
                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
        0
    </span>
        </a>
        <a href="{{ route('user.logout') }}">
            <i class="bi bi-box-arrow-right"></i>
            Выйти
        </a>
    </div>
</div>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
    <div id="toastBox" class="toast align-items-center text-bg-primary border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">
                Сообщение
            </div>

            <button type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

@vite(['resources/css/app.css', 'resources/js/app.js'])

<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function showToast(message, type = 'primary') {
        let toastBox = $('#toastBox');

        toastBox.removeClass('text-bg-primary text-bg-success text-bg-danger text-bg-warning');
        toastBox.addClass('text-bg-' + type);

        $('#toastMessage').text(message);

        let toast = new bootstrap.Toast(document.getElementById('toastBox'));
        toast.show();
    }

    function showAjaxErrors(xhr) {
        if (xhr.status === 401) {
            window.location.href = "{{ route('user.login') }}";
            return;
        }

        if (xhr.status === 422) {
            let errors = xhr.responseJSON.errors;
            let message = xhr.responseJSON.message || 'Ошибка заполнения формы';

            if (errors) {
                message = Object.values(errors).map(function (item) {
                    return item[0];
                }).join('\n');
            }

            showToast(message, 'danger');
            return;
        }

        if (xhr.responseJSON && xhr.responseJSON.message) {
            showToast(xhr.responseJSON.message, 'danger');
            return;
        }

        showToast('Ошибка запроса', 'danger');
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }

        return $('<div>').text(text).html();
    }

    function snapshotSelectOptions($select) {
        return $select.find('option').map(function () {
            return {
                value: $(this).attr('value') ?? '',
                text: $(this).text(),
                selected: $(this).prop('selected'),
                disabled: $(this).prop('disabled')
            };
        }).get();
    }

    function renderFilteredSelectOptions($select, items, preserveSelected = true) {
        let selectedValues = preserveSelected ? ($select.val() || []) : [];

        if (!Array.isArray(selectedValues)) {
            selectedValues = [selectedValues];
        }

        let html = '';

        items.forEach(function (item) {
            let selected = selectedValues.some(function (value) {
                return String(value) === String(item.value);
            });

            html += `<option value="${escapeHtml(item.value)}" ${item.disabled ? 'disabled' : ''} ${selected ? 'selected' : ''}>${escapeHtml(item.text)}</option>`;
        });

        $select.html(html);
    }

    function enhanceSearchableSelect($select) {
        if (!$select.length || $select.data('search-enhanced') || $select.hasClass('no-search-select')) {
            return;
        }

        let options = snapshotSelectOptions($select);

        if (options.length < 8) {
            $select.data('search-enhanced', true);
            return;
        }

        let $box = $('<div class="searchable-select-box"></div>');
        let placeholder = $select.attr('multiple') ? 'Поиск по списку...' : 'Поиск по вариантам...';
        let $inputWrap = $('<div class="searchable-select-input-wrap"></div>');
        let $icon = $('<span class="searchable-select-icon"><i class="bi bi-search"></i></span>');
        let $input = $(`<input type="text" class="searchable-select-input" placeholder="${placeholder}">`);

        $select.before($box);
        $inputWrap.append($icon);
        $inputWrap.append($input);
        $box.append($inputWrap);
        $box.append($select);

        $select.data('search-enhanced', true);
        $select.data('all-options', options);

        $input.on('input', function () {
            let query = ($(this).val() || '').trim().toLowerCase();
            let allOptions = $select.data('all-options') || [];

            if (!query) {
                renderFilteredSelectOptions($select, allOptions);
                return;
            }

            let filtered = allOptions.filter(function (item) {
                return String(item.text).toLowerCase().includes(query);
            });

            renderFilteredSelectOptions($select, filtered);
        });
    }

    function initSearchableSelects(root = document) {
        $(root).find('select.form-select').each(function () {
            enhanceSearchableSelect($(this));
        });
    }

    function refreshSearchableSelect($select) {
        if (!$select.length || !$select.data('search-enhanced')) {
            return;
        }

        $select.data('all-options', snapshotSelectOptions($select));
    }

</script>
<script>
    function loadNotificationsCount() {
        $.ajax({
            url: "{{ route('user.notifications.unread-count') }}",
            method: "GET",
            success: function (response) {
                let count = response.count || 0;

                if (count > 0) {
                    $('#notificationsBadge')
                        .removeClass('d-none')
                        .text(count > 99 ? '99+' : count);
                } else {
                    $('#notificationsBadge')
                        .addClass('d-none')
                        .text('0');
                }
            }
        });
    }

    loadNotificationsCount();
    initSearchableSelects();

    const searchableSelectObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) {
                    return;
                }

                if (node.tagName === 'OPTION') {
                    let $parentSelect = $(node).closest('select.form-select');

                    if ($parentSelect.length) {
                        refreshSearchableSelect($parentSelect);
                    }

                    return;
                }

                if ($(node).is('select.form-select')) {
                    enhanceSearchableSelect($(node));
                    return;
                }

                initSearchableSelects(node);
            });
        });
    });

    searchableSelectObserver.observe(document.body, {
        childList: true,
        subtree: true
    });

    setInterval(loadNotificationsCount, 30000);
</script>
@stack('scripts')

</body>

<style>
    body {
        background: #0f172a;
        color: #e5e7eb;
    }

    .navbar {
        background: #020617;
        border-bottom: 1px solid #1e293b;
    }

    .sidebar {
        min-height: calc(100vh - 56px);
        background: #020617;
        border-right: 1px solid #1e293b;
    }

    .sidebar a {
        color: #cbd5e1;
        text-decoration: none;
        display: block;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 6px;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: #1e293b;
        color: #ffffff;
    }

    .content {
        padding: 24px;
    }

    .card {
        background: #111827;
        color: #e5e7eb;
        border: 1px solid #1f2937;
        border-radius: 16px;
    }

    .table {
        color: #e5e7eb;
    }

    .table thead {
        background: #1f2937;
    }

    .form-control,
    .form-select {
        background-color: #020617;
        color: #e5e7eb;
        border-color: #334155;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: #020617;
        color: #ffffff;
        border-color: #38bdf8;
        box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .2);
    }

    .modal-content {
        background: #111827;
        color: #e5e7eb;
        border: 1px solid #334155;
    }

    .btn-primary {
        background: #2563eb;
        border-color: #2563eb;
    }

    .user-badge {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 12px;
        padding: 6px 12px;
        color: #cbd5e1;
    }

    .journal-card {
        transition: .15s;
        cursor: pointer;
    }

    .journal-card:hover {
        transform: translateY(-2px);
        border-color: #38bdf8;
    }

    .pagination .page-link {
        background: #020617;
        border-color: #334155;
        color: #e5e7eb;
    }

    .pagination .active .page-link {
        background: #2563eb;
        border-color: #2563eb;
    }

    .searchable-select-box {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .searchable-select-input-wrap {
        position: relative;
    }

    .searchable-select-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
        font-size: 14px;
    }

    .searchable-select-input {
        background-color: #020617;
        color: #e5e7eb;
        border: 1px solid #334155;
        border-radius: .375rem;
        padding: .375rem .75rem .375rem 2rem;
    }

    .searchable-select-input:focus {
        outline: none;
        border-color: #38bdf8;
        box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .2);
    }

    .mobile-menu-btn {
        display: none;
    }

    .mobile-sidebar {
        background: #020617;
        color: #e5e7eb;
    }

    .mobile-sidebar .offcanvas-header {
        border-bottom: 1px solid #1e293b;
    }

    .mobile-sidebar a {
        color: #cbd5e1;
        text-decoration: none;
        display: block;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 6px;
    }

    .mobile-sidebar a:hover,
    .mobile-sidebar a.active {
        background: #1e293b;
        color: #ffffff;
    }

    @media (max-width: 767.98px) {
        .mobile-menu-btn {
            display: inline-flex;
        }

        .desktop-sidebar {
            display: none;
        }

        .content {
            padding: 16px;
        }

        .navbar {
            min-height: 56px;
        }

        .navbar-brand {
            font-size: 18px;
        }
    }
</style>
</html>
