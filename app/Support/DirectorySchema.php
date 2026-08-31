<?php

namespace App\Support;

use App\Models\Directory;
use App\Models\DirectoryTemplateList;
use App\Models\DirectoryValue;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DirectorySchema
{
    public const FIELD_TYPES = ['text', 'number', 'date', 'time', 'list', 'qr', 'directory', 'parent', 'calc', 'template', 'image', 'template_list'];

    public static function normalizeSchema($schema): array
    {
        if (!is_array($schema)) {
            return [];
        }

        $normalized = [];

        foreach ($schema as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $key = trim((string) ($field['key'] ?? ''));
            $type = trim((string) ($field['type'] ?? 'text'));

            if ($label === '' || $key === '' || !in_array($type, self::FIELD_TYPES, true)) {
                throw ValidationException::withMessages([
                    'schema' => ['Некорректное описание поля шаблона справочника в строке ' . ($index + 1)],
                ]);
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)) {
                throw ValidationException::withMessages([
                    'schema' => ["Ключ поля «{$label}» должен быть на латинице без пробелов"],
                ]);
            }

            $item = [
                'label' => $label,
                'key' => $key,
                'type' => $type,
                'tab' => mb_substr(trim((string) ($field['tab'] ?? '')), 0, 100),
                'required' => self::toBoolean($field['required'] ?? false),
                'unique' => self::toBoolean($field['unique'] ?? false),
            ];

            if ($type === 'qr') {
                $item['auto_generate'] = self::toBoolean($field['auto_generate'] ?? false);
            }

            if ($type === 'calc') {
                $formula = trim((string) ($field['formula'] ?? ''));

                if ($formula === '') {
                    throw ValidationException::withMessages([
                        'schema' => ["Для поля «{$label}» нужно указать формулу"],
                    ]);
                }

                $item['formula'] = $formula;
            }

            if ($type === 'template') {
                $template = trim((string) ($field['template'] ?? ''));

                if ($template === '') {
                    throw ValidationException::withMessages([
                        'schema' => ["Для поля «{$label}» нужно указать шаблон"],
                    ]);
                }

                $item['template'] = $template;
            }

            if ($type === 'directory') {
                $directoryId = (int) ($field['directory_id'] ?? 0);

                if ($directoryId <= 0 || !Directory::whereKey($directoryId)->exists()) {
                    throw ValidationException::withMessages([
                        'schema' => ["Для поля «{$label}» нужно выбрать справочник"],
                    ]);
                }

                $displayField = trim((string) ($field['directory_display_field'] ?? ''));

                if ($displayField !== '') {
                    $directory = Directory::find($directoryId);
                    $hasDisplayField = collect($directory->schema ?? [])->contains(function ($schemaField) use ($displayField) {
                        return ($schemaField['key'] ?? null) === $displayField;
                    });

                    if (!$hasDisplayField) {
                        throw ValidationException::withMessages([
                            'schema' => ["Поле отображения для «{$label}» не найдено в справочнике"],
                        ]);
                    }
                }

                $item['directory_id'] = $directoryId;

                if ($displayField !== '') {
                    $item['directory_display_field'] = $displayField;
                }
            }

            if ($type === 'parent') {
                $displayField = trim((string) ($field['parent_display_field'] ?? ''));

                if ($displayField === '') {
                    throw ValidationException::withMessages([
                        'schema' => ["Для поля «{$label}» нужно выбрать поле отображения родителя"],
                    ]);
                }

                $item['parent_display_field'] = $displayField;
            }

            if ($type === 'list') {
                $options = collect(Arr::wrap($field['options'] ?? []))
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values()
                    ->all();

                if (empty($options)) {
                    throw ValidationException::withMessages([
                        'schema' => ["Для поля «{$label}» нужно указать варианты списка"],
                    ]);
                }

                $item['options'] = $options;
            }

            if ($type === 'template_list') {
                $templateListId = (int) ($field['template_list_id'] ?? 0);

                if ($templateListId <= 0 || !DirectoryTemplateList::whereKey($templateListId)->exists()) {
                    throw ValidationException::withMessages([
                        'schema' => ["Для поля «{$label}» нужно выбрать список шаблонов"],
                    ]);
                }

                $item['template_list_id'] = $templateListId;
            }

            $normalized[] = $item;
        }

        $keys = array_column($normalized, 'key');

        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages([
                'schema' => ['Ключи полей шаблона справочника должны быть уникальными'],
            ]);
        }

        foreach ($normalized as $field) {
            if (($field['type'] ?? null) !== 'parent') {
                continue;
            }

            $displayField = (string) ($field['parent_display_field'] ?? '');

            if ($displayField !== '' && !in_array($displayField, $keys, true)) {
                throw ValidationException::withMessages([
                    'schema' => ["Для поля «{$field['label']}» поле отображения родителя не найдено в шаблоне"],
                ]);
            }
        }

        return $normalized;
    }

    public static function validateRecord(array $schema, $data): array
    {
        if (!is_array($data)) {
            throw ValidationException::withMessages([
                'data' => ['Данные записи справочника должны быть объектом'],
            ]);
        }

        $expandedSchema = self::expandSchema($schema);
        $result = [];
        $calcFields = [];
        $templateFields = [];
        $fieldsByKey = [];

        $data = self::applyTemplateListValues($schema, $data);

        foreach ($expandedSchema as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;
            $type = $field['type'] ?? 'text';

            if (!$key) {
                continue;
            }

            $fieldsByKey[$key] = $field;
            $required = !empty($field['required']);
            $value = $data[$key] ?? null;

            if ($type === 'calc') {
                $calcFields[] = $field;
                continue;
            }

            if ($type === 'template') {
                $templateFields[] = $field;
                continue;
            }

            if ($type === 'template_list') {
                if (($value === null || $value === '') && $required) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Поле «{$label}» обязательно для заполнения"],
                    ]);
                }

                $result[$key] = $value === null || $value === '' ? null : (string) $value;
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if (($value === null || $value === '') && $required) {
                throw ValidationException::withMessages([
                    "data.{$key}" => ["Поле «{$label}» обязательно для заполнения"],
                ]);
            }

            if ($value === null || $value === '') {
                $result[$key] = null;
                continue;
            }

            if (in_array($type, ['text', 'qr', 'image'], true)) {
                $result[$key] = (string) $value;
                continue;
            }

            if ($type === 'number') {
                if (!is_numeric($value)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Поле «{$label}» должно быть числом"],
                    ]);
                }

                $result[$key] = $value + 0;
                continue;
            }

            if ($type === 'date') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Поле «{$label}» должно быть датой"],
                    ]);
                }

                $result[$key] = (string) $value;
                continue;
            }

            if ($type === 'time') {
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $value)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Поле «{$label}» должно быть временем"],
                    ]);
                }

                $result[$key] = strlen((string) $value) === 5 ? $value . ':00' : (string) $value;
                continue;
            }

            if ($type === 'list') {
                $options = collect(Arr::wrap($field['options'] ?? []))
                    ->map(fn ($option) => trim((string) $option))
                    ->filter()
                    ->values()
                    ->all();

                if (!in_array((string) $value, $options, true)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Некорректное значение поля «{$label}»"],
                    ]);
                }

                $result[$key] = (string) $value;
                continue;
            }

            if (in_array($type, ['directory', 'parent'], true)) {
                if (!is_numeric($value)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Поле «{$label}» должно быть значением справочника"],
                    ]);
                }

                $exists = DirectoryValue::query()
                    ->whereKey((int) $value)
                    ->where('directory_id', (int) ($field['directory_id'] ?? 0))
                    ->exists();

                if (!$exists) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Некорректное значение справочника «{$label}»"],
                    ]);
                }

                $result[$key] = (int) $value;
                continue;
            }

            $result[$key] = (string) $value;
        }

        foreach ($calcFields as $field) {
            $result[$field['key']] = self::evaluateCalcFormula((string) ($field['formula'] ?? ''), $result);
        }

        foreach ($templateFields as $field) {
            $result[$field['key']] = self::evaluateTemplatePattern((string) ($field['template'] ?? ''), $result, $fieldsByKey);
        }

        return $result;
    }

    public static function resolveDisplayValue(array $schema, array $data = [], ?string $fallback = null): string
    {
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            $value = $data[$key] ?? null;

            if ($value === null || $value === '' || is_array($value) || (($field['type'] ?? null) === 'image')) {
                continue;
            }

            return self::formatFieldValue($field, $value);
        }

        return $fallback !== null && $fallback !== '' ? $fallback : 'Запись';
    }

    public static function formatFieldValue(array $field, $value): string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return '-';
        }

        if (in_array(($field['type'] ?? null), ['directory', 'parent'], true)) {
            $directoryValue = DirectoryValue::query()
                ->whereKey((int) $value)
                ->where('directory_id', (int) ($field['directory_id'] ?? 0))
                ->first();

            if (!$directoryValue) {
                return (string) $value;
            }

            $displayField = $field['directory_display_field'] ?? ($field['parent_display_field'] ?? null);
            $data = is_array($directoryValue->data) ? $directoryValue->data : [];

            if ($displayField && isset($data[$displayField]) && $data[$displayField] !== '') {
                return (string) $data[$displayField];
            }

            return (string) $directoryValue->value;
        }

        if (($field['type'] ?? null) === 'template_list') {
            $list = DirectoryTemplateList::query()->find((int) ($field['template_list_id'] ?? 0));
            $items = collect($list?->items ?? []);
            $selected = $items->firstWhere('key', (string) $value);

            if (is_array($selected) && !empty($selected['name'])) {
                return (string) $selected['name'];
            }
        }

        return (string) $value;
    }

    public static function normalizeTemplateLists($lists): array
    {
        return collect($lists)->map(function (DirectoryTemplateList $list) {
            return self::serializeTemplateList($list);
        })->values()->all();
    }

    public static function serializeTemplateList(DirectoryTemplateList $list): array
    {
        $items = collect($list->items ?? [])->map(function ($item) {
            $fields = collect($item['fields'] ?? [])->map(function ($field) {
                $normalizedField = [
                    'key' => trim((string) ($field['key'] ?? '')),
                    'label' => trim((string) ($field['label'] ?? '')),
                    'type' => trim((string) ($field['type'] ?? 'text')),
                    'required' => self::toBoolean($field['required'] ?? false),
                ];

                if (($normalizedField['type'] ?? null) === 'list') {
                    $normalizedField['options'] = collect(Arr::wrap($field['options'] ?? []))
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values()
                        ->all();
                }

                return $normalizedField;
            })->filter(function ($field) {
                return $field['key'] !== '' && $field['label'] !== '';
            })->values()->all();

            return [
                'key' => trim((string) ($item['key'] ?? '')),
                'name' => trim((string) ($item['name'] ?? '')),
                'fields' => $fields,
            ];
        })->filter(function ($item) {
            return $item['key'] !== '' && $item['name'] !== '';
        })->values()->all();

        return [
            'id' => $list->id,
            'name' => $list->name,
            'code' => $list->code,
            'description' => $list->description,
            'items' => $items,
            'created_by' => $list->created_by,
            'creator_name' => optional($list->creator)->name,
        ];
    }

    public static function expandSchema(array $schema): array
    {
        $expanded = [];
        $lists = self::loadTemplateListsForSchema($schema);

        foreach ($schema as $field) {
            $expanded[] = $field;

            if (($field['type'] ?? null) !== 'template_list') {
                continue;
            }

            $fieldKey = (string) ($field['key'] ?? '');
            $fieldLabel = (string) ($field['label'] ?? $fieldKey);
            $list = $lists->get((int) ($field['template_list_id'] ?? 0));

            foreach (($list?->items ?? []) as $item) {
                $itemKey = trim((string) ($item['key'] ?? ''));
                $itemName = trim((string) ($item['name'] ?? $itemKey));

                if ($itemKey === '') {
                    continue;
                }

                foreach (($item['fields'] ?? []) as $subField) {
                    $subKey = trim((string) ($subField['key'] ?? ''));

                    if ($subKey === '') {
                        continue;
                    }

                    $expanded[] = [
                        'label' => $fieldLabel . ' / ' . $itemName . ' / ' . trim((string) ($subField['label'] ?? $subKey)),
                        'key' => self::templateListValueKey($fieldKey, $itemKey, $subKey),
                        'type' => $subField['type'] ?? 'text',
                        'options' => $subField['options'] ?? [],
                        'required' => false,
                        'tab' => $field['tab'] ?? '',
                        'is_template_list_subfield' => true,
                        'template_list_parent_key' => $fieldKey,
                        'template_list_item_key' => $itemKey,
                        'template_list_item_name' => $itemName,
                        'template_list_subfield_key' => $subKey,
                        'template_list_subfield_label' => trim((string) ($subField['label'] ?? $subKey)),
                    ];
                }
            }
        }

        return $expanded;
    }

    public static function templateListDisplayLines(array $field, array $data = []): array
    {
        $fieldKey = (string) ($field['key'] ?? '');
        $selectedItemKey = self::resolveTemplateListSelectedKey($field, $data);

        if ($fieldKey === '' || $selectedItemKey === '') {
            return [];
        }

        $list = DirectoryTemplateList::query()->find((int) ($field['template_list_id'] ?? 0));
        $selectedItem = collect($list?->items ?? [])->firstWhere('key', $selectedItemKey);

        if (!is_array($selectedItem)) {
            return [];
        }

        $lines = [];

        foreach (($selectedItem['fields'] ?? []) as $subField) {
            $subKey = trim((string) ($subField['key'] ?? ''));

            if ($subKey === '') {
                continue;
            }

            $compoundKey = self::templateListValueKey($fieldKey, $selectedItemKey, $subKey);
            $value = $data[$compoundKey] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $lines[] = [
                'key' => $compoundKey,
                'label' => trim((string) ($subField['label'] ?? $subKey)),
                'value' => (string) $value,
                'type' => (string) ($subField['type'] ?? 'text'),
            ];
        }

        return $lines;
    }

    public static function resolveTemplateListSelectedKey(array $field, array $data = []): string
    {
        $fieldKey = (string) ($field['key'] ?? '');

        if ($fieldKey === '') {
            return '';
        }

        $selectedItemKey = trim((string) ($data[$fieldKey] ?? ''));

        if ($selectedItemKey !== '') {
            return $selectedItemKey;
        }

        $list = DirectoryTemplateList::query()->find((int) ($field['template_list_id'] ?? 0));
        $matchedKeys = collect($list?->items ?? [])
            ->map(function ($item) use ($fieldKey, $data) {
                $itemKey = trim((string) ($item['key'] ?? ''));

                if ($itemKey === '') {
                    return null;
                }

                foreach (($item['fields'] ?? []) as $subField) {
                    $subKey = trim((string) ($subField['key'] ?? ''));

                    if ($subKey === '') {
                        continue;
                    }

                    $compoundKey = self::templateListValueKey($fieldKey, $itemKey, $subKey);

                    if (array_key_exists($compoundKey, $data) && $data[$compoundKey] !== null && $data[$compoundKey] !== '') {
                        return $itemKey;
                    }
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        return $matchedKeys->count() === 1 ? (string) $matchedKeys->first() : '';
    }

    public static function validateTemplateListDefinition(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Укажите название списка шаблонов'],
            ]);
        }

        if ($code !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $code)) {
            throw ValidationException::withMessages([
                'code' => ['Код списка шаблонов должен быть на латинице, без пробелов'],
            ]);
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemKey = trim((string) ($item['key'] ?? ''));
            $itemName = trim((string) ($item['name'] ?? ''));

            if ($itemKey === '' || $itemName === '') {
                throw ValidationException::withMessages([
                    'items' => ['У каждого варианта списка шаблонов должны быть ключ и название'],
                ]);
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $itemKey)) {
                throw ValidationException::withMessages([
                    'items' => ["Ключ варианта «{$itemName}» должен быть на латинице"],
                ]);
            }

            $normalizedFields = [];

            foreach (($item['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $fieldKey = trim((string) ($field['key'] ?? ''));
                $fieldLabel = trim((string) ($field['label'] ?? ''));
                $fieldType = trim((string) ($field['type'] ?? 'text'));

                if ($fieldKey === '' || $fieldLabel === '') {
                    throw ValidationException::withMessages([
                        'items' => ['У каждого поля варианта списка шаблонов должны быть ключ и название'],
                    ]);
                }

                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $fieldKey)) {
                    throw ValidationException::withMessages([
                        'items' => ["Ключ поля «{$fieldLabel}» должен быть на латинице"],
                    ]);
                }

                if (!in_array($fieldType, ['text', 'number', 'date', 'time', 'list'], true)) {
                    throw ValidationException::withMessages([
                        'items' => ["Поле «{$fieldLabel}» может быть только текстом, числом, датой, временем или списком"],
                    ]);
                }

                $normalizedField = [
                    'key' => $fieldKey,
                    'label' => $fieldLabel,
                    'type' => $fieldType,
                    'required' => self::toBoolean($field['required'] ?? false),
                ];

                if ($fieldType === 'list') {
                    $options = collect(Arr::wrap($field['options'] ?? []))
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values()
                        ->all();

                    if (empty($options)) {
                        throw ValidationException::withMessages([
                            'items' => ["Для поля «{$fieldLabel}» нужно указать варианты списка"],
                        ]);
                    }

                    $normalizedField['options'] = $options;
                }

                $normalizedFields[] = $normalizedField;
            }

            $fieldKeys = array_column($normalizedFields, 'key');

            if (count($fieldKeys) !== count(array_unique($fieldKeys))) {
                throw ValidationException::withMessages([
                    'items' => ["В варианте «{$itemName}» ключи полей должны быть уникальны"],
                ]);
            }

            $normalizedItems[] = [
                'key' => $itemKey,
                'name' => $itemName,
                'fields' => $normalizedFields,
            ];
        }

        $itemKeys = array_column($normalizedItems, 'key');

        if (count($itemKeys) !== count(array_unique($itemKeys))) {
            throw ValidationException::withMessages([
                'items' => ['Ключи вариантов списка шаблонов должны быть уникальны'],
            ]);
        }

        return [
            'name' => $name,
            'code' => $code !== '' ? $code : null,
            'description' => $description !== '' ? $description : null,
            'items' => $normalizedItems,
        ];
    }

    public static function applyTemplateListValues(array $schema, array $data, array $existingData = []): array
    {
        $lists = self::loadTemplateListsForSchema($schema);

        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'template_list') {
                continue;
            }

            $fieldKey = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $fieldKey);
            $listId = (int) ($field['template_list_id'] ?? 0);
            $list = $lists->get($listId);

            if (!$list) {
                throw ValidationException::withMessages([
                    "data.{$fieldKey}" => ["Для поля «{$label}» не найден список шаблонов"],
                ]);
            }

            $selectedItemKey = trim((string) ($data[$fieldKey] ?? ''));

            if ($selectedItemKey === '') {
                if (!empty($field['required'])) {
                    throw ValidationException::withMessages([
                        "data.{$fieldKey}" => ["Поле «{$label}» обязательно для заполнения"],
                    ]);
                }

                $data[$fieldKey] = null;
                continue;
            }

            $items = collect($list->items ?? []);
            $selectedItem = $items->firstWhere('key', $selectedItemKey);

            if (!is_array($selectedItem)) {
                throw ValidationException::withMessages([
                    "data.{$fieldKey}" => ["Некорректное значение поля «{$label}»"],
                ]);
            }

            $data[$fieldKey] = $selectedItemKey;

            foreach (($selectedItem['fields'] ?? []) as $subField) {
                $compoundKey = self::templateListValueKey($fieldKey, (string) $selectedItemKey, (string) ($subField['key'] ?? ''));
                $subLabel = (string) ($subField['label'] ?? $compoundKey);
                $value = $data[$compoundKey] ?? ($existingData[$compoundKey] ?? null);

                if (is_string($value)) {
                    $value = trim($value);
                }

                if (($value === null || $value === '') && !empty($subField['required'])) {
                    throw ValidationException::withMessages([
                        "data.{$compoundKey}" => ["Поле «{$subLabel}» обязательно для заполнения"],
                    ]);
                }

                if ($value === null || $value === '') {
                    $data[$compoundKey] = null;
                    continue;
                }

                $data[$compoundKey] = self::validateSimpleTemplateListField($compoundKey, $subField, $value);
            }
        }

        return $data;
    }

    public static function templateListValueKey(string $fieldKey, string $itemKey, string $subFieldKey): string
    {
        return $fieldKey . '__' . $itemKey . '__' . $subFieldKey;
    }

    private static function validateSimpleTemplateListField(string $compoundKey, array $field, $value)
    {
        $label = (string) ($field['label'] ?? $compoundKey);
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'number') {
            if (!is_numeric($value)) {
                throw ValidationException::withMessages([
                    "data.{$compoundKey}" => ["Поле «{$label}» должно быть числом"],
                ]);
            }

            return $value + 0;
        }

        if ($type === 'date') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                throw ValidationException::withMessages([
                    "data.{$compoundKey}" => ["Поле «{$label}» должно быть датой"],
                ]);
            }

            return (string) $value;
        }

        if ($type === 'time') {
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $value)) {
                throw ValidationException::withMessages([
                    "data.{$compoundKey}" => ["Поле «{$label}» должно быть временем"],
                ]);
            }

            return strlen((string) $value) === 5 ? $value . ':00' : (string) $value;
        }

        if ($type === 'list') {
            $options = collect(Arr::wrap($field['options'] ?? []))
                ->map(fn ($option) => trim((string) $option))
                ->filter()
                ->values()
                ->all();

            if (!in_array((string) $value, $options, true)) {
                throw ValidationException::withMessages([
                    "data.{$compoundKey}" => ["Некорректное значение поля «{$label}»"],
                ]);
            }

            return (string) $value;
        }

        return (string) $value;
    }

    private static function loadTemplateListsForSchema(array $schema): Collection
    {
        $ids = collect($schema)
            ->filter(fn ($field) => ($field['type'] ?? null) === 'template_list' && !empty($field['template_list_id']))
            ->pluck('template_list_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return collect();
        }

        return DirectoryTemplateList::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    public static function buildDataFromLegacyValue(array $schema, string $value): array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? null) === 'text') {
                return [
                    $field['key'] => $value,
                ];
            }
        }

        throw ValidationException::withMessages([
            'value' => ['Для быстрого добавления в шаблоне справочника должно быть хотя бы одно текстовое поле'],
        ]);
    }

    public static function validateUniqueFields(array $schema, array $recordData, iterable $existingValues, ?int $ignoreId = null): void
    {
        foreach ($schema as $field) {
            if (empty($field['unique'])) {
                continue;
            }

            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;

            if (!$key) {
                continue;
            }

            $targetValue = $recordData[$key] ?? null;

            if ($targetValue === null || $targetValue === '') {
                continue;
            }

            foreach ($existingValues as $existingValue) {
                if ($ignoreId && (int) ($existingValue->id ?? 0) === $ignoreId) {
                    continue;
                }

                $existingData = $existingValue->data ?? [];
                $existingFieldValue = is_array($existingData) ? ($existingData[$key] ?? null) : null;

                if (self::valuesMatch($existingFieldValue, $targetValue)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Поле «{$label}» должно быть уникальным"],
                    ]);
                }
            }
        }
    }

    private static function valuesMatch($left, $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (string) ($left + 0) === (string) ($right + 0);
        }

        return (string) $left === (string) $right;
    }

    private static function evaluateCalcFormula(string $formula, array $values)
    {
        $expression = $formula;

        foreach ($values as $key => $value) {
            $numericValue = 0;

            if ($value !== null && $value !== '' && is_numeric((string) $value)) {
                $numericValue = $value + 0;
            }

            $expression = preg_replace('/\b' . preg_quote((string) $key, '/') . '\b/u', (string) $numericValue, $expression);
        }

        if (!preg_match('/^[0-9+\-*/().,\s]+$/', $expression)) {
            throw ValidationException::withMessages([
                'data' => ['Некорректная формула вычисляемого поля справочника'],
            ]);
        }

        $expression = str_replace(',', '.', $expression);

        set_error_handler(function () {
        });

        try {
            $result = eval('return ' . $expression . ';');
        } finally {
            restore_error_handler();
        }

        if (!is_numeric($result) || !is_finite((float) $result)) {
            throw ValidationException::withMessages([
                'data' => ['Формула вычисляемого поля справочника вернула некорректное значение'],
            ]);
        }

        return round((float) $result, 6);
    }

    private static function evaluateTemplatePattern(string $template, array $values, array $fieldsByKey): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*}}/', function (array $matches) use ($values, $fieldsByKey) {
            $key = $matches[1] ?? '';
            $field = $fieldsByKey[$key] ?? [];
            $value = $values[$key] ?? null;

            return self::stringifyTemplateValue(is_array($field) ? $field : [], $value, $values);
        }, $template) ?? $template;
    }

    private static function stringifyTemplateValue(array $field, $value, array $values = []): string
    {
        if (($field['type'] ?? null) === 'template_list') {
            $parts = [];
            $selectedName = self::formatFieldValue($field, $value);

            if ($selectedName !== '' && $selectedName !== '-') {
                $parts[] = trim($selectedName);
            }

            foreach (self::templateListDisplayLines($field, $values) as $line) {
                $lineValue = trim((string) ($line['value'] ?? ''));

                if ($lineValue !== '') {
                    $parts[] = $lineValue;
                }
            }

            return trim(implode(' ', array_values(array_unique(array_filter($parts)))));
        }

        if ($value === null || $value === '' || is_array($value)) {
            return '';
        }

        if (in_array(($field['type'] ?? null), ['directory', 'parent'], true)) {
            $directoryValue = DirectoryValue::query()
                ->whereKey((int) $value)
                ->where('directory_id', (int) ($field['directory_id'] ?? 0))
                ->first();

            if (!$directoryValue) {
                return (string) $value;
            }

            $displayField = $field['directory_display_field'] ?? ($field['parent_display_field'] ?? null);
            $data = is_array($directoryValue->data) ? $directoryValue->data : [];

            if ($displayField && isset($data[$displayField]) && $data[$displayField] !== '') {
                return (string) $data[$displayField];
            }

            return (string) $directoryValue->value;
        }

        return (string) $value;
    }

    private static function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return !empty($value);
    }
}
