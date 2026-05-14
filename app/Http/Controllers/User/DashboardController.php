<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\JournalTemplate;

class DashboardController extends Controller
{
    public function index()
    {
        $divisionId = session('user_division_id');

        $journals = JournalTemplate::query()
            ->where('is_active', true)
            ->with('divisions')
            ->whereHas('divisions', function ($q) use ($divisionId) {
                $q->where('divisions.id', $divisionId);
            })
            ->orderBy('name')
            ->get();

        return view('user.dashboard', compact('journals'));
    }
}
