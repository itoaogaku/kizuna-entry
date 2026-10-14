<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Meet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntryController extends Controller
{
    public function index(Request $request, Meet $meet): View
    {
        $status = $request->query('status', Entry::STATUS_PAID);

        $entries = $meet->entries()
            ->with('user', 'items.athlete', 'items.event')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        return view('admin.entries.index', compact('meet', 'entries', 'status'));
    }

    /**
     * 選手×種目を1行にしたCSV（Excelで開けるようBOM付きUTF-8）。
     */
    public function export(Request $request, Meet $meet): StreamedResponse
    {
        $status = $request->query('status', Entry::STATUS_PAID);
        Log::info('admin export', ['user' => $request->user()->id, 'meet' => $meet->id]);

        $items = EntryItem::query()
            ->with('entry.user', 'athlete', 'event')
            ->whereHas('entry', fn ($q) => $q->where('meet_id', $meet->id)
                ->when($status !== 'all', fn ($q) => $q->where('status', $status)))
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->event->sort_order <=> $b->event->sort_order,
                fn ($a, $b) => $a->event_id <=> $b->event_id,
                fn ($a, $b) => strcmp($a->athlete->last_kana.$a->athlete->first_kana, $b->athlete->last_kana.$b->athlete->first_kana),
            ]);

        $filename = $meet->held_on->format('Ymd').'_entries.csv';

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['受付番号', '種目', '氏名', 'フリガナ', '性別', '生年月日', '区分', '陸連登録番号', '所属', '申告記録', '参加費', '状態', '入金日時', '代表者', '電話番号', 'メール']);
            foreach ($items as $item) {
                $a = $item->athlete;
                $e = $item->entry;
                fputcsv($out, array_map(fn ($v) => self::safeCell($v), [
                    $e->id,
                    $item->event->name,
                    $a->fullName(),
                    $a->fullKana(),
                    $a->genderLabel(),
                    $a->birthdate->format('Y/m/d'),
                    $a->category,
                    $a->jaaf_id,
                    $e->user->team_name,
                    $item->declared_record,
                    $item->fee,
                    $e->statusLabel(),
                    $e->paid_at?->format('Y/m/d H:i'),
                    $e->user->name,
                    $e->user->phone,
                    $e->user->email,
                ]));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 利用者が入力した値がExcelで数式として実行されないようにする。
     */
    private static function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
