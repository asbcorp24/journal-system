<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Admin\DirectoryController as AdminDirectoryController;
use App\Models\Directory;
use App\Models\User;

class DirectoryTemplateController extends AdminDirectoryController
{
    protected function authorizePageAccess(): void
    {
        $user = $this->currentUser();

        if (!$user || $user->role !== 'admin' || !$user->can_edit_directory_templates) {
            abort(403, 'Нет доступа к настройке шаблонов справочников');
        }
    }

    protected function authorizeDirectoryAccess(?Directory $directory): void
    {
        $this->authorizePageAccess();

        if (!$directory) {
            abort(404);
        }

        if ((int) $directory->created_by !== (int) session('user_id')) {
            abort(403, 'Можно настраивать только свои шаблоны справочников');
        }
    }

    protected function visibleDirectoriesQuery()
    {
        return Directory::query()->where('created_by', session('user_id'));
    }

    protected function currentDirectoryCreatorId(): ?int
    {
        return session('user_id') ? (int) session('user_id') : null;
    }

    protected function canModifyFilledDirectory(): bool
    {
        return false;
    }

    protected function directoryPageLayout(): string
    {
        return 'user.layouts.app';
    }

    protected function directoryPageTitle(): string
    {
        return 'Мои шаблоны справочников';
    }

    protected function directoryRoutes(): array
    {
        return [
            'list' => route('user.directory-templates.list'),
            'store' => route('user.directory-templates.store'),
            'directory' => url('/directory-templates/__ID__'),
            'directoryValues' => url('/directory-templates/__ID__/values'),
            'directoryImportCsv' => url('/directory-templates/__ID__/import-csv'),
            'directoryPrint' => url('/directory-templates/__ID__/print'),
            'directoryBarcodes' => url('/directory-templates/__ID__/barcodes'),
            'value' => url('/directory-template-values/__ID__'),
        ];
    }

    private function currentUser(): ?User
    {
        if (!session('user_id')) {
            return null;
        }

        $user = User::query()->find(session('user_id'));

        if ($user) {
            session([
                'can_edit_directory_templates' => $user->can_edit_directory_templates,
            ]);
        }

        return $user;
    }
}
