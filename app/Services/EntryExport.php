<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Meet;
use App\Support\RaceTime;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 運営用のエントリー一覧（Excel）。
 * 列はエントリーシートの「申込み」欄と同じ並びにし、団体名と受付情報を後ろに足している。
 * 1枚目に全種目、2枚目以降に種目ごとのシート（申請タイム順）を作る。
 */
class EntryExport
{
    private const HEADER = [
        'No', '団体名', '姓', '名', 'フリガナ', '学年', '性別', '所属陸協', '申込み種目', '申請タイム', '自己ベスト',
        '種別', 'PRしたい一言', '料金', '受付番号', '申込区分', '状態', '入金日時', '申込者', '電話番号', 'メール',
    ];

    public function build(Meet $meet, string $status): Spreadsheet
    {
        $items = $this->items($meet, $status);

        $book = new Spreadsheet;
        $this->fill($book->getActiveSheet(), '申込み一覧', $items);
        foreach ($items->groupBy('event_id') as $group) {
            $this->fill($book->createSheet(), $group->first()->event->name, $group);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * @return Collection<int, EntryItem>
     */
    private function items(Meet $meet, string $status): Collection
    {
        return EntryItem::query()
            ->with('entry.user', 'athlete', 'event')
            ->whereHas('entry', fn ($q) => $q->where('meet_id', $meet->id)
                ->when($status !== 'all', fn ($q) => $q->where('status', $status)))
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->event->sort_order <=> $b->event->sort_order,
                fn ($a, $b) => $a->event_id <=> $b->event_id,
                // 申請タイムの速い順（読めないタイムは最後）
                fn ($a, $b) => (RaceTime::toSeconds($a->declared_record) ?? INF) <=> (RaceTime::toSeconds($b->declared_record) ?? INF),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, EntryItem>  $items
     */
    private function fill(Worksheet $sheet, string $title, Collection $items): void
    {
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', '・', $title), 0, 31));
        $sheet->fromArray(self::HEADER, null, 'A1');
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);
        $sheet->getStyle('A1:U1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
        $sheet->freezePane('C2');

        foreach ($items->values() as $i => $item) {
            $a = $item->athlete;
            $e = $item->entry;
            $row = $i + 2;
            $values = [
                $i + 1,
                $e->user->isTeam() ? $e->user->team_name : ($e->user->team_name ?: '個人'),
                $a->last_name,
                $a->first_name,
                $a->kana,
                $a->grade,
                $a->genderLabel(),
                $a->association,
                $item->event->name,
                $item->declared_record,
                $item->personal_best,
                $a->category,
                $item->pr_comment,
                $item->fee,
                $e->id,
                $e->user->typeLabel(),
                $e->statusLabel(),
                $e->paid_at?->format('Y/m/d H:i'),
                $e->user->name,
                $e->user->phone,
                $e->user->email,
            ];
            foreach ($values as $col => $value) {
                $cell = $sheet->getCell([$col + 1, $row]);
                if (is_int($value)) {
                    $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                } else {
                    // 文字は文字のまま入れる（「=」で始まる入力が数式にならないように）
                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            }
        }

        foreach (range(1, count(self::HEADER)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }

    public static function statusOptions(): array
    {
        return [Entry::STATUS_PAID => '入金済み', Entry::STATUS_PENDING => '決済待ち', Entry::STATUS_CANCELLED => '取消', 'all' => 'すべて'];
    }
}
