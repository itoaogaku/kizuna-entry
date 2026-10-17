<?php

namespace App\Services;

use App\Models\Entry;
use TCPDF;

/**
 * 領収書のPDF（A4縦の上半分に1枚）。入金が完了したエントリーについて作る。
 */
class ReceiptPdf
{
    public function render(Entry $entry): string
    {
        $entry->loadMissing('meet', 'user', 'items.athlete', 'items.event');
        $receipt = config('kizuna.receipt');

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor($receipt['issuer']);
        $pdf->SetTitle('領収書 '.$entry->receipt_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(18, 16, 18);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddFont('notosansjp', '', resource_path('fonts/tcpdf/notosansjp.php'), true);
        $pdf->AddFont('notosansjp', 'B', resource_path('fonts/tcpdf/notosansjpb.php'), true);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage();

        $teal = [51, 112, 113];

        // 見出し
        $pdf->SetFont('notosansjp', 'B', 22);
        $pdf->SetTextColor(...$teal);
        $pdf->Cell(0, 12, '領　収　書', 0, 1, 'C');
        $pdf->SetTextColor(30, 40, 45);
        $pdf->SetFont('notosansjp', '', 9);
        $pdf->Cell(0, 5, 'No. '.$entry->receipt_number, 0, 1, 'R');
        $pdf->Cell(0, 5, '発行日 '.$entry->paid_at->format('Y年n月j日'), 0, 1, 'R');
        $pdf->Ln(4);

        // 宛名
        $pdf->SetFont('notosansjp', 'B', 15);
        $pdf->Cell(120, 10, $entry->receiptAddressee().'　様', 'B', 1, 'L');
        $pdf->Ln(6);

        // 金額
        $pdf->SetFillColor(227, 242, 242);
        $pdf->SetFont('notosansjp', 'B', 20);
        $pdf->Cell(0, 16, '金額　¥'.number_format($entry->total_amount).'－', 0, 1, 'C', true);
        $pdf->Ln(3);
        $pdf->SetFont('notosansjp', '', 10.5);
        $count = $entry->items->count();
        $people = $entry->items->pluck('athlete_id')->unique()->count();
        $pdf->Cell(0, 7, '但し　'.$entry->meet->name.'（'.$entry->meet->held_on->format('Y年n月j日').'）参加料として（'.$people.'名・'.$count.'種目）', 0, 1, 'L');
        $pdf->Cell(0, 7, '上記正に領収いたしました。', 0, 1, 'L');
        if ($receipt['registration_number']) {
            $tax = (int) floor($entry->total_amount * $receipt['tax_rate'] / (100 + $receipt['tax_rate']));
            $pdf->SetFont('notosansjp', '', 9);
            $pdf->Cell(0, 6, '（内訳　'.$receipt['tax_rate'].'%対象 ¥'.number_format($entry->total_amount).'（税込）　うち消費税等 ¥'.number_format($tax).'）', 0, 1, 'L');
        }
        $pdf->Ln(4);

        // 内訳
        $pdf->SetFont('notosansjp', 'B', 9);
        $pdf->SetFillColor(...$teal);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(70, 7, '氏名', 1, 0, 'L', true);
        $pdf->Cell(60, 7, '種目', 1, 0, 'L', true);
        $pdf->Cell(44, 7, '金額', 1, 1, 'R', true);
        $pdf->SetTextColor(30, 40, 45);
        $pdf->SetFont('notosansjp', '', 9);
        foreach ($entry->items as $item) {
            $pdf->Cell(70, 6.5, $item->athlete->fullName(), 1, 0, 'L');
            $pdf->Cell(60, 6.5, $item->event->name, 1, 0, 'L');
            $pdf->Cell(44, 6.5, '¥'.number_format($item->fee), 1, 1, 'R');
        }
        if ($entry->payment_fee > 0) {
            $pdf->Cell(130, 6.5, 'コンビニ払い手数料', 1, 0, 'L');
            $pdf->Cell(44, 6.5, '¥'.number_format($entry->payment_fee), 1, 1, 'R');
        }
        $pdf->SetFont('notosansjp', 'B', 9);
        $pdf->Cell(130, 7, '合計', 1, 0, 'R');
        $pdf->Cell(44, 7, '¥'.number_format($entry->total_amount), 1, 1, 'R');
        $pdf->Ln(3);
        $pdf->SetFont('notosansjp', '', 9);
        $pdf->Cell(0, 6, 'お支払い方法：'.$entry->methodLabel().'（受付番号 '.$entry->id.'・入金日 '.$entry->paid_at->format('Y/m/d').'）', 0, 1, 'L');
        $pdf->Ln(6);

        // 発行者
        $x = 110;
        $pdf->SetX($x);
        $pdf->SetFont('notosansjp', 'B', 10.5);
        $pdf->Cell(0, 6, $receipt['issuer'], 0, 1, 'L');
        $pdf->SetFont('notosansjp', '', 9);
        foreach (array_filter([
            $receipt['registration_number'] ? '登録番号：'.$receipt['registration_number'] : null,
            $receipt['address'],
            $receipt['phone'],
        ]) as $line) {
            $pdf->SetX($x);
            $pdf->Cell(0, 5, $line, 0, 1, 'L');
        }

        return $pdf->Output('', 'S');
    }
}
