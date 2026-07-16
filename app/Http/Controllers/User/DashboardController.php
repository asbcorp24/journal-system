<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\JournalTemplate;
use App\Support\DivisionTree;
use App\Support\UserJournalAccess;

class DashboardController extends Controller
{
    public function index()
    {
        $journals = JournalTemplate::query()
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
            ->values();

        return view('user.dashboard', compact('journals'));
    }
}
