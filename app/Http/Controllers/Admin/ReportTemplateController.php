<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\JournalTemplate;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportTemplateController extends Controller
{
    public function index()
    {
        $directories = Directory::orderBy('name')->get();
        $journals = JournalTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'schema']);

        return view('admin.reports.index', compact('directories', 'journals'));
    }

    public function list(Request $request)
    {
        $query = ReportTemplate::query()->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate(10);

        return response()->json([
            'success' => true,
            'items' => $reports->items(),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateReport($request);

        $report = ReportTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Отчёт создан',
            'report' => $report,
        ]);
    }

    public function show(ReportTemplate $report)
    {
        return response()->json([
            'success' => true,
            'report' => $report,
        ]);
    }

    public function update(Request $request, ReportTemplate $report)
    {
        $validated = $this->validateReport($request, $report->id);

        $report->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Отчёт обновлён',
        ]);
    }

    public function destroy(ReportTemplate $report)
    {
        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Отчёт удалён',
        ]);
    }

    public function export(ReportTemplate $report)
    {
        $payload = [
            'kind' => 'report_template',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'template' => [
                'name' => $report->name,
                'code' => $report->code,
                'description' => $report->description,
                'sql_query' => $report->sql_query,
                'params_schema' => $this->exportParamsSchema($report->params_schema ?? []),
                'print_settings' => $report->print_settings ?? [],
                'is_active' => (bool) $report->is_active,
            ],
        ];

        $fileName = 'report_template_' . Str::slug($report->code ?: $report->name, '_') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'template_file' => ['required', 'file', 'mimes:json,txt'],
        ]);

        $payload = $this->readImportPayload($request, 'report_template');
        $template = $payload['template'] ?? [];
        $input = [
            'name' => $this->generateImportedName((string) ($template['name'] ?? 'Отчёт')),
            'code' => $this->generateImportedCode($template['code'] ?? null),
            'description' => $template['description'] ?? null,
            'sql_query' => $template['sql_query'] ?? '',
            'params_schema' => $this->importParamsSchema($template['params_schema'] ?? []),
            'print_settings' => $template['print_settings'] ?? [],
            'is_active' => !empty($template['is_active']),
        ];

        $validated = $this->validateReport(new Request($input));

        $report = ReportTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Шаблон отчёта импортирован',
            'report' => $report,
        ]);
    }

    private function validateReport(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('report_templates', 'code')->ignore($ignoreId),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'sql_query' => [
                'required',
                'string',
            ],
            'params_schema' => [
                'nullable',
                'array',
            ],
            'print_settings' => [
                'nullable',
                'array',
            ],
            'print_settings.orientation' => [
                'nullable',
                Rule::in(['portrait', 'landscape']),
            ],
            'print_settings.title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'print_settings.body_html' => [
                'nullable',
                'string',
                'max:50000',
            ],
            'params_schema.*.key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'params_schema.*.label' => [
                'required',
                'string',
                'max:255',
            ],
            'params_schema.*.type' => [
                'required',
                Rule::in([
                    'string',
                    'number',
                    'date',
                    'time',
                    'list',
                    'directory',
                    'directory_text',
                    'calc',
                ]),
            ],
            'params_schema.*.required' => [
                'nullable',
                'boolean',
            ],
            'params_schema.*.compare_operator' => [
                'nullable',
                Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte']),
            ],
            'params_schema.*.directory_id' => [
                'nullable',
                'exists:directories,id',
            ],
            'params_schema.*.source' => [
                'nullable',
                'string',
                'max:100',
            ],
            'params_schema.*.options' => [
                'nullable',
                'array',
            ],
            'params_schema.*.options.*' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $sql = trim($validated['sql_query']);

        if (!$this->isSafeSelectSql($sql)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Разрешены только SELECT-запросы. INSERT, UPDATE, DELETE, DROP и другие команды запрещены.',
            ], 422));
        }

        $schema = $validated['params_schema'] ?? [];
        $keys = collect($schema)->pluck('key')->toArray();

        if (count($keys) !== count(array_unique($keys))) {
            abort(response()->json([
                'success' => false,
                'message' => 'Ключи параметров не должны повторяться',
            ], 422));
        }

        foreach ($schema as &$field) {
            $field['required'] = !empty($field['required']);

            if (($field['type'] ?? '') === 'list') {
                $field['options'] = array_values(array_filter($field['options'] ?? []));
            }
        }

        $validated['params_schema'] = $schema;

        if ($this->supportsPrintSettings()) {
            $validated['print_settings'] = [
                'orientation' => $validated['print_settings']['orientation'] ?? 'portrait',
                'title' => trim((string) ($validated['print_settings']['title'] ?? '')),
                'body_html' => trim((string) ($validated['print_settings']['body_html'] ?? '')),
            ];
        } else {
            unset($validated['print_settings']);
        }

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function supportsPrintSettings(): bool
    {
        static $supportsPrintSettings = null;

        if ($supportsPrintSettings !== null) {
            return $supportsPrintSettings;
        }

        return $supportsPrintSettings = Schema::hasColumn('report_templates', 'print_settings');
    }

    private function isSafeSelectSql(string $sql): bool
    {
        $clean = trim($sql);

        if (!preg_match('/^select\s+/i', $clean)) {
            return false;
        }

        $forbidden = [
            'insert ',
            'update ',
            'delete ',
            'drop ',
            'alter ',
            'truncate ',
            'create ',
            'replace ',
            'attach ',
            'detach ',
            'pragma ',
            'vacuum ',
        ];

        $lower = strtolower($clean);

        foreach ($forbidden as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }

        return true;
    }

    private function exportParamsSchema(array $schema): array
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

    private function importParamsSchema(array $schema): array
    {
        return collect($schema)->map(function ($field) {
            if (!is_array($field)) {
                throw ValidationException::withMessages([
                    'template_file' => ['Некорректное описание параметра в импортируемом отчёте'],
                ]);
            }

            if (in_array($field['type'] ?? '', ['directory', 'directory_text'], true)) {
                $ref = $field['directory_ref'] ?? [];
                $directory = $this->findDirectoryByImportRef($ref);

                if (!$directory && empty($field['source'])) {
                    $fieldLabel = $field['label'] ?? ($field['key'] ?? 'параметр');
                    throw ValidationException::withMessages([
                        'template_file' => ["Для параметра «{$fieldLabel}» не найден связанный справочник при импорте"],
                    ]);
                }

                if ($directory) {
                    $field['directory_id'] = $directory->id;
                }
            }

            unset($field['directory_ref']);

            return $field;
        })->values()->all();
    }

    private function findDirectoryByImportRef($ref): ?Directory
    {
        $code = trim((string) ($ref['code'] ?? ''));
        $name = trim((string) ($ref['name'] ?? ''));

        if ($code !== '') {
            $directory = Directory::query()->where('code', $code)->first();

            if ($directory) {
                return $directory;
            }
        }

        if ($name !== '') {
            return Directory::query()->where('name', $name)->first();
        }

        return null;
    }

    private function generateImportedName(string $baseName): string
    {
        $baseName = trim($baseName) !== '' ? trim($baseName) : 'Отчёт';
        $candidate = $baseName . ' (импорт)';
        $suffix = 2;

        while (ReportTemplate::where('name', $candidate)->exists()) {
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

        while (ReportTemplate::where('code', $candidate)->exists()) {
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
