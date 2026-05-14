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
        $divisions = Division::orderBy('name')->get();
        $directories = Directory::orderBy('name')->get();

        return view('admin.journal-templates.index', compact('divisions', 'directories'));
    }

    public function list(Request $request)
    {
        $query = JournalTemplate::with('divisions')
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
        $validated = $this->validateTemplate($request);

        $template = DB::transaction(function () use ($validated, $request) {
            $template = JournalTemplate::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'schema' => $validated['schema'],
                'is_active' => $request->boolean('is_active'),
                'created_by' => null,
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
        $journalTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Журнал удалён',
        ]);
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
                    'list',
                    'directory',
                    'directory_text',
                    'calc',
                ]),
            ],
            'schema.*.required' => [
                'nullable',
                'boolean',
            ],
            'schema.*.directory_id' => [
                'nullable',
                'exists:directories,id',
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

        $schema = [];

        foreach ($validated['schema'] as $field) {
            $item = [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => !empty($field['required']),
            ];

            if (in_array($field['type'], ['directory', 'directory_text'])) {
                if (empty($field['directory_id'])) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Для поля справочника необходимо выбрать справочник',
                    ], 422));
                }

                $item['directory_id'] = (int)$field['directory_id'];
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

            $schema[] = $item;
        }

        $validated['schema'] = $schema;

        return $validated;
    }
}
