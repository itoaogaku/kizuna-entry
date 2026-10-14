<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\Meet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetController extends Controller
{
    public function index(): View
    {
        $meets = Meet::withCount(['entries as paid_count' => fn ($q) => $q->where('status', Entry::STATUS_PAID)])
            ->withSum(['entries as paid_total' => fn ($q) => $q->where('status', Entry::STATUS_PAID)], 'total_amount')
            ->orderByDesc('held_on')
            ->get();

        return view('admin.meets.index', compact('meets'));
    }

    public function create(): View
    {
        return view('admin.meets.form', ['meet' => new Meet(['max_events_per_athlete' => 2])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $meet = Meet::create($this->validated($request));

        return redirect()->route('admin.meets.show', $meet)->with('status', '大会を作成しました。続けて種目を追加してください。');
    }

    public function show(Meet $meet): View
    {
        $meet->load('events');

        return view('admin.meets.show', [
            'meet' => $meet,
            'reserved' => $meet->events->mapWithKeys(fn ($e) => [$e->id => $e->reservedCount()]),
        ]);
    }

    public function edit(Meet $meet): View
    {
        return view('admin.meets.form', compact('meet'));
    }

    public function update(Request $request, Meet $meet): RedirectResponse
    {
        $meet->update($this->validated($request));

        return redirect()->route('admin.meets.show', $meet)->with('status', '大会情報を更新しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'held_on' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:100'],
            'entry_opens_at' => ['required', 'date'],
            'entry_closes_at' => ['required', 'date', 'after:entry_opens_at'],
            'max_events_per_athlete' => ['required', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]) + ['is_published' => $request->boolean('is_published')];
    }
}
