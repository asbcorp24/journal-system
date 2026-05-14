<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportTemplateController extends Controller
{
    public function index()
    {
        $directories = Directory::orderBy('name')->get();

        return view('admin.reports.index', compact('directories'));
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
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
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
}
