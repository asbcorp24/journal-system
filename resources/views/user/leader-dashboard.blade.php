@extends('user.layouts.app')

@section('title', 'Дашборд руководителя')

@section('content')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Дашборд руководителя</h2>
            <div class="text-secondary">
                Оперативная сводка по журналам и проблемным участкам
            </div>
        </div>

        <form class="d-flex flex-wrap gap-2 align-items-end" method="GET">
            <div>
                <label class="form-label small text-secondary mb-1">Период</label>
                <select name="period" class="form-select">
                    <option value="7" {{ $period === 7 ? 'selected' : '' }}>7 дней</option>
                    <option value="30" {{ $period === 30 ? 'selected' : '' }}>30 дней</option>
                    <option value="90" {{ $period === 90 ? 'selected' : '' }}>90 дней</option>
                </select>
            </div>

            @if(session('user_role') === 'admin')
                <div>
                    <label class="form-label small text-secondary mb-1">Подразделение</label>
                    <select name="division_id" class="form-select">
                        <option value="">Все доступные</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}" {{ (string) $selectedDivisionId === (string) $division->id ? 'selected' : '' }}>
                                {{ $division->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <button type="submit" class="btn btn-primary">
                    Обновить
                </button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small mb-2">Записей сегодня</div>
                    <div class="display-6 fw-bold">{{ $cards['entries_today'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small mb-2">Отклонено за период</div>
                    <div class="display-6 fw-bold text-warning">{{ $cards['rejected_period'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small mb-2">Удалено за период</div>
                    <div class="display-6 fw-bold text-danger">{{ $cards['deleted_period'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1">Контроль справочников</h5>
                    <div class="text-secondary small">Изменения значений справочников за выбранный период</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small mb-2">Новых значений сегодня</div>
                    <div class="display-6 fw-bold">{{ $cards['directory_values_today'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small mb-2">Обновлено за период</div>
                    <div class="display-6 fw-bold text-info">{{ $cards['directory_updated_period'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small mb-2">Удалено за период</div>
                    <div class="display-6 fw-bold text-danger">{{ $cards['directory_deleted_period'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Динамика за период</h5>
                            <div class="text-secondary small">Новые записи, отклонения и удаления</div>
                        </div>
                    </div>

                    <div style="height: 340px;">
                        <canvas id="leaderDailyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Динамика по справочникам</h5>
                            <div class="text-secondary small">Новые, обновленные и удаленные значения</div>
                        </div>
                    </div>

                    <div style="height: 340px;">
                        <canvas id="leaderDirectoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-1">Проблемные участки</h5>
                    <div class="text-secondary small mb-3">Где больше всего отклонений и удалений</div>

                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Подразделение</th>
                                <th>Записей</th>
                                <th>Откл.</th>
                                <th>Удал.</th>
                                <th>Балл</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($problemDivisions as $row)
                                <tr>
                                    <td>{{ $row['division_name'] }}</td>
                                    <td>{{ $row['entries_count'] }}</td>
                                    <td class="text-warning">{{ $row['rejected_count'] }}</td>
                                    <td class="text-danger">{{ $row['deleted_count'] }}</td>
                                    <td><span class="badge bg-danger">{{ $row['problem_score'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">
                                        За выбранный период проблемных участков не найдено
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-1">Проблемные справочники</h5>
                    <div class="text-secondary small mb-3">Где больше всего обновлений и удалений значений</div>

                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Справочник</th>
                                <th>Создано</th>
                                <th>Обновлено</th>
                                <th>Удалено</th>
                                <th>Балл</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($problemDirectories as $row)
                                <tr>
                                    <td>{{ $row['directory_name'] }}</td>
                                    <td>{{ $row['created_count'] }}</td>
                                    <td class="text-info">{{ $row['updated_count'] }}</td>
                                    <td class="text-danger">{{ $row['deleted_count'] }}</td>
                                    <td><span class="badge bg-danger">{{ $row['problem_score'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">
                                        За выбранный период проблемных справочников не найдено
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('vendor/jquery/chart.js') }}"></script>
    <script>
        const leaderDailyChart = @json($dailyChart);
        const leaderDirectoryChartData = @json($directoryDailyChart);

        const dailyCtx = document.getElementById('leaderDailyChart');
        if (dailyCtx) {
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: leaderDailyChart.labels || [],
                    datasets: [
                        {
                            label: 'Новые записи',
                            data: leaderDailyChart.entries || [],
                            borderColor: '#38bdf8',
                            backgroundColor: 'rgba(56, 189, 248, .18)',
                            tension: .28,
                            fill: true
                        },
                        {
                            label: 'Отклонено',
                            data: leaderDailyChart.rejected || [],
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, .15)',
                            tension: .28,
                            fill: true
                        },
                        {
                            label: 'Удалено',
                            data: leaderDailyChart.deleted || [],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, .15)',
                            tension: .28,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#cbd5e1'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, .12)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#94a3b8', precision: 0 },
                            grid: { color: 'rgba(148, 163, 184, .12)' }
                        }
                    }
                }
            });
        }

        const directoryCtx = document.getElementById('leaderDirectoryChart');
        if (directoryCtx) {
            new Chart(directoryCtx, {
                type: 'line',
                data: {
                    labels: leaderDirectoryChartData.labels || [],
                    datasets: [
                        {
                            label: '\u041d\u043e\u0432\u044b\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u044f',
                            data: leaderDirectoryChartData.created || [],
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, .16)',
                            tension: .28,
                            fill: true
                        },
                        {
                            label: '\u041e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u043e',
                            data: leaderDirectoryChartData.updated || [],
                            borderColor: '#60a5fa',
                            backgroundColor: 'rgba(96, 165, 250, .14)',
                            tension: .28,
                            fill: true
                        },
                        {
                            label: '\u0423\u0434\u0430\u043b\u0435\u043d\u043e',
                            data: leaderDirectoryChartData.deleted || [],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, .14)',
                            tension: .28,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#cbd5e1'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, .12)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#94a3b8', precision: 0 },
                            grid: { color: 'rgba(148, 163, 184, .12)' }
                        }
                    }
                }
            });
        }
    </script>
@endpush
