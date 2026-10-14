<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Meet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function store(Request $request, Meet $meet): RedirectResponse
    {
        $meet->events()->create($this->validated($request));

        return back()->with('status', '種目を追加しました。');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->validated($request));

        return back()->with('status', $event->name.'を更新しました。');
    }

    public function destroy(Event $event): RedirectResponse
    {
        if ($event->entryItems()->exists()) {
            return back()->withErrors(['event' => $event->name.'にはエントリーがあるため削除できません。']);
        }
        $event->delete();

        return back()->with('status', '種目を削除しました。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'fee' => ['required', 'integer', 'min:50', 'max:100000'], // Stripeの最低決済額は50円
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['gender'] = $data['gender'] ?? null;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
