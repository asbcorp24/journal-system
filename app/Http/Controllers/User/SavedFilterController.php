<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalTemplate;
use App\Models\SavedFilter;
use App\Support\DivisionTree;
use App\Support\UserJournalAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedFilterController extends Controller
{
    public function journalIndex(JournalTemplate $journal)
    {
        $this->ensureJournalAccess($journal);

        return view('user.saved-filters.builder', [
            'pageTitle' => 'Конструктор фильтров журнала',
            'entityTitle' => $journal->name,
            'entityType' => SavedFilter::ENTITY_JOURNAL,
            'entityId' => $journal->id,
            'backUrl' => route('user.journals.show', $journal),
            'availableFields' => $this->buildJournalAvailableFields($journal),
            'savedFilters' => $this->serializeSavedFilters(
                SavedFilter::query()
                    ->where('user_id', (int) session('user_id'))
                    ->where('entity_type', SavedFilter::ENTITY_JOURNAL)
                    ->where('entity_id', $journal->id)
                    ->orderBy('name')
                    ->get()
            ),
            'storeUrl' => route('user.journals.filters.store', $journal),
            'updateUrlPattern' => url('/journals/' . $journal->id . '/filters/__ID__'),
            'deleteUrlPattern' => url('/journals/' . $journal->id . '/filters/__ID__'),
            'entityHelp' => 'Выберите, какие поля должны показываться в фильтрах журнала, и при необходимости задайте для них значения по умолчанию.',
        ]);
    }

    public function storeJournal(Request $request, JournalTemplate $journal)
    {
        $this->ensureJournalAccess($journal);

        $filter = SavedFilter::create($this->validateFilterPayload(
            $request,
            SavedFilter::ENTITY_JOURNAL,
            $journal->id,
            $this->buildJournalAvailableFields($journal)
        ));

        return response()->json([
            'success' => true,
            'message' => 'Фильтр журнала сохранён',
            'filter' => $this->serializeSavedFilter($filter),
        ]);
    }

    public function updateJournal(Request $request, JournalTemplate $journal, SavedFilter $filter)
    {
        $this->ensureJournalAccess($journal);
        $this->ensureOwnedFilter($filter, SavedFilter::ENTITY_JOURNAL, $journal->id);

        $filter->update($this->validateFilterPayload(
            $request,
            SavedFilter::ENTITY_JOURNAL,
            $journal->id,
            $this->buildJournalAvailableFields($journal),
            $filter
        ));

        return response()->json([
            'success' => true,
            'message' => 'Фильтр журнала обновлён',
            'filter' => $this->serializeSavedFilter($filter->fresh()),
        ]);
    }

    public function destroyJournal(JournalTemplate $journal, SavedFilter $filter)
    {
        $this->ensureJournalAccess($journal);
        $this->ensureOwnedFilter($filter, SavedFilter::ENTITY_JOURNAL, $journal->id);
        $filter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Фильтр журнала удалён',
        ]);
    }

    public function directoryIndex(Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

        return view('user.saved-filters.builder', [
            'pageTitle' => 'Конструктор фильтров справочника',
            'entityTitle' => $directory->name,
            'entityType' => SavedFilter::ENTITY_DIRECTORY,
            'entityId' => $directory->id,
            'backUrl' => route('user.directories.index'),
            'availableFields' => $this->buildDirectoryAvailableFields($directory),
            'savedFilters' => $this->serializeSavedFilters(
                SavedFilter::query()
                    ->where('user_id', (int) session('user_id'))
                    ->where('entity_type', SavedFilter::ENTITY_DIRECTORY)
                    ->where('entity_id', $directory->id)
                    ->orderBy('name')
                    ->get()
            ),
            'storeUrl' => route('user.directories.filters.store', $directory),
            'updateUrlPattern' => url('/directories/' . $directory->id . '/filters/__ID__'),
            'deleteUrlPattern' => url('/directories/' . $directory->id . '/filters/__ID__'),
            'entityHelp' => 'Отметьте поля справочника, которые должны отображаться в фильтрах, и при необходимости задайте стартовые значения.',
        ]);
    }

    public function storeDirectory(Request $request, Directory $directory)
    {
        $this->ensureDirectoryAccess($directory);

        $filter = SavedFilter::create($this->validateFilterPayload(
            $request,
            SavedFilter::ENTITY_DIRECTORY,
            $directory->id,
            $this->buildDirectoryAvailableFields($directory)
        ));

        return response()->json([
            'success' => true,
            'message' => 'Фильтр справочника сохранён',
            'filter' => $this->serializeSavedFilter($filter),
        ]);
    }

    public function updateDirectory(Request $request, Directory $directory, SavedFilter $filter)
    {
        $this->ensureDirectoryAccess($directory);
        $this->ensureOwnedFilter($filter, SavedFilter::ENTITY_DIRECTORY, $directory->id);

        $filter->update($this->validateFilterPayload(
            $request,
            SavedFilter::ENTITY_DIRECTORY,
            $directory->id,
            $this->buildDirectoryAvailableFields($directory),
            $filter
        ));

        return response()->json([
            'success' => true,
            'message' => 'Фильтр справочника обновлён',
            'filter' => $this->serializeSavedFilter($filter->fresh()),
        ]);
    }

    public function destroyDirectory(Directory $directory, SavedFilter $filter)
    {
        $this->ensureDirectoryAccess($directory);
        $this->ensureOwnedFilter($filter, SavedFilter::ENTITY_DIRECTORY, $directory->id);
        $filter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Фильтр справочника удалён',
        ]);
    }

    private function validateFilterPayload(
        Request $request,
        string $entityType,
        int $entityId,
        array $availableFields,
        ?SavedFilter $filter = null
    ): array {
        $allowedKeys = collect($availableFields)->pluck('key')->all();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('saved_filters', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', (int) session('user_id'))
                        ->where('entity_type', $entityType)
                        ->where('entity_id', $entityId))
                    ->ignore($filter?->id),
            ],
            'description' => ['nullable', 'string'],
            'visible_fields' => ['nullable', 'array'],
            'visible_fields.*' => ['string', Rule::in($allowedKeys)],
            'values' => ['nullable', 'array'],
        ]);

        $visibleFields = collect($validated['visible_fields'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

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
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'visible_fields' => $visibleFields,
            'values' => $values,
        ];
    }

    private function serializeSavedFilters($filters): array
    {
        return collect($filters)->map(function (SavedFilter $filter) {
            return $this->serializeSavedFilter($filter);
        })->values()->all();
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

    private function buildJournalAvailableFields(JournalTemplate $journal): array
    {
        $schema = $journal->schema ?? [];
        $directoryFields = collect($schema)
            ->filter(fn ($field) => in_array($field['type'] ?? '', ['directory', 'directory_text'], true) && !empty($field['directory_id']))
            ->pluck('directory_id')
            ->unique()
            ->values()
            ->all();

        $directoryValues = DirectoryValue::query()
            ->whereIn('directory_id', $directoryFields)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');

        return collect($schema)
            ->filter(fn ($field) => !empty($field['filterable']) && !empty($field['key']))
            ->map(function (array $field) use ($directoryValues) {
                return [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? $field['key']),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'options' => $this->normalizeFilterFieldOptions($field, $directoryValues),
                ];
            })
            ->values()
            ->all();
    }

    private function buildDirectoryAvailableFields(Directory $directory): array
    {
        $schema = $directory->schema ?? [];
        $directoryFields = collect($schema)
            ->filter(fn ($field) => ($field['type'] ?? null) === 'directory' && !empty($field['directory_id']))
            ->pluck('directory_id')
            ->unique()
            ->values()
            ->all();

        $directoryValues = DirectoryValue::query()
            ->whereIn('directory_id', $directoryFields)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('directory_id');

        return collect($schema)
            ->filter(fn ($field) => !empty($field['key']))
            ->map(function (array $field) use ($directoryValues) {
                return [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? $field['key']),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'options' => $this->normalizeFilterFieldOptions($field, $directoryValues),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeFilterFieldOptions(array $field, $directoryValues): array
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'list') {
            return collect($field['options'] ?? [])->map(function ($option) {
                return [
                    'value' => (string) $option,
                    'label' => (string) $option,
                ];
            })->values()->all();
        }

        if (!in_array($type, ['directory', 'directory_text'], true) || empty($field['directory_id'])) {
            return [];
        }

        $directoryId = (int) $field['directory_id'];
        $displayField = $field['directory_display_field'] ?? null;

        return collect($directoryValues->get($directoryId, []))
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

    private function ensureOwnedFilter(SavedFilter $filter, string $entityType, int $entityId): void
    {
        if (
            (int) $filter->user_id !== (int) session('user_id')
            || $filter->entity_type !== $entityType
            || (int) $filter->entity_id !== $entityId
        ) {
            abort(404);
        }
    }

    private function ensureJournalAccess(JournalTemplate $journal): void
    {
        if (!$journal->is_active) {
            abort(404);
        }

        $access = UserJournalAccess::resolveForJournal(
            $journal,
            session('user_id'),
            session('user_role'),
            session('user_division_id')
        );

        if (empty($access['division_ids'])) {
            abort(403, 'Нет доступа к этому журналу');
        }
    }

    private function ensureDirectoryAccess(Directory $directory): void
    {
        $managedDivisionIds = DivisionTree::managedDivisionIds(session('user_division_id'), session('user_role'));
        $allowedDivisionIds = $directory->divisions()->pluck('divisions.id')->map(fn ($id) => (int) $id)->all();

        if (empty(array_intersect($managedDivisionIds, $allowedDivisionIds))) {
            abort(403, 'Нет доступа к этому справочнику');
        }
    }
}
