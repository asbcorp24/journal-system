<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\JournalTemplate;
use App\Models\UserFavorite;
use App\Support\DivisionTree;
use App\Support\UserJournalAccess;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'in:journal,directory'],
            'entity_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->ensureFavoriteEntityAccess($validated['entity_type'], (int) $validated['entity_id']);

        $favorite = UserFavorite::query()->where([
            'user_id' => (int) session('user_id'),
            'entity_type' => $validated['entity_type'],
            'entity_id' => (int) $validated['entity_id'],
        ])->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'is_favorite' => false,
                'message' => 'Убрано из избранного',
            ]);
        }

        UserFavorite::query()->create([
            'user_id' => (int) session('user_id'),
            'entity_type' => $validated['entity_type'],
            'entity_id' => (int) $validated['entity_id'],
        ]);

        return response()->json([
            'success' => true,
            'is_favorite' => true,
            'message' => 'Добавлено в избранное',
        ]);
    }

    private function ensureFavoriteEntityAccess(string $entityType, int $entityId): void
    {
        if ($entityType === UserFavorite::TYPE_JOURNAL) {
            $journal = JournalTemplate::query()->with('divisions')->findOrFail($entityId);
            $access = UserJournalAccess::resolveForJournal(
                $journal,
                session('user_id'),
                session('user_role'),
                session('user_division_id')
            );

            abort_unless($access['can_view'], 403, 'Нет доступа к этому журналу');

            return;
        }

        $directory = Directory::query()->findOrFail($entityId);
        $managedDivisionIds = DivisionTree::managedDivisionIds(session('user_division_id'), session('user_role'));
        $hasAccess = !$directory->divisions()->exists()
            || (!empty($managedDivisionIds) && $directory->divisions()->whereIn('divisions.id', $managedDivisionIds)->exists());

        abort_unless($hasAccess, 403, 'Нет доступа к этому справочнику');
    }
}
