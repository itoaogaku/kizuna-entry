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
        $meet->events()->create($this->validated($request, $meet));

        return back()->with('status', '種目を追加しました。');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->validated($request, $event->meet));

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

    private function validated(Request $request, Meet $meet): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            // 区分別の参加料がある大会では種目の料金は使わないので空欄でよい（Stripeの最低決済額は50円）
            'fee' => $meet->hasCategoryFees() ? ['nullable', 'integer', 'min:0', 'max:100000'] : ['required', 'integer', 'min:50', 'max:100000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['gender'] = $data['gender'] ?? null;
        $data['fee'] = (int) ($data['fee'] ?? 0);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
