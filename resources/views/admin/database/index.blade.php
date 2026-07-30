@extends('admin.layouts.app')

@section('title', 'База данных')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">База данных</h1>
            <div class="text-secondary">Раздел доступен только супер-администратору.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Экспорт</h5>
                    <p class="text-secondary mb-3">Скачивает текущий файл базы данных целиком.</p>

                    <div class="small text-secondary mb-3">
                        <div>Подключение: <strong>{{ $connection }}</strong></div>
                        <div>Драйвер: <strong>{{ $driver }}</strong></div>
                        <div>Путь: <code>{{ $databasePath ?? 'не определён' }}</code></div>
                        <div>Статус файла: <strong>{{ $databaseExists ? 'найден' : 'не найден' }}</strong></div>
                    </div>

                    @if($driver === 'sqlite' && $databaseExists)
                        <a href="{{ route('admin.database.export') }}" class="btn btn-primary">
                            Скачать резервную копию
                        </a>
                    @else
                        <div class="alert alert-warning mb-0">
                            Экспорт через интерфейс доступен только для SQLite, когда файл базы найден.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Импорт</h5>
                    <p class="text-secondary">
                        Загруженный файл заменит текущую базу. Перед заменой будет создана резервная копия рядом с текущим файлом базы.
                    </p>

                    @if($driver === 'sqlite')
                        <form action="{{ route('admin.database.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="database_file" class="form-label">Файл базы данных</label>
                                <input
                                    type="file"
                                    class="form-control"
                                    id="database_file"
                                    name="database_file"
                                    accept=".sqlite,.sqlite3,.db"
                                    required
                                >
                            </div>

                            <div class="alert alert-warning">
                                Используйте импорт только для полной замены базы. Текущие данные будут перезаписаны.
                            </div>

                            <button type="submit" class="btn btn-danger">
                                Импортировать базу
                            </button>
                        </form>
                    @else
                        <div class="alert alert-warning mb-0">
                            Импорт через интерфейс поддерживается только для SQLite.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h5 class="card-title mb-1">SQL-debug</h5>
                            <div class="text-secondary small">
                                Если флаг включён, все новые SQL-запросы к базе будут сохраняться в журнал для последующего просмотра.
                            </div>
                        </div>

                        @if($debugAvailable)
                            <form action="{{ route('admin.database.debug') }}" method="POST" class="d-flex align-items-center gap-3 flex-wrap">
                                @csrf
                                <div class="form-check form-switch m-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="sql_debug_enabled"
                                        name="sql_debug_enabled"
                                        value="1"
                                        {{ $sqlDebugEnabled ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="sql_debug_enabled">
                                        Дебаг SQL
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-outline-primary btn-sm">Сохранить</button>
                            </form>
                        @else
                            <div class="alert alert-warning mb-0">
                                Для SQL-debug сначала выполните миграции.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">SQL-запрос</h5>
                    <p class="text-secondary small">
                        Выполняется как есть от имени текущего подключения базы данных. Поддерживаются SELECT, UPDATE, DELETE, INSERT, PRAGMA и другие одиночные запросы.
                    </p>

                    <form action="{{ route('admin.database.sql') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="sql" class="form-label">Текст SQL</label>
                            <textarea
                                class="form-control font-monospace"
                                id="sql"
                                name="sql"
                                rows="8"
                                placeholder="SELECT * FROM users ORDER BY id DESC LIMIT 20;"
                                required
                            >{{ old('sql', $sqlInput) }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Выполнить SQL</button>
                    </form>

                    @if($sqlResult)
                        <div class="mt-4">
                            <div class="alert {{ $sqlResult['success'] ? 'alert-success' : 'alert-danger' }} mb-3">
                                {{ $sqlResult['message'] }}
                            </div>

                            @if($sqlResult['success'] && !empty($sqlResult['columns']))
                                <div class="table-responsive">
                                    <table class="table table-dark table-striped align-middle">
                                        <thead>
                                        <tr>
                                            @foreach($sqlResult['columns'] as $column)
                                                <th>{{ $column }}</th>
                                            @endforeach
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($sqlResult['rows'] as $row)
                                            <tr>
                                                @foreach($sqlResult['columns'] as $column)
                                                    <td>
                                                        @php($value = $row[$column] ?? null)
                                                        {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_null($value) ? 'NULL' : $value) }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ max(count($sqlResult['columns']), 1) }}" class="text-secondary">
                                                    Запрос не вернул строк.
                                                </td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                        <div>
                            <h5 class="card-title mb-1">Журнал SQL-debug</h5>
                            <div class="text-secondary small">Показываются последние сохранённые SQL-запросы.</div>
                        </div>

                        @if($debugAvailable)
                            <form action="{{ route('admin.database.debug.clear') }}" method="POST" onsubmit="return confirm('Очистить журнал SQL-debug?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Очистить журнал</button>
                            </form>
                        @endif
                    </div>

                    @if(!$debugAvailable)
                        <div class="alert alert-warning mb-0">
                            Таблицы журнала ещё не созданы. Выполните миграции.
                        </div>
                    @elseif($debugLogs && $debugLogs->count())
                        <div class="table-responsive">
                            <table class="table table-dark table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Дата</th>
                                    <th>Подключение</th>
                                    <th>Время</th>
                                    <th>Пользователь</th>
                                    <th>Путь</th>
                                    <th>SQL</th>
                                    <th>Bindings</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($debugLogs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <div>{{ $log->connection_name }}</div>
                                            <div class="small text-secondary">{{ $log->driver }}</div>
                                        </td>
                                        <td>{{ $log->time_ms }} ms</td>
                                        <td>{{ $log->user_id ?? '—' }}</td>
                                        <td>{{ $log->request_path ?: '—' }}</td>
                                        <td><pre class="mb-0 small">{{ $log->sql }}</pre></td>
                                        <td><pre class="mb-0 small">{{ json_encode($log->bindings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $debugLogs->links() }}
                        </div>
                    @else
                        <div class="text-secondary">Журнал пока пуст.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
