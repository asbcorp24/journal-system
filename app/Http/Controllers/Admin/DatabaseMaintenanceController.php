<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);
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
