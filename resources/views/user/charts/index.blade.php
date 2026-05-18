@extends('user.layouts.app')

@section('title', 'Графики журналов')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Графики журналов</h2>
            <div class="text-secondary">
                Автоматическое построение графиков по числовым полям журналов
            </div>
        </div>

        <button class="btn btn-outline-light" id="refreshChartsBtn">
            <i class="bi bi-arrow-clockwise"></i>
            Обновить
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Дата от</label>
                    <input type="date" id="dateFrom" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Дата до</label>
                    <input type="date" id="dateTo" class="form-control">
                </div>

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

                <div class="col-md-2">
                    <button class="btn btn-primary w-100" id="applyFilters">
                        Применить
                    </button>
                </div>

                <div class="col-md-1">
                    <button class="btn btn-outline-light w-100" id="resetFilters">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" id="journalTabs" role="tablist"></ul>

            <div class="tab-content pt-4" id="journalTabsContent">
                <div class="text-center text-secondary py-5">
                    Загрузка графиков...
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        let chartInstances = {};

        function destroyCharts() {
            Object.keys(chartInstances).forEach(function (key) {
                chartInstances[key].destroy();
            });

            chartInstances = {};
        }

        function loadCharts() {
            destroyCharts();

            $('#journalTabs').html('');
            $('#journalTabsContent').html(`
            <div class="text-center text-secondary py-5">
                Загрузка графиков...
            </div>
        `);

            $.ajax({
                url: "{{ route('user.charts.data') }}",
                method: "GET",
                data: {
                    date_from: $('#dateFrom').val(),
                    date_to: $('#dateTo').val(),
                    division_id: $('#divisionFilter').length ? $('#divisionFilter').val() : ''
                },
                success: function (response) {
                    renderJournalTabs(response.journals || []);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderJournalTabs(journals) {
            if (!journals || journals.length === 0) {
                $('#journalTabs').html('');
                $('#journalTabsContent').html(`
                <div class="text-center text-secondary py-5">
                    Нет доступных журналов
                </div>
            `);
                return;
            }

            let tabsHtml = '';
            let contentHtml = '';

            journals.forEach(function (journal, index) {
                let active = index === 0 ? 'active' : '';
                let selected = index === 0 ? 'true' : 'false';
                let tabId = `journal-tab-${journal.journal_id}`;
                let contentId = `journal-content-${journal.journal_id}`;

                tabsHtml += `
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${active}"
                            id="${tabId}"
                            data-bs-toggle="tab"
                            data-bs-target="#${contentId}"
                            type="button"
                            role="tab"
                            aria-selected="${selected}">
                        ${escapeHtml(journal.journal_name)}
                    </button>
                </li>
            `;

                contentHtml += `
                <div class="tab-pane fade show ${active}"
                     id="${contentId}"
                     role="tabpanel"
                     aria-labelledby="${tabId}">
                    ${renderJournalChartsContainer(journal)}
                </div>
            `;
            });

            $('#journalTabs').html(tabsHtml);
            $('#journalTabsContent').html(contentHtml);

            journals.forEach(function (journal) {
                renderChartsForJournal(journal);
            });
        }

        function renderJournalChartsContainer(journal) {
            if (!journal.charts || journal.charts.length === 0) {
                return `
                <div class="text-center text-secondary py-5">
                    В этом журнале нет числовых полей type = number или type = calc
                </div>
            `;
            }

            let html = `
            <div class="mb-3">
                <h5 class="fw-bold">${escapeHtml(journal.journal_name)}</h5>
                <div class="text-secondary">
                    Найдено числовых показателей: ${journal.charts.length}
                </div>
            </div>

            <div class="row g-4">
        `;

            journal.charts.forEach(function (chart) {
                let canvasId = `chart_${journal.journal_id}_${chart.key}`;

                html += `
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-bold mb-1">
                                        ${escapeHtml(chart.label)}
                                    </h5>
                                    <div class="text-secondary small">
                                        Поле: ${escapeHtml(chart.key)} / тип: ${escapeHtml(chart.type)}
                                    </div>
                                </div>

                                <span class="badge bg-info text-dark">
                                    Точек: ${chart.points ? chart.points.length : 0}
                                </span>
                            </div>

                            ${chart.points && chart.points.length > 0
                    ? `<div style="height: 340px;">
                                       <canvas id="${canvasId}"></canvas>
                                   </div>`
                    : `<div class="text-center text-secondary py-5">
                                       Нет числовых данных для построения графика
                                   </div>`
                }
                        </div>
                    </div>
                </div>
            `;
            });

            html += `</div>`;

            return html;
        }

        function renderChartsForJournal(journal) {
            if (!journal.charts) {
                return;
            }

            journal.charts.forEach(function (chart) {
                if (!chart.points || chart.points.length === 0) {
                    return;
                }

                let canvasId = `chart_${journal.journal_id}_${chart.key}`;
                let canvas = document.getElementById(canvasId);

                if (!canvas) {
                    return;
                }

                let labels = chart.points.map(function (point) {
                    return point.date + ' / #' + point.entry_id;
                });

                let values = chart.points.map(function (point) {
                    return point.value;
                });

                chartInstances[canvasId] = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: chart.label,
                                data: values,
                                tension: 0.25,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: '#e5e7eb'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    afterLabel: function (context) {
                                        let point = chart.points[context.dataIndex];

                                        return 'Запись #' + point.entry_id;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: '#cbd5e1'
                                },
                                grid: {
                                    color: 'rgba(148, 163, 184, 0.15)'
                                }
                            },
                            y: {
                                ticks: {
                                    color: '#cbd5e1'
                                },
                                grid: {
                                    color: 'rgba(148, 163, 184, 0.15)'
                                }
                            }
                        }
                    }
                });
            });
        }

        $('#applyFilters').on('click', function () {
            loadCharts();
        });

        $('#resetFilters').on('click', function () {
            $('#dateFrom').val('');
            $('#dateTo').val('');

            if ($('#divisionFilter').length) {
                $('#divisionFilter').val('');
            }

            loadCharts();
        });

        $('#refreshChartsBtn').on('click', function () {
            loadCharts();
        });

        if ($('#divisionFilter').length) {
            $('#divisionFilter').on('change', function () {
                loadCharts();
            });
        }

        loadCharts();
    </script>
@endpush
