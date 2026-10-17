<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meet;
use App\Models\StartListEntry;
use App\Services\ProgramBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * プログラム作成：タイムテーブルの設定 → 組分け → 暫定版の公開 → 組変更 → 確定。
 */
class ProgramController extends Controller
{
    public function __construct(private ProgramBuilder $builder) {}

    public function show(Meet $meet): View
    {
        $races = $this->builder->syncRaces($meet)
            ->load('startListEntries.entryItem.athlete', 'startListEntries.entryItem.entry.user');

        return view('admin.program.show', [
            'meet' => $meet,
            'races' => $races,
            'unassigned' => $this->builder->unassigned($meet),
            'withdrawn' => $this->builder->withdrawnCount($meet),
            'generated' => $races->contains(fn ($r) => $r->startListEntries->isNotEmpty()),
        ]);
    }

    /**
     * タイムテーブルの設定（開始時刻・1組の人数・組の間隔・並び）を保存する。
     */
    public function updateRaces(Request $request, Meet $meet): RedirectResponse
    {
        $data = $request->validate([
            'races' => ['array'],
            'races.*.start_time' => ['nullable', 'date_format:H:i'],
            'races.*.heat_size' => ['required', 'integer', 'min:1', 'max:100'],
            'races.*.heat_interval' => ['required', 'integer', 'min:0', 'max:180'],
            'races.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'program_notes' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'races.*.start_time' => '開始時刻', 'races.*.heat_size' => '1組の人数', 'races.*.heat_interval' => '組の間隔',
        ]);

        $races = $meet->races()->get()->keyBy('id');
        $sizeChanged = false;
        foreach ($data['races'] ?? [] as $id => $values) {
            $race = $races->get((int) $id);
            if (! $race) {
                continue;
            }
            $sizeChanged = $sizeChanged || (int) $values['heat_size'] !== $race->heat_size
                || $request->boolean("races.$id.fastest_last") !== $race->fastest_last;
            $race->update([
                'start_time' => $values['start_time'] ?? null,
                'heat_size' => (int) $values['heat_size'],
                'heat_interval' => (int) $values['heat_interval'],
                'sort_order' => (int) ($values['sort_order'] ?? $race->sort_order),
                'fastest_last' => $request->boolean("races.$id.fastest_last"),
            ]);
        }
        $meet->update(['program_notes' => $data['program_notes'] ?? null]);

        $message = 'タイムテーブルの設定を保存しました。';
        if ($sizeChanged) {
            $message .= '1組の人数・並びを変えた場合は「組分けを作り直す」を押すと反映されます。';
        }

        return back()->with('status', $message);
    }

    public function generate(Meet $meet): RedirectResponse
    {
        $this->builder->generate($meet);

        return back()->with('status', '入金済みの申込から組分けを作りました（申請タイム順・同タイムは自己ベスト順）。');
    }

    /**
     * 組を動かす（組変更希望の反映など）。
     */
    public function moveHeats(Request $request, Meet $meet): RedirectResponse
    {
        $moved = $this->builder->moveHeats($meet, (array) $request->input('heats', []));

        return back()->with('status', $moved > 0 ? $moved.'人の組を変更しました。' : '変更はありませんでした。');
    }

    /**
     * 公開の状態を変える：未公開 / 暫定版を公開 / 確定（プログラム完成）。
     */
    public function status(Request $request, Meet $meet): RedirectResponse
    {
        $status = $request->validate([
            'status' => ['required', Rule::in([Meet::START_LIST_NONE, Meet::START_LIST_PROVISIONAL, Meet::START_LIST_FINAL])],
        ])['status'];

        if ($status !== Meet::START_LIST_NONE && StartListEntry::whereIn('race_id', $meet->races()->pluck('id'))->doesntExist()) {
            return back()->withErrors(['status' => '先に組分けを作ってください。']);
        }
        $meet->update(['start_list_status' => $status, 'start_list_published_at' => $status === Meet::START_LIST_NONE ? null : now()]);

        return back()->with('status', match ($status) {
            Meet::START_LIST_PROVISIONAL => 'タイムテーブルとスタートリストを「暫定版」として公開しました。',
            Meet::START_LIST_FINAL => 'タイムテーブルとスタートリストを確定しました。大会プログラムが完成し、大会開催ページに表示されます。',
            default => 'タイムテーブルとスタートリストを非公開にしました。',
        });
    }

    /**
     * スタートリストのExcel（レースごとのシート）。
     */
    public function export(Meet $meet): StreamedResponse
    {
        $races = $meet->races()->with('event', 'startListEntries.entryItem.athlete', 'startListEntries.entryItem.entry.user')->get();
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($races as $race) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', '・', $race->name()), 0, 31));
            $sheet->fromArray(['組', 'スタート', 'ナンバー', '氏名', 'フリガナ', '所属', '種別・学年', '部門', '所属陸協', '申請タイム', '自己ベスト'], null, 'A1');
            $row = 2;
            foreach ($race->startListEntries as $sle) {
                $item = $sle->entryItem;
                $a = $item->athlete;
                $sheet->fromArray([
                    $sle->heat, $race->heatStartTime($sle->heat), $sle->position, $a->fullName(), $a->kana,
                    $item->entry->user->isIndividual() ? ($item->entry->user->team_name ?: '個人') : $item->entry->user->displayName(),
                    trim($a->category.' '.($a->grade ? $a->grade.'年' : '')), $a->divisionLabel(), $a->association, $item->declared_record, $item->personal_best,
                ], null, 'A'.$row++, true);
            }
            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        if ($book->getSheetCount() === 0) {
            $book->createSheet()->setTitle('スタートリスト');
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'),
            $meet->held_on->format('Ymd').'_スタートリスト.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
