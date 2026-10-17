<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\Meet;
use App\Services\EntryExport;
use App\Services\ReceiptPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
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

        return view('admin.entries.index', [
            'meet' => $meet,
            'entries' => $entries,
            'status' => $status,
            'statusOptions' => EntryExport::statusOptions(),
        ]);
    }

    /**
     * エントリーシートと同じ列のExcelファイルをダウンロードする。
     */
    public function export(Request $request, Meet $meet, EntryExport $export): StreamedResponse
    {
        $status = $request->query('status', Entry::STATUS_PAID);
        Log::info('admin export', ['user' => $request->user()->id, 'meet' => $meet->id]);

        $book = $export->build($meet, $status);
        $filename = $meet->held_on->format('Ymd').'_エントリー一覧.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * エクセル添付で申し込まれた元のファイルをダウンロードする。
     */
    public function sheet(Entry $entry): StreamedResponse
    {
        abort_unless($entry->sheet_path && Storage::exists($entry->sheet_path), 404);

        return Storage::download($entry->sheet_path, '受付'.$entry->id.'_'.$entry->user->displayName().'.'.pathinfo($entry->sheet_path, PATHINFO_EXTENSION));
    }

    /**
     * 領収書（PDF）のダウンロード。入金済みのエントリーだけ。
     */
    public function receipt(Request $request, Entry $entry): Response
    {
        abort_unless($entry->isPaid() && $entry->receipt_number, 404);

        return response(app(ReceiptPdf::class)->render($entry), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $entry->receiptFilename(), 'receipt-'.$entry->receipt_number.'.pdf'),
        ]);
    }

    /**
     * 領収書の宛名を直す（申込者から依頼があったときなど）。番号は変えずに作り直す。
     */
    public function updateReceipt(Request $request, Entry $entry): RedirectResponse
    {
        $data = $request->validate(['receipt_name' => ['nullable', 'string', 'max:100']], [], ['receipt_name' => '領収書の宛名']);
        $entry->update(['receipt_name' => $data['receipt_name'] ?: null]);

        return back()->with('status', '受付番号'.$entry->id.'の領収書の宛名を「'.$entry->receiptAddressee().'」にしました。');
    }
}
