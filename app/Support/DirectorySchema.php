<?php

namespace App\Support;

use App\Models\Directory;
use App\Models\DirectoryValue;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class DirectorySchema
{
    public const FIELD_TYPES = ['text', 'number', 'date', 'time', 'list', 'qr', 'directory'];

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

            if ($type === 'directory') {
                $directoryId = (int) ($field['directory_id'] ?? 0);

                if ($directoryId <= 0 || !Directory::whereKey($directoryId)->exists()) {
                    throw ValidationException::withMessages([
                        'schema' => ["Р”Р»СЏ РїРѕР»СЏ В«{$label}В» РЅСѓР¶РЅРѕ РІС‹Р±СЂР°С‚СЊ СЃРїСЂР°РІРѕС‡РЅРёРє"],
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
                            'schema' => ["РџРѕР»Рµ РѕС‚РѕР±СЂР°Р¶РµРЅРёСЏ РґР»СЏ В«{$label}В» РЅРµ РЅР°Р№РґРµРЅРѕ РІ СЃРїСЂР°РІРѕС‡РЅРёРєРµ"],
                        ]);
                    }
                }

                $item['directory_id'] = $directoryId;

                if ($displayField !== '') {
                    $item['directory_display_field'] = $displayField;
                }
            }

            if ($type === 'list') {
                $options = collect(Arr::wrap($field['options'] ?? []))
                    ->map(function ($value) {
                        return trim((string) $value);
                    })
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

            $normalized[] = $item;
        }

        $keys = array_column($normalized, 'key');

        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages([
                'schema' => ['Ключи полей шаблона справочника должны быть уникальными'],
            ]);
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

        $result = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            $label = $field['label'];
            $type = $field['type'];
            $required = !empty($field['required']);
            $value = $data[$key] ?? null;

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

            if ($type === 'text' || $type === 'qr') {
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
                $options = $field['options'] ?? [];

                if (!in_array((string) $value, $options, true)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["Некорректное значение поля «{$label}»"],
                    ]);
                }

                $result[$key] = (string) $value;
                continue;
            }

            if ($type === 'directory') {
                if (!is_numeric($value)) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["РџРѕР»Рµ В«{$label}В» РґРѕР»Р¶РЅРѕ Р±С‹С‚СЊ Р·РЅР°С‡РµРЅРёРµРј СЃРїСЂР°РІРѕС‡РЅРёРєР°"],
                    ]);
                }

                $exists = DirectoryValue::whereKey((int) $value)
                    ->where('directory_id', (int) ($field['directory_id'] ?? 0))
                    ->exists();

                if (!$exists) {
                    throw ValidationException::withMessages([
                        "data.{$key}" => ["РќРµРєРѕСЂСЂРµРєС‚РЅРѕРµ Р·РЅР°С‡РµРЅРёРµ СЃРїСЂР°РІРѕС‡РЅРёРєР° В«{$label}В»"],
                    ]);
                }

                $result[$key] = (int) $value;
            }
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

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            return (string) $value;
        }

        return $fallback !== null && $fallback !== '' ? $fallback : 'Запись';
    }

    public static function formatFieldValue(array $field, $value): string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return '-';
        }

        if (($field['type'] ?? null) === 'directory') {
            $directoryValue = DirectoryValue::whereKey((int) $value)
                ->where('directory_id', (int) ($field['directory_id'] ?? 0))
                ->first();

            if (!$directoryValue) {
                return (string) $value;
            }

            $displayField = $field['directory_display_field'] ?? null;
            $data = is_array($directoryValue->data) ? $directoryValue->data : [];

            if ($displayField && isset($data[$displayField]) && $data[$displayField] !== '') {
                return (string) $data[$displayField];
            }

            return (string) $directoryValue->value;
        }

        return (string) $value;
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
