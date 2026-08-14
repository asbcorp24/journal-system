<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedFilterController extends Controller
{
    public function directoryIndex(Directory $directory)
    {
        $savedFilters = SavedFilter::query()
            ->where('user_id', (int) session('user_id'))
            ->where('entity_type', SavedFilter::ENTITY_DIRECTORY)
            ->where('entity_id', $directory->id)
            ->orderBy('name')
            ->get()
            ->map(fn (SavedFilter $filter) => $this->serializeSavedFilter($filter))
            ->values()
            ->all();

        return view('admin.saved-filters.builder', [
            'pageTitle' => 'Конструктор фильтров справочника',
            'entityTitle' => $directory->name,
            'backUrl' => route('admin.directories.index'),
            'availableFields' => $this->buildDirectoryAvailableFields($directory),
            'savedFilters' => $savedFilters,
            'storeUrl' => route('admin.directories.filters.store', $directory),
            'updateUrlPattern' => url('/admin/directories/' . $directory->id . '/filters/__ID__'),
            'deleteUrlPattern' => url('/admin/directories/' . $directory->id . '/filters/__ID__'),
            'entityHelp' => 'Отметьте поля справочника, которые должны показываться в фильтрах админского раздела, и при необходимости задайте значения по умолчанию.',
        ]);
    }

    public function storeDirectory(Request $request, Directory $directory)
    {
        $filter = SavedFilter::create($this->validateFilterPayload($request, $directory->id));

        return response()->json([
            'success' => true,
            'message' => 'Фильтр справочника сохранён',
            'filter' => $this->serializeSavedFilter($filter),
        ]);
    }

    public function updateDirectory(Request $request, Directory $directory, SavedFilter $filter)
    {
        $this->ensureOwnedDirectoryFilter($filter, $directory);
        $filter->update($this->validateFilterPayload($request, $directory->id, $filter));

        return response()->json([
            'success' => true,
            'message' => 'Фильтр справочника обновлён',
            'filter' => $this->serializeSavedFilter($filter->fresh()),
        ]);
    }

    public function destroyDirectory(Directory $directory, SavedFilter $filter)
    {
        $this->ensureOwnedDirectoryFilter($filter, $directory);
        $filter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Фильтр справочника удалён',
        ]);
    }

    private function validateFilterPayload(Request $request, int $directoryId, ?SavedFilter $filter = null): array
    {
        $allowedKeys = collect($this->buildDirectoryAvailableFields(Directory::findOrFail($directoryId)))
            ->pluck('key')
            ->all();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('saved_filters', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', (int) session('user_id'))
                        ->where('entity_type', SavedFilter::ENTITY_DIRECTORY)
                        ->where('entity_id', $directoryId))
                    ->ignore($filter?->id),
            ],
            'description' => ['nullable', 'string'],
            'visible_fields' => ['nullable', 'array'],
            'visible_fields.*' => ['string', Rule::in($allowedKeys)],
            'values' => ['nullable', 'array'],
        ]);

        $values = [];
        foreach ((array) ($validated['values'] ?? []) as $key => $value) {
            if (!in_array((string) $key, $allowedKeys, true)) {
                continue;
            }

            $operator = 'eq';
            $normalized = '';
            $normalizedTo = '';

            if (is_array($value)) {
                $operator = trim((string) ($value['operator'] ?? 'eq'));
                $normalized = trim((string) ($value['value'] ?? ''));
                $normalizedTo = trim((string) ($value['value_to'] ?? ''));
            } else {
                $normalized = trim((string) $value);
            }

            if (!in_array($operator, ['eq', 'gt', 'lt', 'neq', 'between', 'contains'], true)) {
                $operator = 'eq';
            }

            if ($normalized === '') {
                continue;
            }

            $values[$key] = [
                'operator' => $operator,
                'value' => $normalized,
                'value_to' => $normalizedTo,
            ];
        }

        return [
            'user_id' => (int) session('user_id'),
            'entity_type' => SavedFilter::ENTITY_DIRECTORY,
            'entity_id' => $directoryId,
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'visible_fields' => collect($validated['visible_fields'] ?? [])->values()->all(),
            'values' => $values,
        ];
    }

    private function buildDirectoryAvailableFields(Directory $directory): array
    {
        $schema = $directory->schema ?? [];
        $directoryIds = collect($schema)
            ->filter(fn ($field) => in_array($field['type'] ?? '', ['directory', 'directory_text'], true) && !empty($field['directory_id']))
            ->pluck('directory_id')
            ->unique()
            ->values()
            ->all();

        $directoryValues = DirectoryValue::query()
            ->whereIn('directory_id', $directoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');

        return collect($schema)
            ->filter(fn ($field) => !empty($field['key']))
            ->map(function (array $field) use ($directoryValues) {
                $type = (string) ($field['type'] ?? 'text');
                $options = [];

                if ($type === 'list') {
                    $options = collect($field['options'] ?? [])->map(fn ($option) => [
                        'value' => (string) $option,
                        'label' => (string) $option,
                    ])->values()->all();
                } elseif (in_array($type, ['directory', 'directory_text'], true) && !empty($field['directory_id'])) {
                    $displayField = $field['directory_display_field'] ?? null;
                    $options = collect($directoryValues->get((int) $field['directory_id'], []))
                        ->map(function (DirectoryValue $value) use ($type, $displayField) {
                            $data = is_array($value->data) ? $value->data : [];
                            $label = $displayField && !empty($data[$displayField])
                                ? (string) $data[$displayField]
                                : (string) $value->value;

                            return [
                                'value' => $type === 'directory_text' ? $label : (string) $value->id,
                                'label' => $label,
                            ];
                        })
                        ->values()
                        ->all();
                }

                return [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? $field['key']),
                    'type' => $type,
                    'options' => $options,
                ];
            })
            ->values()
            ->all();
    }

    private function serializeSavedFilter(SavedFilter $filter): array
    {
        return [
            'id' => $filter->id,
            'name' => $filter->name,
            'description' => $filter->description,
            'visible_fields' => $filter->visible_fields ?? [],
            'values' => $filter->values ?? [],
        ];
    }

    private function ensureOwnedDirectoryFilter(SavedFilter $filter, Directory $directory): void
    {
        if (
            (int) $filter->user_id !== (int) session('user_id')
            || $filter->entity_type !== SavedFilter::ENTITY_DIRECTORY
            || (int) $filter->entity_id !== (int) $directory->id
        ) {
            abort(404);
        }
    }
}
