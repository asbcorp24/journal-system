<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Admin\JournalTemplateController as AdminJournalTemplateController;
use App\Models\JournalTemplate;

class JournalTemplateEditorController extends AdminJournalTemplateController
{
    protected function authorizePageAccess(): void
    {
        if (session('user_role') !== 'admin' || !session('can_edit_journal_templates')) {
            abort(403, 'Нет доступа к настройке шаблонов журналов');
        }
    }

    protected function authorizeJournalTemplateAccess(?JournalTemplate $journalTemplate): void
    {
        parent::authorizeJournalTemplateAccess($journalTemplate);

        if ((int) $journalTemplate->created_by !== (int) session('user_id')) {
            abort(403, 'Нет доступа к этому шаблону журнала');
        }
    }

    protected function visibleJournalTemplatesQuery()
    {
        return JournalTemplate::query()
            ->where('created_by', session('user_id'));
    }

    protected function currentJournalTemplateCreatorId(): ?int
    {
        return (int) session('user_id');
    }

    protected function canModifyUsedJournalTemplate(): bool
    {
        return false;
    }

    protected function journalTemplatePageLayout(): string
    {
        return 'user.layouts.app';
    }

    protected function journalTemplatePageTitle(): string
    {
        return 'Мои шаблоны журналов';
    }

    protected function journalTemplateRoutes(): array
    {
        return [
            'list' => route('user.journal-templates.list'),
            'store' => route('user.journal-templates.store'),
            'template' => url('/journal-templates/__ID__'),
            'templateExport' => url('/journal-templates/__ID__/export'),
            'templateImport' => route('user.journal-templates.import'),
        ];
    }
}
