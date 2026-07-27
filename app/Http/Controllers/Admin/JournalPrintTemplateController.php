<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalPrintTemplate;
use App\Models\JournalTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalPrintTemplateController extends Controller
{
    public function index()
    {
        $this->authorizePageAccess();

        $journals = $this->visibleJournalTemplatesQuery()
            ->with('creator')
            ->orderBy('name')
            ->get();
        $journalsForScript = $journals
            ->map(function ($journal) {
                return [
                    'id' => $journal->id,
                    'name' => $journal->name,
                    'schema' => $journal->schema ?? [],
                ];
            })
            ->values()
            ->all();

        return view('admin.journal-print-templates.index', [
            'layout' => $this->pageLayout(),
            'pageTitle' => $this->pageTitle(),
            'routes' => $this->pageRoutes(),
            'journals' => $journals,
            'journalsForScript' => $journalsForScript,
        ]);
    }

    public function list(Request $request)
    {
        $this->authorizePageAccess();

        $query = JournalPrintTemplate::with(['journalTemplate', 'creator'])
            ->whereHas('journalTemplate', function ($journalQuery) {
                $this->applyVisibleJournalScope($journalQuery);
            })
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('journalTemplate', function ($journalQuery) use ($search) {
                        $journalQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $templates = $query->paginate(10);

        return response()->json([
            'success' => true,
            'items' => $templates->items(),
            'pagination' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'total' => $templates->total(),
                'from' => $templates->firstItem(),
                'to' => $templates->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePageAccess();

        $validated = $this->validateTemplate($request);
        $journal = JournalTemplate::findOrFail($validated['journal_template_id']);
        $this->authorizeJournalAccess($journal);

        $template = JournalPrintTemplate::create(array_merge($validated, [
            'created_by' => $this->currentCreatorId(),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Шаблон печати создан',
            'template' => $template,
        ]);
    }

    public function show(JournalPrintTemplate $printTemplate)
    {
        $this->authorizeTemplateAccess($printTemplate);

        return response()->json([
            'success' => true,
            'template' => $printTemplate->load(['journalTemplate', 'creator']),
        ]);
    }

    public function update(Request $request, JournalPrintTemplate $printTemplate)
    {
        $this->authorizeTemplateAccess($printTemplate);

        $validated = $this->validateTemplate($request);
        $journal = JournalTemplate::findOrFail($validated['journal_template_id']);
        $this->authorizeJournalAccess($journal);

        $printTemplate->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Шаблон печати обновлён',
        ]);
    }

    public function destroy(JournalPrintTemplate $printTemplate)
    {
        $this->authorizeTemplateAccess($printTemplate);
        $printTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Шаблон печати удалён',
        ]);
    }

    protected function authorizePageAccess(): void
    {
    }

    protected function authorizeJournalAccess(JournalTemplate $journalTemplate): void
    {
    }

    protected function authorizeTemplateAccess(JournalPrintTemplate $printTemplate): void
    {
        $this->authorizeJournalAccess($printTemplate->journalTemplate);
    }

    protected function visibleJournalTemplatesQuery()
    {
        return JournalTemplate::query();
    }

    protected function applyVisibleJournalScope($query): void
    {
    }

    protected function currentCreatorId(): ?int
    {
        return session('admin_id');
    }

    protected function pageLayout(): string
    {
        return 'admin.layouts.app';
    }

    protected function pageTitle(): string
    {
        return 'Шаблоны печати журналов';
    }

    protected function pageRoutes(): array
    {
        return [
            'list' => route('admin.journal-print-templates.list'),
            'store' => route('admin.journal-print-templates.store'),
            'template' => url('/admin/journal-print-templates/__ID__'),
        ];
    }

    private function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'journal_template_id' => ['required', 'exists:journal_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'settings.orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
            'settings.show_signatures' => ['nullable', 'boolean'],
            'settings.columns' => ['nullable', 'array'],
            'settings.columns.*.type' => ['required', Rule::in(['system', 'field'])],
            'settings.columns.*.key' => ['required', 'string', 'max:100'],
            'settings.columns.*.label' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $journal = JournalTemplate::findOrFail($validated['journal_template_id']);
        $allowedColumns = $this->allowedColumnKeys($journal);
        $columns = $validated['settings']['columns'] ?? [];

        if (count($columns) === 0) {
            abort(response()->json([
                'success' => false,
                'message' => 'Выберите хотя бы одну колонку для печати',
            ], 422));
        }

        foreach ($columns as $column) {
            $compoundKey = ($column['type'] ?? '') . ':' . ($column['key'] ?? '');

            if (!in_array($compoundKey, $allowedColumns, true)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'В шаблоне выбрано поле, которого нет в журнале',
                ], 422));
            }
        }

        $validated['settings'] = [
            'orientation' => $validated['settings']['orientation'] ?? 'landscape',
            'show_signatures' => !empty($validated['settings']['show_signatures']),
            'columns' => array_values($columns),
        ];
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function allowedColumnKeys(JournalTemplate $journal): array
    {
        $keys = [
            'system:number',
            'system:entry_date',
            'system:created_by',
            'system:division',
            'system:status',
            'system:checked_by',
            'system:last_comment',
        ];

        foreach ($journal->schema ?? [] as $field) {
            if (!empty($field['key'])) {
                $keys[] = 'field:' . $field['key'];
            }
        }

        return $keys;
    }
}
