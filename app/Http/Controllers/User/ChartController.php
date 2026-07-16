<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\JournalEntry;
use App\Models\JournalTemplate;
use App\Support\DivisionTree;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function index()
    {
        $divisionId = session('user_division_id');
        $managedDivisionIds = DivisionTree::managedDivisionIds($divisionId, session('user_role'));

        $journals = JournalTemplate::query()
            ->where('is_active', true)
            ->whereHas('divisions', function ($q) use ($managedDivisionIds) {
                $q->whereIn('divisions.id', $managedDivisionIds);
            })
            ->orderBy('name')
            ->get();

        $divisions = Division::whereIn('id', $managedDivisionIds)->orderBy('name')->get();

        return view('user.charts.index', compact('journals', 'divisions'));
    }

    public function data(Request $request)
    {
        $role = session('user_role');
        $divisionId = session('user_division_id');
        $userId = session('user_id');
        $managedDivisionIds = DivisionTree::managedDivisionIds($divisionId, $role);

        $journals = JournalTemplate::query()
            ->where('is_active', true)
            ->whereHas('divisions', function ($q) use ($managedDivisionIds) {
                $q->whereIn('divisions.id', $managedDivisionIds);
            })
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($journals as $journal) {
            $schema = $journal->schema ?? [];

            $numericFields = collect($schema)
                ->filter(function ($field) {
                    return in_array($field['type'] ?? '', ['number', 'calc']);
                })
                ->values()
                ->toArray();

            if (count($numericFields) === 0) {
                $result[] = [
                    'journal_id' => $journal->id,
                    'journal_name' => $journal->name,
                    'fields' => [],
                    'charts' => [],
                ];

                continue;
            }

            $query = JournalEntry::query()
                ->where('journal_template_id', $journal->id)
                ->orderBy('entry_date')
                ->orderBy('id');

            if ($role === 'worker') {
                $query->where('division_id', $divisionId)
                    ->where('user_id', $userId);
            }

            if ($role === 'foreman') {
                $query->where('division_id', $divisionId);
            }

            if ($role === 'admin') {
                if ($request->filled('division_id') && in_array((int) $request->division_id, $managedDivisionIds, true)) {
                    $query->where('division_id', $request->division_id);
                } else {
                    $query->whereIn('division_id', $managedDivisionIds);
                }
            }

            if ($request->filled('date_from')) {
                $query->whereDate('entry_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('entry_date', '<=', $request->date_to);
            }

            $entries = $query->get();

            $charts = [];

            foreach ($numericFields as $field) {
                $key = $field['key'];
                $label = $field['label'] ?? $key;

                $points = [];

                foreach ($entries as $entry) {
                    $data = $entry->data ?? [];

                    if (!is_array($data)) {
                        continue;
                    }

                    $value = $data[$key] ?? null;

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $value = str_replace(',', '.', (string)$value);

                    if (!is_numeric($value)) {
                        continue;
                    }

                    $points[] = [
                        'entry_id' => $entry->id,
                        'date' => $entry->entry_date
                            ? $entry->entry_date->format('Y-m-d')
                            : $entry->created_at->format('Y-m-d'),
                        'value' => (float)$value,
                    ];
                }

                $charts[] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $field['type'] ?? 'number',
                    'points' => $points,
                ];
            }

            $result[] = [
                'journal_id' => $journal->id,
                'journal_name' => $journal->name,
                'fields' => $numericFields,
                'charts' => $charts,
            ];
        }

        return response()->json([
            'success' => true,
            'journals' => $result,
        ]);
    }
}
