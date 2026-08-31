<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalEntry;
use App\Models\JournalTemplate;
use App\Models\UserFavorite;
use App\Support\DirectoryAccessScope;
use App\Support\UserJournalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index()
    {
        $journals = $this->accessibleJournals();

        $favoriteIds = UserFavorite::query()
            ->where('user_id', (int) session('user_id'))
            ->where('entity_type', UserFavorite::TYPE_JOURNAL)
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $journals = $journals
            ->map(function (JournalTemplate $journal) use ($favoriteIds) {
                $journal->is_favorite = in_array((int) $journal->id, $favoriteIds, true);

                return $journal;
            })
            ->sortByDesc(function (JournalTemplate $journal) {
                return $journal->is_favorite ? 1 : 0;
            })
            ->values();

        return view('user.dashboard', compact('journals'));
    }

    public function leader(Request $request)
    {
        abort_unless(in_array(session('user_role'), ['foreman', 'admin'], true), 403, 'Нет доступа к дашборду руководителя');

        $period = (int) $request->input('period', 30);
        if (!in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        $selectedDivisionId = null;
        $availableDivisionIds = [];

        if (session('user_role') === 'admin') {
            $availableDivisionIds = \App\Support\DivisionTree::managedDivisionIds(session('user_division_id'), session('user_role'));
            $requestedDivisionId = (int) $request->input('division_id', 0);

            if ($requestedDivisionId > 0 && in_array($requestedDivisionId, $availableDivisionIds, true)) {
                $selectedDivisionId = $requestedDivisionId;
            }
        }

        $scopes = $this->accessibleJournalScopes($selectedDivisionId);
        $today = now()->toDateString();
        $dateFrom = now()->copy()->subDays($period - 1)->toDateString();
        $dateLabels = collect(range(0, $period - 1))
            ->map(fn ($offset) => now()->copy()->subDays($period - 1 - $offset)->format('Y-m-d'))
            ->values();

        $entriesTodayCount = $this->buildScopedEntriesQuery($scopes)
            ->whereDate('created_at', $today)
            ->count();

        $rejectedPeriodCount = $this->buildScopedEntriesQuery($scopes)
            ->where('status', 'rejected')
            ->whereDate('updated_at', '>=', $dateFrom)
            ->count();

        $deletedPeriodCount = $this->buildScopedEntriesQuery($scopes, true)
            ->whereNotNull('deleted_at')
            ->whereDate('deleted_at', '>=', $dateFrom)
            ->count();

        $dailyCreated = $this->buildScopedEntriesQuery($scopes)
            ->selectRaw('date(created_at) as chart_date, count(*) as total')
            ->whereDate('created_at', '>=', $dateFrom)
            ->groupByRaw('date(created_at)')
            ->pluck('total', 'chart_date');

        $dailyRejected = $this->buildScopedEntriesQuery($scopes)
            ->selectRaw('date(updated_at) as chart_date, count(*) as total')
            ->where('status', 'rejected')
            ->whereDate('updated_at', '>=', $dateFrom)
            ->groupByRaw('date(updated_at)')
            ->pluck('total', 'chart_date');

        $dailyDeleted = $this->buildScopedEntriesQuery($scopes, true)
            ->selectRaw('date(deleted_at) as chart_date, count(*) as total')
            ->whereNotNull('deleted_at')
            ->whereDate('deleted_at', '>=', $dateFrom)
            ->groupByRaw('date(deleted_at)')
            ->pluck('total', 'chart_date');

        $dailyChart = [
            'labels' => $dateLabels->all(),
            'entries' => $dateLabels->map(fn ($date) => (int) ($dailyCreated[$date] ?? 0))->all(),
            'rejected' => $dateLabels->map(fn ($date) => (int) ($dailyRejected[$date] ?? 0))->all(),
            'deleted' => $dateLabels->map(fn ($date) => (int) ($dailyDeleted[$date] ?? 0))->all(),
        ];

        $directoryScopes = $this->accessibleDirectoryScopes($selectedDivisionId);

        $directoryValuesTodayCount = $this->buildScopedDirectoryValuesQuery($directoryScopes)
            ->whereDate('created_at', $today)
            ->count();

        $directoryUpdatedPeriodCount = $this->buildScopedDirectoryValuesQuery($directoryScopes)
            ->whereDate('updated_at', '>=', $dateFrom)
            ->whereColumn('updated_at', '!=', 'created_at')
            ->count();

        $directoryDeletedPeriodCount = $this->buildScopedDirectoryValuesQuery($directoryScopes, true)
            ->whereNotNull('deleted_at')
            ->whereDate('deleted_at', '>=', $dateFrom)
            ->count();

        $directoryDailyCreated = $this->buildScopedDirectoryValuesQuery($directoryScopes)
            ->selectRaw('date(created_at) as chart_date, count(*) as total')
            ->whereDate('created_at', '>=', $dateFrom)
            ->groupByRaw('date(created_at)')
            ->pluck('total', 'chart_date');

        $directoryDailyUpdated = $this->buildScopedDirectoryValuesQuery($directoryScopes)
            ->selectRaw('date(updated_at) as chart_date, count(*) as total')
            ->whereDate('updated_at', '>=', $dateFrom)
            ->whereColumn('updated_at', '!=', 'created_at')
            ->groupByRaw('date(updated_at)')
            ->pluck('total', 'chart_date');

        $directoryDailyDeleted = $this->buildScopedDirectoryValuesQuery($directoryScopes, true)
            ->selectRaw('date(deleted_at) as chart_date, count(*) as total')
            ->whereNotNull('deleted_at')
            ->whereDate('deleted_at', '>=', $dateFrom)
            ->groupByRaw('date(deleted_at)')
            ->pluck('total', 'chart_date');

        $directoryDailyChart = [
            'labels' => $dateLabels->all(),
            'created' => $dateLabels->map(fn ($date) => (int) ($directoryDailyCreated[$date] ?? 0))->all(),
            'updated' => $dateLabels->map(fn ($date) => (int) ($directoryDailyUpdated[$date] ?? 0))->all(),
            'deleted' => $dateLabels->map(fn ($date) => (int) ($directoryDailyDeleted[$date] ?? 0))->all(),
        ];

        $problemDivisions = $this->buildProblemDivisions($scopes, $dateFrom);
        $problemDirectories = $this->buildProblemDirectories($directoryScopes, $dateFrom);
        $divisions = session('user_role') === 'admin'
            ? Division::query()->whereIn('id', $availableDivisionIds)->orderBy('name')->get()
            : collect();

        $cards = [
            'entries_today' => $entriesTodayCount,
            'rejected_period' => $rejectedPeriodCount,
            'deleted_period' => $deletedPeriodCount,
            'directory_values_today' => $directoryValuesTodayCount,
            'directory_updated_period' => $directoryUpdatedPeriodCount,
            'directory_deleted_period' => $directoryDeletedPeriodCount,
        ];

        return view('user.leader-dashboard', [
            'cards' => $cards,
            'period' => $period,
            'selectedDivisionId' => $selectedDivisionId,
            'divisions' => $divisions,
            'dailyChart' => $dailyChart,
            'directoryDailyChart' => $directoryDailyChart,
            'problemDivisions' => $problemDivisions,
            'problemDirectories' => $problemDirectories,
        ]);
    }

    private function accessibleJournals(): Collection
    {
        return JournalTemplate::query()
            ->where('is_active', true)
            ->with('divisions')
            ->orderBy('name')
            ->get()
            ->filter(function (JournalTemplate $journal) {
                $access = UserJournalAccess::resolveForJournal(
                    $journal,
                    session('user_id'),
                    session('user_role'),
                    session('user_division_id')
                );

                if (!$access['can_view']) {
                    return false;
                }

                $journal->user_access_mode = $access['can_manage'] ? 'full' : 'view';
                $journal->user_access_division_ids = $access['division_ids'];

                return true;
            })
            ->map(function (JournalTemplate $journal) {
                $journal->is_favorite = false;

                return $journal;
            })
            ->values();
    }

    private function accessibleJournalScopes(?int $selectedDivisionId = null): Collection
    {
        return $this->accessibleJournals()
            ->map(function (JournalTemplate $journal) use ($selectedDivisionId) {
                $divisionIds = collect($journal->user_access_division_ids ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0);

                if ($selectedDivisionId !== null) {
                    $divisionIds = $divisionIds->filter(fn ($id) => $id === $selectedDivisionId);
                }

                $divisionIds = $divisionIds->values();

                if ($divisionIds->isEmpty()) {
                    return null;
                }

                return [
                    'journal' => $journal,
                    'division_ids' => $divisionIds->all(),
                ];
            })
            ->filter()
            ->values();
    }

    private function accessibleDirectories(): Collection
    {
        $directoryIds = DirectoryAccessScope::accessibleDirectoryIdsForUser(
            (int) session('user_id'),
            session('user_role'),
            session('user_division_id') !== null ? (int) session('user_division_id') : null
        );

        return Directory::query()
            ->with('divisions:id,name')
            ->whereIn('id', $directoryIds)
            ->orderBy('name')
            ->get();
    }

    private function accessibleDirectoryScopes(?int $selectedDivisionId = null): Collection
    {
        return $this->accessibleDirectories()
            ->map(function (Directory $directory) use ($selectedDivisionId) {
                $divisionIds = $directory->divisions
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                if ($selectedDivisionId !== null && $divisionIds->isNotEmpty()) {
                    $divisionIds = $divisionIds->filter(fn ($id) => $id === $selectedDivisionId)->values();
                }

                if ($selectedDivisionId !== null && $directory->divisions->isNotEmpty() && $divisionIds->isEmpty()) {
                    return null;
                }

                return [
                    'directory' => $directory,
                    'division_ids' => $divisionIds->all(),
                ];
            })
            ->filter()
            ->values();
    }

    private function buildScopedEntriesQuery(Collection $scopes, bool $withTrashed = false): Builder
    {
        $query = $withTrashed ? JournalEntry::query()->withTrashed() : JournalEntry::query();

        if ($scopes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outerQuery) use ($scopes) {
            foreach ($scopes as $scope) {
                $outerQuery->orWhere(function (Builder $innerQuery) use ($scope) {
                    $innerQuery
                        ->where('journal_template_id', (int) $scope['journal']->id)
                        ->whereIn('division_id', $scope['division_ids']);
                });
            }
        });
    }

    private function buildScopedDirectoryValuesQuery(Collection $scopes, bool $withTrashed = false): Builder
    {
        $query = $withTrashed ? DirectoryValue::query()->withTrashed() : DirectoryValue::query();

        if ($scopes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('directory_id', $scopes->pluck('directory.id')->map(fn ($id) => (int) $id)->all());
    }

    private function buildWarehouseStockData(Collection $scopes): array
    {
        $receiptJournalIds = $scopes
            ->filter(function ($scope) {
                $code = (string) ($scope['journal']->code ?? '');

                return $code === 'warehouse_receipt' || str_starts_with($code, 'warehouse_receipt_import');
            })
            ->pluck('journal.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $issueJournalIds = $scopes
            ->filter(function ($scope) {
                $code = (string) ($scope['journal']->code ?? '');

                return $code === 'warehouse_issue' || str_starts_with($code, 'warehouse_issue_import');
            })
            ->pluck('journal.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $receiptEntries = empty($receiptJournalIds)
            ? collect()
            : $this->buildScopedEntriesQuery($scopes)
                ->whereIn('journal_template_id', $receiptJournalIds)
                ->where('status', '!=', 'rejected')
                ->get(['id', 'data']);

        $issueEntries = empty($issueJournalIds)
            ? collect()
            : $this->buildScopedEntriesQuery($scopes)
                ->whereIn('journal_template_id', $issueJournalIds)
                ->where('status', '!=', 'rejected')
                ->get(['id', 'data']);

        $receiptsByItem = [];
        foreach ($receiptEntries as $entry) {
            $itemId = (int) data_get($entry->data, 'item');
            $quantity = (float) data_get($entry->data, 'quantity', 0);

            if ($itemId <= 0) {
                continue;
            }

            $receiptsByItem[$itemId] = ($receiptsByItem[$itemId] ?? 0) + $quantity;
        }

        $issuesByItem = [];
        $issueCountByItem = [];
        foreach ($issueEntries as $entry) {
            $itemId = (int) data_get($entry->data, 'item');
            $quantity = (float) data_get($entry->data, 'quantity', 0);

            if ($itemId <= 0) {
                continue;
            }

            $issuesByItem[$itemId] = ($issuesByItem[$itemId] ?? 0) + $quantity;
            $issueCountByItem[$itemId] = ($issueCountByItem[$itemId] ?? 0) + 1;
        }

        $itemIds = collect(array_merge(array_keys($receiptsByItem), array_keys($issuesByItem)))
            ->unique()
            ->values();

        $items = DirectoryValue::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'value', 'data'])
            ->keyBy('id');

        $stockRows = $itemIds->map(function ($itemId) use ($items, $receiptsByItem, $issuesByItem, $issueCountByItem) {
            $item = $items->get($itemId);
            $received = round((float) ($receiptsByItem[$itemId] ?? 0), 3);
            $issued = round((float) ($issuesByItem[$itemId] ?? 0), 3);
            $balance = round($received - $issued, 3);

            return [
                'item_id' => (int) $itemId,
                'item_name' => (string) ($item->value ?? ('ID ' . $itemId)),
                'item_number' => (string) data_get($item?->data, 'item_number', ''),
                'unit' => (string) data_get($item?->data, 'unit', ''),
                'received_quantity' => $received,
                'issued_quantity' => $issued,
                'issue_count' => (int) ($issueCountByItem[$itemId] ?? 0),
                'stock_balance' => $balance,
            ];
        })
            ->sortBy('stock_balance')
            ->values()
            ->all();

        $issueRows = collect($stockRows)
            ->filter(fn ($row) => (float) $row['issued_quantity'] > 0)
            ->sortByDesc('issued_quantity')
            ->values()
            ->all();

        return [
            'stock_rows' => $stockRows,
            'issue_rows' => $issueRows,
            'positive_positions' => collect($stockRows)->filter(fn ($row) => (float) $row['stock_balance'] > 0)->count(),
        ];
    }

    private function buildProblemDivisions(Collection $scopes, string $dateFrom): Collection
    {
        $divisionIds = $scopes
            ->pluck('division_ids')
            ->flatten()
            ->unique()
            ->values();

        $divisionNames = Division::query()
            ->whereIn('id', $divisionIds)
            ->pluck('name', 'id');

        $entries = $this->buildScopedEntriesQuery($scopes, true)
            ->where(function (Builder $query) use ($dateFrom) {
                $query
                    ->whereDate('created_at', '>=', $dateFrom)
                    ->orWhere(function (Builder $subQuery) use ($dateFrom) {
                        $subQuery->where('status', 'rejected')
                            ->whereDate('updated_at', '>=', $dateFrom);
                    })
                    ->orWhere(function (Builder $subQuery) use ($dateFrom) {
                        $subQuery->whereNotNull('deleted_at')
                            ->whereDate('deleted_at', '>=', $dateFrom);
                    });
            })
            ->get(['division_id', 'status', 'deleted_at']);

        return $entries
            ->groupBy('division_id')
            ->map(function (Collection $divisionEntries, $divisionId) use ($divisionNames) {
                $entriesCount = $divisionEntries->count();
                $rejectedCount = $divisionEntries->where('status', 'rejected')->count();
                $deletedCount = $divisionEntries->filter(fn ($entry) => $entry->deleted_at !== null)->count();
                $problemScore = ($rejectedCount * 2) + ($deletedCount * 3);

                return [
                    'division_id' => (int) $divisionId,
                    'division_name' => (string) ($divisionNames[(int) $divisionId] ?? ('Подразделение #' . $divisionId)),
                    'entries_count' => $entriesCount,
                    'rejected_count' => $rejectedCount,
                    'deleted_count' => $deletedCount,
                    'problem_score' => $problemScore,
                ];
            })
            ->sortByDesc('problem_score')
            ->take(10)
            ->values();
    }

    private function buildProblemDirectories(Collection $scopes, string $dateFrom): Collection
    {
        $directoryIds = $scopes->pluck('directory.id')->map(fn ($id) => (int) $id)->values();
        $directoryNames = Directory::query()
            ->whereIn('id', $directoryIds)
            ->pluck('name', 'id');

        $values = $this->buildScopedDirectoryValuesQuery($scopes, true)
            ->where(function (Builder $query) use ($dateFrom) {
                $query
                    ->whereDate('created_at', '>=', $dateFrom)
                    ->orWhere(function (Builder $subQuery) use ($dateFrom) {
                        $subQuery->whereDate('updated_at', '>=', $dateFrom)
                            ->whereColumn('updated_at', '!=', 'created_at');
                    })
                    ->orWhere(function (Builder $subQuery) use ($dateFrom) {
                        $subQuery->whereNotNull('deleted_at')
                            ->whereDate('deleted_at', '>=', $dateFrom);
                    });
            })
            ->get(['directory_id', 'created_at', 'updated_at', 'deleted_at']);

        return $values
            ->groupBy('directory_id')
            ->map(function (Collection $directoryValues, $directoryId) use ($directoryNames, $dateFrom) {
                $createdCount = $directoryValues->filter(function (DirectoryValue $value) use ($dateFrom) {
                    return $value->created_at !== null
                        && $value->created_at->toDateString() >= $dateFrom;
                })->count();
                $updatedCount = $directoryValues->filter(function (DirectoryValue $value) use ($dateFrom) {
                    return $value->updated_at !== null
                        && $value->created_at !== null
                        && !$value->updated_at->equalTo($value->created_at)
                        && $value->updated_at->toDateString() >= $dateFrom;
                })->count();
                $deletedCount = $directoryValues->filter(function (DirectoryValue $value) use ($dateFrom) {
                    return $value->deleted_at !== null
                        && $value->deleted_at->toDateString() >= $dateFrom;
                })->count();
                $problemScore = $updatedCount + ($deletedCount * 2);

                return [
                    'directory_id' => (int) $directoryId,
                    'directory_name' => (string) ($directoryNames[(int) $directoryId] ?? ('Справочник #' . $directoryId)),
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'deleted_count' => $deletedCount,
                    'problem_score' => $problemScore,
                ];
            })
            ->sortByDesc('problem_score')
            ->take(10)
            ->values();
    }
}
