<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Meet;
use App\Support\EntryOrder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * プログラム用Excel（大会プログラムと同じ形）。
 * - 「競技日程」：競技時刻を入れると、招集の開始（30分前）と完了（15分前）が計算される
 * - レース（種目×性別）ごとのシート：組分け案つきのスタートリスト（ORD・氏名・ﾌﾘｶﾞﾅ・学年・所属・陸協）
 * 運営はこのExcelで組・時刻を調整し、PDFにして「大会資料（PDF）」に貼る。
 */
class ProgramWorkbook
{
    private const FONT = 'Yu Gothic';

    public function build(Meet $meet): Spreadsheet
    {
        $races = $this->races($meet);

        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(10);
        $this->schedule($book->getActiveSheet(), $meet, $races);
        foreach ($races as $race) {
            $this->startList($book->createSheet(), $meet, $race);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * 入金済みの申込を、レース（種目×性別）ごとに申請タイム順で並べて組に分ける。
     *
     * @return Collection<int, array{name: string, heats: array<int, Collection<int, EntryItem>>}>
     */
    private function races(Meet $meet): Collection
    {
        $items = EntryOrder::sort(EntryItem::query()
            ->with('athlete', 'event', 'entry.user')
            ->whereHas('entry', fn ($q) => $q->where('meet_id', $meet->id)->where('status', Entry::STATUS_PAID))
            ->get());

        $races = collect();
        foreach ($meet->events()->get() as $event) {
            foreach (['M', 'F'] as $gender) {
                $runners = $items->filter(fn (EntryItem $i) => $i->event_id === $event->id && $i->athlete->gender === $gender)->values();
                if ($runners->isEmpty()) {
                    continue;
                }
                $size = config('kizuna.heat_size.'.$event->name) ?? config('kizuna.heat_size.default', 40);
                $races->push([
                    'name' => Athlete::GENDERS[$gender].'子'.$event->name,
                    'gender' => Athlete::GENDERS[$gender].'子',
                    'event' => $event->name,
                    'heats' => $this->split($runners, (int) $size),
                ]);
            }
        }

        return $races;
    }

    /**
     * 人数がそろうように組に分け、速い組を最後にする（組の中は申請タイムの速い順）。
     *
     * @return array<int, Collection<int, EntryItem>>
     */
    private function split(Collection $runners, int $size): array
    {
        $count = $runners->count();
        $heatCount = (int) ceil($count / max(1, $size));
        $base = intdiv($count, $heatCount);
        $extra = $count % $heatCount;
        $groups = [];
        $offset = 0;
        for ($g = 0; $g < $heatCount; $g++) {
            $n = $base + ($g < $extra ? 1 : 0);
            $groups[] = $runners->slice($offset, $n)->values();
            $offset += $n;
        }
        $result = [];
        foreach (array_reverse($groups) as $i => $group) {
            $result[$i + 1] = $group;
        }

        return $result;
    }

    private function schedule(Worksheet $sheet, Meet $meet, Collection $races): void
    {
        $sheet->setTitle('競技日程');
        $sheet->setCellValue('A1', $meet->name)->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('I1', now()->format('Y/n/j'))->getStyle('I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue('A2', $meet->held_on->isoFormat('YYYY年M月D日（ddd）'))->getStyle('A2')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A4', '【競技日程】')->getStyle('A4')->getFont()->setBold(true)->setSize(11);

        // 見出し（2段）
        $sheet->fromArray(['競技時刻', '種目', '', '組', '人数', '招集', '', '部門', '★スポンサー杯'], null, 'A5');
        $sheet->fromArray(['', '', '', '', '', '開始', '完了', '', ''], null, 'A6');
        foreach (['A5:A6', 'B5:C6', 'D5:D6', 'E5:E6', 'F5:G5', 'H5:H6', 'I5:I6'] as $range) {
            $sheet->mergeCells($range);
        }
        $sheet->getStyle('A5:I6')->getFont()->setBold(true);

        $row = 7;
        foreach ($races as $race) {
            foreach ($race['heats'] as $heat => $runners) {
                $sheet->setCellValue("B$row", $race['gender']);
                $sheet->setCellValue("C$row", $race['event']);
                $sheet->setCellValue("D$row", $heat);
                $sheet->setCellValue("E$row", $runners->count());
                // 競技時刻を入れると招集の時刻が出る（一次招集：30分前に開始・15分前に完了）
                $sheet->setCellValue("F$row", "=IF(A$row=\"\",\"\",A$row-TIME(0,30,0))");
                $sheet->setCellValue("G$row", "=IF(A$row=\"\",\"\",A$row-TIME(0,15,0))");
                $row++;
            }
        }
        $last = max(7, $row - 1);
        $sheet->getStyle("A7:A$last")->getNumberFormat()->setFormatCode('h:mm');
        $sheet->getStyle("F7:G$last")->getNumberFormat()->setFormatCode('h:mm');
        $sheet->getStyle("A5:I$last")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A5:I$last")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:I$last")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        foreach (['A' => 9, 'B' => 6, 'C' => 9, 'D' => 5, 'E' => 6, 'F' => 8, 'G' => 8, 'H' => 18, 'I' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->setCellValue('A'.($last + 2), '※競技時刻（A列）を「16:10」の形で入れると、招集の開始・完了が自動で入ります。');
        $sheet->getStyle('A'.($last + 2))->getFont()->setSize(9)->getColor()->setRGB('666666');
        $this->pageSetup($sheet, "A1:I$last");
        $this->logo($sheet, 'C'.($last + 4), 140);
    }

    /**
     * レースのスタートリスト。組ごとに1ページ（プログラムと同じ並び）。
     */
    private function startList(Worksheet $sheet, Meet $meet, array $race): void
    {
        $sheet->setTitle(mb_substr($race['name'], 0, 31));
        foreach (['A' => 5, 'B' => 9, 'C' => 9, 'D' => 18, 'E' => 5, 'F' => 24, 'G' => 8, 'H' => 2, 'I' => 10, 'J' => 10, 'K' => 18, 'L' => 9] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $updated = now()->format('Y/n/j').'　更新版';

        $row = 1;
        $lastPrintRow = 1;
        foreach ($race['heats'] as $heat => $runners) {
            $top = $row;
            $sheet->setCellValue("G$row", $updated)->getStyle("G$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G$row")->getFont()->setSize(8);
            $row++;
            $sheet->setCellValue("A$row", $race['name'])->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
            $this->logo($sheet, "D$row", 42);
            $row++;
            $sheet->setCellValue("A$row", $heat.'組')->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
            $row++;
            $sheet->fromArray(['ORD', '氏名', '', 'ﾌﾘｶﾞﾅ', '学年', '所属', '陸協'], null, "A$row");
            $sheet->mergeCells("B$row:C$row");
            $sheet->getStyle("A$row:G$row")->getFont()->setBold(true)->setSize(8);
            $sheet->getStyle("A$row:G$row")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
            // 調整用（印刷範囲の外）
            $sheet->fromArray(['申請タイム', '自己ベスト', '部門', '受付番号'], null, "I$row");
            $sheet->getStyle("I$row:L$row")->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('888888');
            $row++;

            foreach ($runners->values() as $i => $item) {
                $a = $item->athlete;
                $user = $item->entry->user;
                $values = [
                    $i + 1,
                    $a->last_name,
                    $a->first_name,
                    $a->kana ? mb_convert_kana($a->kana, 'k') : '',
                    $a->grade ?? '',
                    $user->isIndividual() ? (string) $user->team_name : $user->displayName(),
                    $a->association ?? '',
                ];
                foreach ($values as $c => $value) {
                    $cell = $sheet->getCell([$c + 1, $row]);
                    is_int($value) ? $cell->setValue($value) : $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
                foreach ([$item->declared_record, $item->personal_best, $a->divisionLabel(), $item->entry_id] as $c => $value) {
                    $sheet->getCell([9 + $c, $row])->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
                $row++;
            }
            $sheet->getStyle("A$top:G".($row - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.($top + 4).':A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D'.($top + 4).':G'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D'.($top + 4).':D'.($row - 1))->getFont()->setSize(7);
            $sheet->getStyle('I'.($top + 4).':L'.($row - 1))->getFont()->getColor()->setRGB('888888');
            $sheet->getStyle('A'.($top + 4).':G'.($row - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $lastPrintRow = $row - 1;
            $sheet->setBreak('A'.($row - 1), Worksheet::BREAK_ROW);
            $row++;
        }

        $this->pageSetup($sheet, "A1:G$lastPrintRow");
    }

    private function pageSetup(Worksheet $sheet, string $printArea): void
    {
        $sheet->setShowGridlines(false);
        $setup = $sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $setup->setFitToWidth(1)->setFitToHeight(0);
        $setup->setPrintArea($printArea);
        $setup->setHorizontalCentered(true);
        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
    }

    private function logo(Worksheet $sheet, string $cell, int $height): void
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return;
        }
        $drawing = new Drawing;
        $drawing->setName('logo')->setPath($path)->setHeight($height)->setCoordinates($cell)->setWorksheet($sheet);
    }
}
