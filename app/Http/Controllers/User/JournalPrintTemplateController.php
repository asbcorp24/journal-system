<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Admin\JournalPrintTemplateController as AdminJournalPrintTemplateController;
use App\Models\JournalTemplate;

class JournalPrintTemplateController extends AdminJournalPrintTemplateController
{
    protected function authorizePageAccess(): void
    {
        if (session('user_role') !== 'admin' || !session('can_edit_journal_templates')) {
            abort(403, 'Нет доступа к шаблонам печати журналов');
        }
    }

    protected function authorizeJournalAccess(JournalTemplate $journalTemplate): void
    {
        if ((int) $journalTemplate->created_by !== (int) session('user_id')) {
            abort(403, 'Нет доступа к этому журналу');
        }
    }

    protected function visibleJournalTemplatesQuery()
    {
        return JournalTemplate::query()
            ->where('created_by', session('user_id'));
    }

    protected function applyVisibleJournalScope($query): void
    {
        $query->where('created_by', session('user_id'));
    }

    protected function currentCreatorId(): ?int
    {
        return (int) session('user_id');
    }

    protected function pageLayout(): string
    {
        return 'user.layouts.app';
    }

    protected function pageTitle(): string
    {
        return 'Мои шаблоны печати журналов';
    }

    protected function pageRoutes(): array
    {
        return [
            'list' => route('user.journal-print-templates.list'),
            'store' => route('user.journal-print-templates.store'),
            'template' => url('/journal-print-templates/__ID__'),
        ];
    }
}
