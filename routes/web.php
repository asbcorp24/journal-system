<?php
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DatabaseMaintenanceController;
use App\Http\Controllers\Admin\DirectoryController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\JournalTemplateController;
use App\Http\Controllers\Admin\JournalPrintTemplateController;
use App\Http\Controllers\Admin\ReportTemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\User\ChartController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\DirectoryController as UserDirectoryController;
use App\Http\Controllers\User\DirectoryTemplateController;
use App\Http\Controllers\User\JournalController;
use App\Http\Controllers\User\JournalPrintTemplateController as UserJournalPrintTemplateController;
use App\Http\Controllers\User\JournalTemplateEditorController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\ReviewController;
use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    if (session('user_id')) {
        return redirect()->route('user.dashboard');
    }

    return redirect()->route('user.login');
});

Route::get('/login', [UserAuthController::class, 'loginPage'])->name('user.login');
Route::post('/login', [UserAuthController::class, 'login'])->name('user.login.post');
Route::get('/logout', [UserAuthController::class, 'logout'])->name('user.logout');

Route::middleware('user.auth')->group(function () {
    Route::get('/journals', [DashboardController::class, 'index'])->name('user.dashboard');
    Route::get('/journals/{journal}', [JournalController::class, 'show'])
        ->name('user.journals.show');

    Route::get('/journals/{journal}/entries', [JournalController::class, 'list'])
        ->name('user.journals.entries.list');

    Route::post('/journals/{journal}/entries', [JournalController::class, 'store'])
        ->name('user.journals.entries.store');

    Route::get('/journals/{journal}/entries/{entry}', [JournalController::class, 'showEntry'])
        ->withTrashed()
        ->name('user.journals.entries.show');

    Route::post('/journals/{journal}/entries/{entry}', [JournalController::class, 'update'])
        ->name('user.journals.entries.update');

    Route::delete('/journals/{journal}/entries/{entry}', [JournalController::class, 'destroy'])
        ->withTrashed()
        ->name('user.journals.entries.destroy');

    Route::post('/journals/{journal}/entries/{entry}/restore', [JournalController::class, 'restore'])
        ->withTrashed()
        ->name('user.journals.entries.restore');

    Route::post('/journals/{journal}/entries/{entry}/sql-fields/{fieldKey}/recalculate', [JournalController::class, 'recalculateSqlField'])
        ->name('user.journals.entries.sql-fields.recalculate');

    Route::post('/journals/{journal}/entries/{entry}/approve', [JournalController::class, 'approve'])
        ->name('user.journals.entries.approve');

    Route::post('/journals/{journal}/entries/{entry}/reject', [JournalController::class, 'reject'])
        ->name('user.journals.entries.reject');

    Route::get('/reports', [ReportController::class, 'index'])
        ->name('user.reports.index');

    Route::get('/reports/{report}', [ReportController::class, 'show'])
        ->name('user.reports.show');

    Route::post('/reports/{report}/run', [ReportController::class, 'run'])
        ->name('user.reports.run');

    Route::post('/reports/{report}/export', [ReportController::class, 'export'])
        ->name('user.reports.export');
    Route::get('/journals/{journal}/print', [JournalController::class, 'print'])
        ->name('user.journals.print');


    Route::get('/journals/{journal}/entries/{entry}/comments', [JournalController::class, 'comments'])
        ->name('user.journals.entries.comments');

    Route::post('/journals/{journal}/entries/{entry}/comments', [JournalController::class, 'storeComment'])
        ->name('user.journals.entries.comments.store');

    Route::post('/journals/{journal}/entries/{entry}/comments/{comment}', [JournalController::class, 'updateComment'])
        ->name('user.journals.entries.comments.update');

    Route::post('/journals/{journal}/directories/{directory}/values', [JournalController::class, 'storeDirectoryValue'])
        ->name('user.journals.directories.values.store');

    Route::get('/journals/{journal}/entries/{entry}/logs', [JournalController::class, 'logs'])
        ->name('user.journals.entries.logs');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('user.notifications.index');

    Route::get('/notifications/list', [NotificationController::class, 'list'])
        ->name('user.notifications.list');

    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('user.notifications.unread-count');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('user.notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('user.notifications.read-all');

    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])
        ->name('user.notifications.open');

    Route::get('/review', [ReviewController::class, 'index'])
        ->name('user.review.index');

    Route::get('/review/list', [ReviewController::class, 'list'])
        ->name('user.review.list');

    Route::get('/review/entries/{entry}', [ReviewController::class, 'showEntry'])
        ->name('user.review.entries.show');

    Route::post('/review/entries/{entry}/approve', [ReviewController::class, 'approve'])
        ->name('user.review.entries.approve');

    Route::post('/review/entries/{entry}/reject', [ReviewController::class, 'reject'])
        ->name('user.review.entries.reject');
    Route::get('/charts', [ChartController::class, 'index'])
        ->name('user.charts.index');

    Route::get('/charts/data', [ChartController::class, 'data'])
        ->name('user.charts.data');

    Route::get('/directories', [UserDirectoryController::class, 'index'])
        ->name('user.directories.index');

    Route::get('/directories/list', [UserDirectoryController::class, 'list'])
        ->name('user.directories.list');

    Route::get('/directories/{directory}/values', [UserDirectoryController::class, 'valuesList'])
        ->name('user.directories.values.list');

    Route::get('/directories/{directory}/print', [UserDirectoryController::class, 'print'])
        ->name('user.directories.print');

    Route::get('/directories/{directory}/barcodes', [UserDirectoryController::class, 'printBarcodes'])
        ->name('user.directories.barcodes');

    Route::post('/directories/{directory}/values', [UserDirectoryController::class, 'storeValue'])
        ->name('user.directories.values.store');

    Route::post('/directory-values/{value}', [UserDirectoryController::class, 'updateValue'])
        ->name('user.directory-values.update');

    Route::delete('/directory-values/{value}', [UserDirectoryController::class, 'destroyValue'])
        ->name('user.directory-values.destroy');

    Route::get('/directory-templates', [DirectoryTemplateController::class, 'index'])
        ->name('user.directory-templates.index');

    Route::get('/directory-templates/list', [DirectoryTemplateController::class, 'list'])
        ->name('user.directory-templates.list');

    Route::post('/directory-templates', [DirectoryTemplateController::class, 'store'])
        ->name('user.directory-templates.store');

    Route::post('/directory-templates/import-template', [DirectoryTemplateController::class, 'importTemplate'])
        ->name('user.directory-templates.import-template');

    Route::get('/directory-templates/{directory}', [DirectoryTemplateController::class, 'show'])
        ->name('user.directory-templates.show');

    Route::post('/directory-templates/{directory}', [DirectoryTemplateController::class, 'update'])
        ->name('user.directory-templates.update');

    Route::delete('/directory-templates/{directory}', [DirectoryTemplateController::class, 'destroy'])
        ->name('user.directory-templates.destroy');

    Route::get('/directory-templates/{directory}/export-template', [DirectoryTemplateController::class, 'exportTemplate'])
        ->name('user.directory-templates.export-template');

    Route::get('/directory-templates/{directory}/values', [DirectoryTemplateController::class, 'valuesList'])
        ->name('user.directory-templates.values.list');

    Route::post('/directory-templates/{directory}/values', [DirectoryTemplateController::class, 'valueStore'])
        ->name('user.directory-templates.values.store');

    Route::post('/directory-templates/{directory}/import-csv', [DirectoryTemplateController::class, 'importCsv'])
        ->name('user.directory-templates.import.csv');

    Route::get('/directory-templates/{directory}/print', [DirectoryTemplateController::class, 'print'])
        ->name('user.directory-templates.print');

    Route::get('/directory-templates/{directory}/barcodes', [DirectoryTemplateController::class, 'printBarcodes'])
        ->name('user.directory-templates.barcodes');

    Route::get('/directory-template-values/{value}', [DirectoryTemplateController::class, 'valueShow'])
        ->name('user.directory-template-values.show');

    Route::post('/directory-template-values/{value}', [DirectoryTemplateController::class, 'valueUpdate'])
        ->name('user.directory-template-values.update');

    Route::delete('/directory-template-values/{value}', [DirectoryTemplateController::class, 'valueDestroy'])
        ->name('user.directory-template-values.destroy');

    Route::get('/journal-templates', [JournalTemplateEditorController::class, 'index'])
        ->name('user.journal-templates.index');

    Route::get('/journal-templates/list', [JournalTemplateEditorController::class, 'list'])
        ->name('user.journal-templates.list');

    Route::post('/journal-templates', [JournalTemplateEditorController::class, 'store'])
        ->name('user.journal-templates.store');

    Route::post('/journal-templates/import', [JournalTemplateEditorController::class, 'import'])
        ->name('user.journal-templates.import');

    Route::get('/journal-templates/{journalTemplate}', [JournalTemplateEditorController::class, 'show'])
        ->name('user.journal-templates.show');

    Route::post('/journal-templates/{journalTemplate}', [JournalTemplateEditorController::class, 'update'])
        ->name('user.journal-templates.update');

    Route::delete('/journal-templates/{journalTemplate}', [JournalTemplateEditorController::class, 'destroy'])
        ->name('user.journal-templates.destroy');

    Route::get('/journal-templates/{journalTemplate}/export', [JournalTemplateEditorController::class, 'export'])
        ->name('user.journal-templates.export');

    Route::get('/journal-print-templates', [UserJournalPrintTemplateController::class, 'index'])
        ->name('user.journal-print-templates.index');

    Route::get('/journal-print-templates/list', [UserJournalPrintTemplateController::class, 'list'])
        ->name('user.journal-print-templates.list');

    Route::post('/journal-print-templates', [UserJournalPrintTemplateController::class, 'store'])
        ->name('user.journal-print-templates.store');

    Route::get('/journal-print-templates/{printTemplate}', [UserJournalPrintTemplateController::class, 'show'])
        ->name('user.journal-print-templates.show');

    Route::post('/journal-print-templates/{printTemplate}', [UserJournalPrintTemplateController::class, 'update'])
        ->name('user.journal-print-templates.update');

    Route::delete('/journal-print-templates/{printTemplate}', [UserJournalPrintTemplateController::class, 'destroy'])
        ->name('user.journal-print-templates.destroy');

});




Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'loginPage'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/divisions', [DivisionController::class, 'index'])->name('divisions.index');

    Route::get('/divisions/list', [DivisionController::class, 'list'])->name('divisions.list');
    Route::post('/divisions', [DivisionController::class, 'store'])->name('divisions.store');
    Route::get('/divisions/{division}', [DivisionController::class, 'show'])->name('divisions.show');
    Route::post('/divisions/{division}', [DivisionController::class, 'update'])->name('divisions.update');
    Route::delete('/divisions/{division}', [DivisionController::class, 'destroy'])->name('divisions.destroy');
    Route::middleware('superadmin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');

        Route::get('/users/list', [UserController::class, 'list'])->name('users.list');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
        Route::post('/users/{user}/permissions', [UserController::class, 'storePermission'])->name('users.permissions.store');
        Route::delete('/users/{user}/permissions/{permission}', [UserController::class, 'destroyPermission'])->name('users.permissions.destroy');


        Route::get('/directories', [DirectoryController::class, 'index'])->name('directories.index');

        Route::get('/directories/list', [DirectoryController::class, 'list'])->name('directories.list');
        Route::post('/directories', [DirectoryController::class, 'store'])->name('directories.store');
        Route::post('/directories/import-template', [DirectoryController::class, 'importTemplate'])->name('directories.import-template');

        Route::get('/directories/{directory}/values', [DirectoryController::class, 'valuesList'])->name('directories.values.list');
        Route::post('/directories/{directory}/values', [DirectoryController::class, 'valueStore'])->name('directories.values.store');
        Route::post('/directories/{directory}/import-csv', [DirectoryController::class, 'importCsv'])->name('directories.import.csv');
        Route::get('/directories/{directory}/print', [DirectoryController::class, 'print'])->name('directories.print');
        Route::get('/directories/{directory}/barcodes', [DirectoryController::class, 'printBarcodes'])->name('directories.barcodes');

        Route::get('/directory-values/{value}', [DirectoryController::class, 'valueShow'])->name('directory-values.show');
        Route::post('/directory-values/{value}', [DirectoryController::class, 'valueUpdate'])->name('directory-values.update');
        Route::delete('/directory-values/{value}', [DirectoryController::class, 'valueDestroy'])->name('directory-values.destroy');

        Route::get('/directories/{directory}', [DirectoryController::class, 'show'])->name('directories.show');
        Route::post('/directories/{directory}', [DirectoryController::class, 'update'])->name('directories.update');
        Route::delete('/directories/{directory}', [DirectoryController::class, 'destroy'])->name('directories.destroy');
        Route::get('/directories/{directory}/export-template', [DirectoryController::class, 'exportTemplate'])->name('directories.export-template');
        Route::get('/journal-templates', [JournalTemplateController::class, 'index'])->name('journal-templates.index');

        Route::get('/journal-templates/list', [JournalTemplateController::class, 'list'])->name('journal-templates.list');
        Route::post('/journal-templates', [JournalTemplateController::class, 'store'])->name('journal-templates.store');
        Route::post('/journal-templates/import', [JournalTemplateController::class, 'import'])->name('journal-templates.import');
        Route::get('/journal-templates/{journalTemplate}', [JournalTemplateController::class, 'show'])->name('journal-templates.show');
        Route::post('/journal-templates/{journalTemplate}', [JournalTemplateController::class, 'update'])->name('journal-templates.update');
        Route::delete('/journal-templates/{journalTemplate}', [JournalTemplateController::class, 'destroy'])->name('journal-templates.destroy');
        Route::get('/journal-templates/{journalTemplate}/export', [JournalTemplateController::class, 'export'])->name('journal-templates.export');

        Route::get('/journal-print-templates', [JournalPrintTemplateController::class, 'index'])->name('journal-print-templates.index');
        Route::get('/journal-print-templates/list', [JournalPrintTemplateController::class, 'list'])->name('journal-print-templates.list');
        Route::post('/journal-print-templates', [JournalPrintTemplateController::class, 'store'])->name('journal-print-templates.store');
        Route::get('/journal-print-templates/{printTemplate}', [JournalPrintTemplateController::class, 'show'])->name('journal-print-templates.show');
        Route::post('/journal-print-templates/{printTemplate}', [JournalPrintTemplateController::class, 'update'])->name('journal-print-templates.update');
        Route::delete('/journal-print-templates/{printTemplate}', [JournalPrintTemplateController::class, 'destroy'])->name('journal-print-templates.destroy');

        Route::get('/reports', [ReportTemplateController::class, 'index'])->name('reports.index');
        Route::get('/reports/list', [ReportTemplateController::class, 'list'])->name('reports.list');
        Route::post('/reports', [ReportTemplateController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}', [ReportTemplateController::class, 'show'])->name('reports.show');
        Route::post('/reports/{report}', [ReportTemplateController::class, 'update'])->name('reports.update');
        Route::delete('/reports/{report}', [ReportTemplateController::class, 'destroy'])->name('reports.destroy');
        Route::get('/database', [DatabaseMaintenanceController::class, 'index'])->name('database.index');
        Route::get('/database/export', [DatabaseMaintenanceController::class, 'export'])->name('database.export');
        Route::post('/database/import', [DatabaseMaintenanceController::class, 'import'])->name('database.import');
    });
});
