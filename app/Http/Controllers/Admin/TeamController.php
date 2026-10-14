<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $teams = User::withCount(['athletes' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('team_kana')
            ->orderBy('team_name')
            ->get();

        return view('admin.teams.index', compact('teams'));
    }
}
