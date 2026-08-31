<?php

namespace App\Support;

use App\Models\Directory;
use App\Models\JournalTemplate;

class DirectoryAccessScope
{
    private static array $accessibleIdsCache = [];

    public static function accessibleDirectoryIdsForUser(?int $userId, ?string $role, ?int $divisionId): array
    {
        $cacheKey = implode(':', [
            (string) ($userId ?? 0),
            (string) ($role ?? ''),
            (string) ($divisionId ?? 0),
        ]);

        if (array_key_exists($cacheKey, self::$accessibleIdsCache)) {
            return self::$accessibleIdsCache[$cacheKey];
        }

        $managedDivisionIds = DivisionTree::managedDivisionIds($divisionId, $role);
        $directDirectoryIds = self::directlyAccessibleDirectoryIds($managedDivisionIds);
        $journalDirectoryIds = self::journalAccessibleDirectoryIds($userId, $role, $divisionId);
        $allDirectoryIds = self::expandDirectoryIds(array_merge($directDirectoryIds, $journalDirectoryIds));

        sort($allDirectoryIds);

        return self::$accessibleIdsCache[$cacheKey] = $allDirectoryIds;
    }

    public static function userCanAccessDirectory(Directory $directory, ?int $userId, ?string $role, ?int $divisionId): bool
    {
        return in_array((int) $directory->id, self::accessibleDirectoryIdsForUser($userId, $role, $divisionId), true);
    }

    public static function grantDivisionAccessToReferencedDirectoriesFromDirectory(Directory $directory, array $divisionIds): void
    {
        $divisionIds = self::normalizeIds($divisionIds);

        if (empty($divisionIds)) {
            return;
        }

        $directoryIds = self::expandDirectoryIds(self::referencedDirectoryIdsFromDirectorySchema($directory->schema ?? []));
        self::attachDivisionsToDirectories($directoryIds, $divisionIds);
    }

    public static function grantDivisionAccessToReferencedDirectoriesFromJournal(JournalTemplate $journal, array $divisionIds): void
    {
        $divisionIds = self::normalizeIds($divisionIds);

        if (empty($divisionIds)) {
            return;
        }

        $directoryIds = self::expandDirectoryIds(self::referencedDirectoryIdsFromJournalSchema($journal->schema ?? []));
        self::attachDivisionsToDirectories($directoryIds, $divisionIds);
    }

    private static function directlyAccessibleDirectoryIds(array $managedDivisionIds): array
    {
        return Directory::query()
            ->where(function ($query) use ($managedDivisionIds) {
                $query->whereDoesntHave('divisions');

                if (!empty($managedDivisionIds)) {
                    $query->orWhereHas('divisions', function ($divisionQuery) use ($managedDivisionIds) {
                        $divisionQuery->whereIn('divisions.id', $managedDivisionIds);
                    });
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function journalAccessibleDirectoryIds(?int $userId, ?string $role, ?int $divisionId): array
    {
        $journals = JournalTemplate::query()
            ->with('divisions:id')
            ->where('is_active', true)
            ->get(['id', 'schema', 'is_active']);

        $directoryIds = [];

        foreach ($journals as $journal) {
            $access = UserJournalAccess::resolveForJournal($journal, $userId, $role, $divisionId);

            if (empty($access['can_view'])) {
                continue;
            }

            $directoryIds = array_merge($directoryIds, self::referencedDirectoryIdsFromJournalSchema($journal->schema ?? []));
        }

        return self::normalizeIds($directoryIds);
    }

    private static function expandDirectoryIds(array $directoryIds): array
    {
        $queue = self::normalizeIds($directoryIds);
        $visited = [];
        $resolved = [];

        while (!empty($queue)) {
            $currentIds = array_values(array_diff($queue, array_keys($visited)));
            $queue = [];

            if (empty($currentIds)) {
                break;
            }

            $directories = Directory::query()
                ->whereIn('id', $currentIds)
                ->get(['id', 'schema']);

            foreach ($directories as $directory) {
                $directoryId = (int) $directory->id;

                if (isset($visited[$directoryId])) {
                    continue;
                }

                $visited[$directoryId] = true;
                $resolved[] = $directoryId;

                foreach (self::referencedDirectoryIdsFromDirectorySchema($directory->schema ?? []) as $childId) {
                    if (!isset($visited[$childId])) {
                        $queue[] = $childId;
                    }
                }
            }
        }

        return self::normalizeIds($resolved);
    }

    private static function referencedDirectoryIdsFromDirectorySchema(array $schema): array
    {
        return collect($schema)
            ->filter(function ($field) {
                return is_array($field)
                    && ($field['type'] ?? null) === 'directory'
                    && !empty($field['directory_id']);
            })
            ->pluck('directory_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    private static function referencedDirectoryIdsFromJournalSchema(array $schema): array
    {
        return collect($schema)
            ->filter(function ($field) {
                return is_array($field)
                    && in_array($field['type'] ?? null, ['directory', 'directory_text'], true)
                    && !empty($field['directory_id']);
            })
            ->pluck('directory_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    private static function attachDivisionsToDirectories(array $directoryIds, array $divisionIds): void
    {
        if (empty($directoryIds) || empty($divisionIds)) {
            return;
        }

        Directory::query()
            ->whereIn('id', $directoryIds)
            ->get(['id'])
            ->each(function (Directory $directory) use ($divisionIds) {
                $directory->divisions()->syncWithoutDetaching($divisionIds);
            });
    }

    private static function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($id) => (int) $id, $ids), fn ($id) => $id > 0)));
    }
}
