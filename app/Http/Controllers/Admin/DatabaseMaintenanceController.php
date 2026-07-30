<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SqlDebugLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseMaintenanceController extends Controller
{
    public function index(): View
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $databasePath = $this->databasePath();

        return view('admin.database.index', [
            'connection' => $connection,
            'driver' => $driver,
            'databasePath' => $databasePath,
            'databaseExists' => $databasePath ? File::exists($databasePath) : false,
            'debugAvailable' => false,
            'sqlDebugEnabled' => false,
            'debugLogs' => null,
            'sqlInput' => '',
            'sqlResult' => null,
        ]);
    }

    public function runSql(Request $request): View
    {
        $request->validate([
            'sql' => ['required', 'string', 'max:50000'],
        ], [
            'sql.required' => 'Введите SQL-запрос.',
            'sql.max' => 'SQL-запрос слишком длинный.',
        ]);

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $databasePath = $this->databasePath();
        $sql = trim((string) $request->input('sql'));
        $statementType = strtolower((string) preg_replace('/\s+.*/', '', ltrim($sql)));
        $result = [
            'type' => $statementType,
            'success' => true,
            'message' => null,
            'columns' => [],
            'rows' => [],
            'row_count' => null,
        ];

        try {
            $startedAt = microtime(true);
            $pdo = DB::connection()->getPdo();
            $statement = $pdo->prepare($sql);
            $statement->execute();
            SqlDebugLogger::logManualQuery($sql, [], round((microtime(true) - $startedAt) * 1000, 3));

            if (in_array($statementType, ['select', 'pragma', 'show', 'describe', 'desc', 'with', 'explain'], true)) {
                $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $result['rows'] = $rows;
                $result['columns'] = !empty($rows) ? array_keys($rows[0]) : [];
                $result['row_count'] = count($rows);
                $result['message'] = 'Запрос выполнен. Получено строк: ' . count($rows) . '.';
            } else {
                $result['row_count'] = $statement->rowCount();
                $result['message'] = 'Запрос выполнен. Затронуто строк: ' . $statement->rowCount() . '.';
            }
        } catch (\Throwable $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return view('admin.database.index', [
            'connection' => $connection,
            'driver' => $driver,
            'databasePath' => $databasePath,
            'databaseExists' => $databasePath ? File::exists($databasePath) : false,
            'debugAvailable' => false,
            'sqlDebugEnabled' => false,
            'debugLogs' => null,
            'sqlInput' => $sql,
            'sqlResult' => $result,
        ]);
    }

    public function updateDebug(Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.database.index')
            ->with('success', 'SQL-debug временно отключён.');
    }

    public function clearDebugLogs(): RedirectResponse
    {
        return redirect()
            ->route('admin.database.index')
            ->with('success', 'SQL-debug временно отключён.');
    }

    public function export(): BinaryFileResponse
    {
        $path = $this->requireSqliteDatabasePath();

        abort_unless(File::exists($path), 404, 'Файл базы данных не найден.');

        return Response::download(
            $path,
            'journal-system-backup-' . now()->format('Y-m-d_H-i-s') . '.sqlite'
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $path = $this->requireSqliteDatabasePath();

        $request->validate([
            'database_file' => ['required', 'file', 'max:51200'],
        ], [
            'database_file.required' => 'Выберите файл базы данных.',
            'database_file.file' => 'Нужен корректный файл базы данных.',
            'database_file.max' => 'Размер файла не должен превышать 50 МБ.',
        ]);

        $uploadedFile = $request->file('database_file');
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        $allowedExtensions = ['sqlite', 'sqlite3', 'db'];

        if (!in_array($extension, $allowedExtensions, true)) {
            return back()->withErrors([
                'database_file' => 'Разрешены только файлы .sqlite, .sqlite3 или .db.',
            ]);
        }

        $tempPath = $uploadedFile->getRealPath();
        if (!$tempPath || !is_file($tempPath)) {
            return back()->withErrors([
                'database_file' => 'Не удалось прочитать загруженный файл.',
            ]);
        }

        $directory = dirname($path);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $backupPath = $path . '.backup-' . now()->format('Ymd_His');
        $connection = config('database.default');

        DB::disconnect($connection);
        app('db')->purge($connection);

        if (File::exists($path)) {
            File::copy($path, $backupPath);
        }

        try {
            File::copy($tempPath, $path);
            @chmod($path, 0664);
            DB::reconnect($connection);
        } catch (\Throwable $e) {
            if (File::exists($backupPath)) {
                File::copy($backupPath, $path);
            }

            throw $e;
        }

        return redirect()
            ->route('admin.database.index')
            ->with('success', 'База данных успешно импортирована. Резервная копия сохранена рядом с текущим файлом.');
    }

    private function requireSqliteDatabasePath(): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        abort_unless($driver === 'sqlite', 422, 'Импорт и экспорт доступны только для SQLite.');

        $path = $this->databasePath();
        abort_unless($path, 422, 'Путь к файлу базы данных не настроен.');

        return $path;
    }

    private function databasePath(): ?string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            return null;
        }

        $database = (string) config("database.connections.{$connection}.database");
        if ($database === '' || $database === ':memory:') {
            return null;
        }

        if (
            str_starts_with($database, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:(\\\\|\/)/', $database)
        ) {
            return $database;
        }

        return database_path($database);
    }
}
