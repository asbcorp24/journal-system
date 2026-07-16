<?php

namespace App\Support;

use App\Models\Division;

class DivisionTree
{
    public static function managedDivisionIds(?int $divisionId, ?string $role = null): array
    {
        if (!$divisionId) {
            return [];
        }

        if ($role !== 'admin') {
            return [$divisionId];
        }

        $divisions = Division::query()
            ->get(['id', 'parent_id']);

        $childrenMap = [];

        foreach ($divisions as $division) {
            $parentId = $division->parent_id;

            if ($parentId === null) {
                continue;
            }

            $childrenMap[$parentId] ??= [];
            $childrenMap[$parentId][] = (int) $division->id;
        }

        $result = [];
        $queue = [(int) $divisionId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if (in_array($currentId, $result, true)) {
                continue;
            }

            $result[] = $currentId;

            foreach ($childrenMap[$currentId] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        sort($result);

        return $result;
    }

    public static function ancestorAndSelfIds(?int $divisionId): array
    {
        if (!$divisionId) {
            return [];
        }

        $divisions = Division::query()
            ->get(['id', 'parent_id'])
            ->keyBy('id');

        $result = [];
        $currentId = (int) $divisionId;

        while ($currentId && isset($divisions[$currentId])) {
            if (in_array($currentId, $result, true)) {
                break;
            }

            $result[] = $currentId;
            $currentId = (int) ($divisions[$currentId]->parent_id ?? 0);
        }

        return $result;
    }
}
