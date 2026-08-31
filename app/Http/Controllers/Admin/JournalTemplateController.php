<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\Division;
use App\Models\JournalTemplate;
use App\Models\User;
use App\Support\DirectoryAccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JournalTemplateController extends Controller
{
    public function index()
    {
        $this->authorizePageAccess();

        $divisions = Division::orderBy('name')->get();
        $directories = Directory::orderBy('name')->get();
        $approvers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'division_id']);
        $journalTemplateRoutes = $this->journalTemplateRoutes();
        $journalTemplatePageLayout = $this->journalTemplatePageLayout();
        $journalTemplatePageTitle = $this->journalTemplatePageTitle();
        $journalTemplateCanModifyUsed = $this->canModifyUsedJournalTemplate();

        return view('admin.journal-templates.index', compact(
            'divisions',
            'directories',
            'approvers',
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
            ->with(['divisions', 'creator', 'approver.division'])
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
                'approver_user_id' => $validated['approver_user_id'] ?? null,
            ]);

            $template->divisions()->sync($validated['division_ids'] ?? []);
            DirectoryAccessScope::grantDivisionAccessToReferencedDirectoriesFromJournal(
                $template,
                $validated['division_ids'] ?? []
            );

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
                'approver_user_id' => $journalTemplate->approver_user_id,
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
                'approver_user_id' => $validated['approver_user_id'] ?? null,
            ]);

            $journalTemplate->divisions()->sync($validated['division_ids'] ?? []);
            DirectoryAccessScope::grantDivisionAccessToReferencedDirectoriesFromJournal(
                $journalTemplate,
                $validated['division_ids'] ?? []
            );
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

    public function export(JournalTemplate $journalTemplate)
    {
        $this->authorizeJournalTemplateAccess($journalTemplate);
        $journalTemplate->load('divisions');

        $payload = [
            'kind' => 'journal_template',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'template' => [
                'name' => $journalTemplate->name,
                'code' => $journalTemplate->code,
                'description' => $journalTemplate->description,
                'is_active' => (bool) $journalTemplate->is_active,
                'approver' => $journalTemplate->approver
                    ? [
                        'email' => $journalTemplate->approver->email,
                        'name' => $journalTemplate->approver->name,
                    ]
                    : null,
                'divisions' => $journalTemplate->divisions->map(function (Division $division) {
                    return [
                        'name' => $division->name,
                    ];
                })->values()->all(),
                'schema' => $this->exportJournalSchema($journalTemplate->schema ?? []),
            ],
        ];

        $fileName = 'journal_template_' . Str::slug($journalTemplate->code ?: $journalTemplate->name, '_') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizePageAccess();

        $request->validate([
            'template_file' => ['required', 'file', 'mimes:json,txt'],
        ]);

        $payload = $this->readImportPayload($request, 'journal_template');
        $template = $payload['template'] ?? [];
        $importedCode = $this->generateImportedCode($template['code'] ?? null);
        $input = [
            'name' => $this->generateImportedName((string) ($template['name'] ?? 'Журнал')),
            'code' => $importedCode,
            'description' => $template['description'] ?? null,
            'is_active' => !empty($template['is_active']),
            'approver_user_id' => $this->resolveApproverIdFromImport($template['approver'] ?? null),
            'division_ids' => $this->resolveDivisionIdsFromImport($template['divisions'] ?? []),
            'schema' => $this->importJournalSchema($template['schema'] ?? [], (string) ($template['code'] ?? ''), (string) ($importedCode ?? '')),
        ];

        $validated = $this->normalizeTemplateInput($input);

        $journalTemplate = DB::transaction(function () use ($validated, $input) {
            $template = JournalTemplate::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'schema' => $validated['schema'],
                'is_active' => !empty($input['is_active']),
                'created_by' => $this->currentJournalTemplateCreatorId(),
                'approver_user_id' => $validated['approver_user_id'] ?? null,
            ]);

            $template->divisions()->sync($validated['division_ids'] ?? []);
            DirectoryAccessScope::grantDivisionAccessToReferencedDirectoriesFromJournal(
                $template,
                $validated['division_ids'] ?? []
            );

            return $template;
        });

        return response()->json([
            'success' => true,
            'message' => 'Шаблон журнала импортирован',
            'template' => $journalTemplate,
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
            'templateExport' => url('/admin/journal-templates/__ID__/export'),
            'templateImport' => route('admin.journal-templates.import'),
        ];
    }

    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $this->normalizeTemplateInput($request->all(), $ignoreId);
    }

    private function normalizeTemplateInput(array $input, ?int $ignoreId = null): array
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
            'approver_user_id' => [
                'nullable',
                'exists:users,id',
            ],
            'division_ids.*' => [
                'exists:divisions,id',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];

        $validated = validator($input, $rules)->validate();
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

            if ($field['type'] === 'hidden') {
                $item['default_value'] = trim((string) ($field['default_value'] ?? ''));
                $item['filterable'] = false;
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

    private function resolveApproverIdFromImport($approver): ?int
    {
        if (!is_array($approver)) {
            return null;
        }

        $email = trim((string) ($approver['email'] ?? ''));
        $name = trim((string) ($approver['name'] ?? ''));

        if ($email !== '') {
            $id = User::query()->where('email', $email)->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        if ($name !== '') {
            $id = User::query()->where('name', $name)->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private function exportJournalSchema(array $schema): array
    {
        return collect($schema)->map(function ($field) {
            if (in_array($field['type'] ?? '', ['directory', 'directory_text'], true) && !empty($field['directory_id'])) {
                $directory = Directory::find((int) $field['directory_id']);

                $field['directory_ref'] = [
                    'code' => $directory?->code,
                    'name' => $directory?->name,
                ];
                unset($field['directory_id']);
            }

            return $field;
        })->values()->all();
    }

    private function importJournalSchema(array $schema, string $sourceCode = '', string $importedCode = ''): array
    {
        return collect($schema)->map(function ($field) use ($sourceCode, $importedCode) {
            if (!is_array($field)) {
                throw ValidationException::withMessages([
                    'template_file' => ['Некорректное описание поля в импортируемом журнале'],
                ]);
            }

            if (in_array($field['type'] ?? '', ['directory', 'directory_text'], true)) {
                $ref = $field['directory_ref'] ?? [];
                $directory = $this->findDirectoryByImportRef($ref);

                if (!$directory) {
                    $fieldLabel = $field['label'] ?? ($field['key'] ?? 'поле');
                    throw ValidationException::withMessages([
                        'template_file' => ["Для поля «{$fieldLabel}» не найден связанный справочник при импорте"],
                    ]);
                }

                $field['directory_id'] = $directory->id;
            }

            if (($field['type'] ?? '') === 'sql' && !empty($field['sql_query']) && $sourceCode !== '' && $importedCode !== '') {
                $field['sql_query'] = str_replace(
                    "code = '{$sourceCode}'",
                    "code = '{$importedCode}'",
                    (string) $field['sql_query']
                );
            }

            unset($field['directory_ref']);

            return $field;
        })->values()->all();
    }

    private function findDirectoryByImportRef($ref): ?Directory
    {
        if (!is_array($ref)) {
            return null;
        }

        $code = trim((string) ($ref['code'] ?? ''));
        $name = trim((string) ($ref['name'] ?? ''));

        if ($code !== '') {
            $directory = Directory::where('code', $code)->first();
            if ($directory) {
                return $directory;
            }
        }

        if ($name !== '') {
            return Directory::where('name', $name)->first();
        }

        return null;
    }

    private function resolveDivisionIdsFromImport(array $divisions): array
    {
        return collect($divisions)
            ->map(function ($division) {
                $name = trim((string) ($division['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return Division::where('name', $name)->value('id');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function generateImportedName(string $baseName): string
    {
        $baseName = trim($baseName) !== '' ? trim($baseName) : 'Журнал';
        $candidate = $baseName . ' (импорт)';
        $suffix = 2;

        while (JournalTemplate::where('name', $candidate)->exists()) {
            $candidate = $baseName . ' (импорт ' . $suffix . ')';
            $suffix++;
        }

        return $candidate;
    }

    private function generateImportedCode(?string $baseCode): ?string
    {
        $baseCode = trim((string) $baseCode);

        if ($baseCode === '') {
            return null;
        }

        $candidate = $baseCode . '_import';
        $suffix = 2;

        while (JournalTemplate::where('code', $candidate)->exists()) {
            $candidate = $baseCode . '_import_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function readImportPayload(Request $request, string $expectedKind): array
    {
        $file = $request->file('template_file');
        $path = $file?->getRealPath();

        if (!$path || !file_exists($path)) {
            throw ValidationException::withMessages([
                'template_file' => ['Файл импорта не найден'],
            ]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'template_file' => ['Не удалось прочитать JSON-файл'],
            ]);
        }

        if (($decoded['kind'] ?? null) !== $expectedKind) {
            throw ValidationException::withMessages([
                'template_file' => ['Выбран файл другого типа шаблона'],
            ]);
        }

        return $decoded;
    }

}
