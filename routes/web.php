<?php
use App\Http\Controllers\Admin\ReportTemplateController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\DirectoryController;
use App\Http\Controllers\Admin\JournalTemplateController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\JournalController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\ReviewController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ChartController;
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
        ->name('user.journals.entries.show');

    Route::post('/journals/{journal}/entries/{entry}', [JournalController::class, 'update'])
        ->name('user.journals.entries.update');

    Route::delete('/journals/{journal}/entries/{entry}', [JournalController::class, 'destroy'])
        ->name('user.journals.entries.destroy');


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


        Route::get('/directories', [DirectoryController::class, 'index'])->name('directories.index');

        Route::get('/directories/list', [DirectoryController::class, 'list'])->name('directories.list');
        Route::post('/directories', [DirectoryController::class, 'store'])->name('directories.store');

        Route::get('/directories/{directory}/values', [DirectoryController::class, 'valuesList'])->name('directories.values.list');
        Route::post('/directories/{directory}/values', [DirectoryController::class, 'valueStore'])->name('directories.values.store');
        Route::post('/directories/{directory}/import-csv', [DirectoryController::class, 'importCsv'])->name('directories.import.csv');

        Route::get('/directory-values/{value}', [DirectoryController::class, 'valueShow'])->name('directory-values.show');
        Route::post('/directory-values/{value}', [DirectoryController::class, 'valueUpdate'])->name('directory-values.update');
        Route::delete('/directory-values/{value}', [DirectoryController::class, 'valueDestroy'])->name('directory-values.destroy');

        Route::get('/directories/{directory}', [DirectoryController::class, 'show'])->name('directories.show');
        Route::post('/directories/{directory}', [DirectoryController::class, 'update'])->name('directories.update');
        Route::delete('/directories/{directory}', [DirectoryController::class, 'destroy'])->name('directories.destroy');

        Route::get('/journal-templates', [JournalTemplateController::class, 'index'])->name('journal-templates.index');

        Route::get('/journal-templates/list', [JournalTemplateController::class, 'list'])->name('journal-templates.list');
        Route::post('/journal-templates', [JournalTemplateController::class, 'store'])->name('journal-templates.store');
        Route::get('/journal-templates/{journalTemplate}', [JournalTemplateController::class, 'show'])->name('journal-templates.show');
        Route::post('/journal-templates/{journalTemplate}', [JournalTemplateController::class, 'update'])->name('journal-templates.update');
        Route::delete('/journal-templates/{journalTemplate}', [JournalTemplateController::class, 'destroy'])->name('journal-templates.destroy');


        Route::get('/reports', [ReportTemplateController::class, 'index'])->name('reports.index');
        Route::get('/reports/list', [ReportTemplateController::class, 'list'])->name('reports.list');
        Route::post('/reports', [ReportTemplateController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}', [ReportTemplateController::class, 'show'])->name('reports.show');
        Route::post('/reports/{report}', [ReportTemplateController::class, 'update'])->name('reports.update');
        Route::delete('/reports/{report}', [ReportTemplateController::class, 'destroy'])->name('reports.destroy');
    });
});
