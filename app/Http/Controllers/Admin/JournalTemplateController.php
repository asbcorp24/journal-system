<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\Division;
use App\Models\JournalTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JournalTemplateController extends Controller
{
    public function index()
    {
        $this->authorizePageAccess();

        $divisions = Division::orderBy('name')->get();
        $directories = Directory::orderBy('name')->get();
        $journalTemplateRoutes = $this->journalTemplateRoutes();
        $journalTemplatePageLayout = $this->journalTemplatePageLayout();
        $journalTemplatePageTitle = $this->journalTemplatePageTitle();
        $journalTemplateCanModifyUsed = $this->canModifyUsedJournalTemplate();

        return view('admin.journal-templates.index', compact(
            'divisions',
            'directories',
            'journalTemplateRoutes',
            'journalTemplatePageLayout',
            'journalTemplatePageTitle',
            'journalTemplateCanModifyUsed'
        ));
    }

    public function list(Request $request)
    {
        $this->authorizePageAccess();

        $query = $this->visibleJournalTemplatesQuery()
            ->with(['divisions', 'creator'])
            ->withCount(['entries as entries_count' => function ($query) {
                $query->withTrashed();
            }])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool)$request->is_active);
        }

        $templates = $query->paginate(10);

        return response()->json([
            'success' => true,
            'items' => $templates->items(),
            'pagination' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
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

        $template = DB::transaction(function () use ($validated, $request) {
            $template = JournalTemplate::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'schema' => $validated['schema'],
                'is_active' => $request->boolean('is_active'),
                'created_by' => $this->currentJournalTemplateCreatorId(),
            ]);

            $template->divisions()->sync($validated['division_ids'] ?? []);

            return $template;
        });

        return response()->json([
            'success' => true,
            'message' => 'Журнал создан',
            'template' => $template,
        ]);
    }

    public function show(JournalTemplate $journalTemplate)
    {
        $this->authorizeJournalTemplateAccess($journalTemplate);
        $journalTemplate->load('divisions');

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $journalTemplate->id,
                'name' => $journalTemplate->name,
                'code' => $journalTemplate->code,
                'description' => $journalTemplate->description,
                'schema' => $journalTemplate->schema ?? [],
                'is_active' => $journalTemplate->is_active,
                'division_ids' => $journalTemplate->divisions->pluck('id')->values(),
            ],
        ]);
    }

    public function update(Request $request, JournalTemplate $journalTemplate)
    {
        $this->authorizeJournalTemplateAccess($journalTemplate);

        if (!$this->canModifyUsedJournalTemplate() && $journalTemplate->entries()->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя редактировать шаблон журнала, потому что в нём уже есть записи',
            ], 422);
        }

        $validated = $this->validateTemplate($request, $journalTemplate->id);

        DB::transaction(function () use ($journalTemplate, $validated, $request) {
            $journalTemplate->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'schema' => $validated['schema'],
                'is_active' => $request->boolean('is_active'),
            ]);

            $journalTemplate->divisions()->sync($validated['division_ids'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Журнал обновлён',
        ]);
    }

    public function destroy(JournalTemplate $journalTemplate)
    {
        $this->authorizeJournalTemplateAccess($journalTemplate);

        if (!$this->canModifyUsedJournalTemplate() && $journalTemplate->entries()->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить журнал, потому что в нём уже есть записи',
            ], 422);
        }

        $journalTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Журнал удалён',
        ]);
    }

    protected function authorizePageAccess(): void
    {
    }

    protected function authorizeJournalTemplateAccess(?JournalTemplate $journalTemplate): void
    {
        if (!$journalTemplate) {
            abort(404);
        }
    }

    protected function visibleJournalTemplatesQuery()
    {
        return JournalTemplate::query();
    }

    protected function currentJournalTemplateCreatorId(): ?int
    {
        return null;
    }

    protected function canModifyUsedJournalTemplate(): bool
    {
        return true;
    }

    protected function journalTemplatePageLayout(): string
    {
        return 'admin.layouts.app';
    }

    protected function journalTemplatePageTitle(): string
    {
        return 'Конструктор журналов';
    }

    protected function journalTemplateRoutes(): array
    {
        return [
            'list' => route('admin.journal-templates.list'),
            'store' => route('admin.journal-templates.store'),
            'template' => url('/admin/journal-templates/__ID__'),
        ];
    }

    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('journal_templates', 'name')->ignore($ignoreId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('journal_templates', 'code')->ignore($ignoreId),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'schema' => [
                'required',
                'array',
                'min:1',
            ],
            'schema.*.key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'schema.*.label' => [
                'required',
                'string',
                'max:255',
            ],
            'schema.*.type' => [
                'required',
                Rule::in([
                    'string',
                    'number',
                    'date',
                    'time',
                    'hidden',
                    'list',
                    'directory',
                    'directory_text',
                    'calc',
                    'sql',
                ]),
            ],
            'schema.*.tab' => [
                'nullable',
                'string',
                'max:100',
            ],
            'schema.*.required' => [
                'nullable',
                'boolean',
            ],
            'schema.*.filterable' => [
                'nullable',
                'boolean',
            ],
            'schema.*.directory_id' => [
                'nullable',
                'exists:directories,id',
            ],
            'schema.*.directory_display_field' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'schema.*.options' => [
                'nullable',
                'array',
            ],
            'schema.*.options.*' => [
                'nullable',
                'string',
                'max:255',
            ],
            'schema.*.formula' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'schema.*.default_value' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'schema.*.sql_query' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'schema.*.validation' => [
                'nullable',
                'array',
            ],
            'schema.*.validation.min' => [
                'nullable',
                'numeric',
            ],
            'schema.*.validation.max' => [
                'nullable',
                'numeric',
            ],
            'schema.*.validation.greater_than_field' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'schema.*.validation.less_than_field' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'division_ids' => [
                'nullable',
                'array',
            ],
            'division_ids.*' => [
                'exists:divisions,id',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];

        $validated = $request->validate($rules);
        $keys = collect($validated['schema'])->pluck('key')->toArray();

        if (count($keys) !== count(array_unique($keys))) {
            abort(response()->json([
                'success' => false,
                'message' => 'Ключи полей не должны повторяться',
            ], 422));
        }

        foreach ($validated['schema'] as $field) {
            $validation = $field['validation'] ?? [];

            if (($field['type'] ?? '') === 'hidden' && !empty($field['required']) && trim((string) ($field['default_value'] ?? '')) === '') {
                abort(response()->json([
                    'success' => false,
                    'message' => "У скрытого поля «{$field['label']}» должно быть значение по умолчанию",
                ], 422));
            }

            if (!empty($validation['greater_than_field']) && !in_array($validation['greater_than_field'], $keys, true)) {
                abort(response()->json([
                    'success' => false,
                    'message' => "В ограничении поля «{$field['label']}» указано несуществующее поле: {$validation['greater_than_field']}",
                ], 422));
            }

            if (!empty($validation['less_than_field']) && !in_array($validation['less_than_field'], $keys, true)) {
                abort(response()->json([
                    'success' => false,
                    'message' => "В ограничении поля «{$field['label']}» указано несуществующее поле: {$validation['less_than_field']}",
                ], 422));
            }
        }

        $schema = [];

        foreach ($validated['schema'] as $field) {
            $item = [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'tab' => trim((string)($field['tab'] ?? '')),
                'required' => !empty($field['required']),
                'filterable' => !empty($field['filterable']),
            ];

            if (in_array($field['type'], ['number', 'calc'], true)) {
                $validation = $field['validation'] ?? [];
                $cleanValidation = [];

                if (isset($validation['min']) && $validation['min'] !== '') {
                    $cleanValidation['min'] = $validation['min'] + 0;
                }

                if (isset($validation['max']) && $validation['max'] !== '') {
                    $cleanValidation['max'] = $validation['max'] + 0;
                }

                if (!empty($validation['greater_than_field'])) {
                    $cleanValidation['greater_than_field'] = $validation['greater_than_field'];
                }

                if (!empty($validation['less_than_field'])) {
                    $cleanValidation['less_than_field'] = $validation['less_than_field'];
                }

                if (!empty($cleanValidation)) {
                    $item['validation'] = $cleanValidation;
                }
            }

            if (in_array($field['type'], ['directory', 'directory_text'], true)) {
                if (empty($field['directory_id'])) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Для поля справочника необходимо выбрать справочник',
                    ], 422));
                }

                $directory = Directory::find((int)$field['directory_id']);
                $directorySchema = $directory?->schema ?? [];
                $displayField = $field['directory_display_field'] ?? null;

                if (!empty($directorySchema)) {
                    if (empty($displayField)) {
                        abort(response()->json([
                            'success' => false,
                            'message' => "Для поля «{$field['label']}» нужно выбрать отображаемое поле справочника",
                        ], 422));
                    }

                    $existsInSchema = collect($directorySchema)->contains(function ($directoryField) use ($displayField) {
                        return ($directoryField['key'] ?? null) === $displayField;
                    });

                    if (!$existsInSchema) {
                        abort(response()->json([
                            'success' => false,
                            'message' => "Для поля «{$field['label']}» выбрано несуществующее поле справочника",
                        ], 422));
                    }
                }

                $item['directory_id'] = (int)$field['directory_id'];

                if (!empty($displayField)) {
                    $item['directory_display_field'] = $displayField;
                }
            }

            if ($field['type'] === 'list') {
                $options = $field['options'] ?? [];
                $options = array_values(array_filter($options, function ($value) {
                    return trim((string)$value) !== '';
                }));

                if (count($options) === 0) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Для поля типа список нужно добавить варианты',
                    ], 422));
                }

                $item['options'] = $options;
            }

            if ($field['type'] === 'calc') {
                $item['formula'] = $field['formula'] ?? '';
            }

            if ($field['type'] === 'sql') {
                $sqlQuery = trim((string)($field['sql_query'] ?? ''));

                if ($sqlQuery === '') {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Для поля «{$field['label']}» нужно указать SQL-запрос",
                    ], 422));
                }

                $item['sql_query'] = $sqlQuery;
            }

            $schema[] = $item;
        }

        $validated['schema'] = $schema;

        return $validated;
    }

}
