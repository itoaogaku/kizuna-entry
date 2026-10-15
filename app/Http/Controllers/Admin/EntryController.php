<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\Meet;
use App\Services\EntryExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
}
