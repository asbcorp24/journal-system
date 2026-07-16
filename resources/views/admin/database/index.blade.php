@extends('admin.layouts.app')

@section('title', 'Экспорт и импорт базы')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Экспорт и импорт базы данных</h1>
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
        <div class="col-lg-6">
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

        <div class="col-lg-6">
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
    </div>
@endsection
