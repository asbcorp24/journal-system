<?php

namespace App\Support;

use App\Models\JournalTemplate;
use App\Models\UserJournalPermission;

class UserJournalAccess
{
    private static array $permissionsCache = [];

    public static function resolveForJournal(JournalTemplate $journal, ?int $userId, ?string $role, ?int $divisionId): array
    {
        $journalDivisionIds = $journal->relationLoaded('divisions')
            ? $journal->divisions->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $journal->divisions()->pluck('divisions.id')->map(fn ($id) => (int) $id)->values()->all();

        $journalDivisionIds = array_values(array_unique($journalDivisionIds));

        $baseDivisionIds = [];
        if ($role === 'admin') {
            $baseDivisionIds = array_values(array_intersect(
                $journalDivisionIds,
                DivisionTree::managedDivisionIds($divisionId, $role)
            ));
        } elseif ($divisionId !== null && in_array((int) $divisionId, $journalDivisionIds, true)) {
            $baseDivisionIds = [(int) $divisionId];
        }

        $explicitViewDivisionIds = [];
        $explicitFullDivisionIds = [];

        foreach (self::permissionsForUser($userId) as $permission) {
            if ($permission->journal_template_id !== null && (int) $permission->journal_template_id !== (int) $journal->id) {
                continue;
            }

            $permissionDivisionId = (int) $permission->division_id;
            if (!in_array($permissionDivisionId, $journalDivisionIds, true)) {
                continue;
            }

            if ($permission->access_level === UserJournalPermission::ACCESS_FULL) {
                $explicitFullDivisionIds[] = $permissionDivisionId;
                continue;
            }

            $explicitViewDivisionIds[] = $permissionDivisionId;
        }

        $explicitViewDivisionIds = array_values(array_unique($explicitViewDivisionIds));
        $explicitFullDivisionIds = array_values(array_unique($explicitFullDivisionIds));

        $accessByDivision = [];

        foreach ($baseDivisionIds as $id) {
            $accessByDivision[$id] = [
                'mode' => UserJournalPermission::ACCESS_FULL,
                'explicit' => false,
            ];
        }

        foreach ($explicitViewDivisionIds as $id) {
            if (!isset($accessByDivision[$id])) {
                $accessByDivision[$id] = [
                    'mode' => UserJournalPermission::ACCESS_VIEW,
                    'explicit' => true,
                ];
            }
        }

        foreach ($explicitFullDivisionIds as $id) {
            $accessByDivision[$id] = [
                'mode' => UserJournalPermission::ACCESS_FULL,
                'explicit' => true,
            ];
        }

        $viewDivisionIds = [];
        $fullDivisionIds = [];

        foreach ($accessByDivision as $id => $access) {
            $viewDivisionIds[] = (int) $id;

            if ($access['mode'] === UserJournalPermission::ACCESS_FULL) {
                $fullDivisionIds[] = (int) $id;
            }
        }

        sort($viewDivisionIds);
        sort($fullDivisionIds);

        return [
            'division_ids' => $viewDivisionIds,
            'full_division_ids' => $fullDivisionIds,
            'base_division_ids' => array_values(array_unique($baseDivisionIds)),
            'explicit_view_division_ids' => $explicitViewDivisionIds,
            'explicit_full_division_ids' => $explicitFullDivisionIds,
            'access_by_division' => $accessByDivision,
            'can_view' => !empty($viewDivisionIds),
            'can_manage' => !empty($fullDivisionIds),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserJournalPermission>
     */
    public static function permissionsForUser(?int $userId)
    {
        if (!$userId) {
            return collect();
        }

        if (!array_key_exists($userId, self::$permissionsCache)) {
            self::$permissionsCache[$userId] = UserJournalPermission::query()
                ->where('user_id', $userId)
                ->get();
        }

        return self::$permissionsCache[$userId];
    }
}
