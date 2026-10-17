<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meet;
use App\Services\ProgramWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 大会資料（PDF）：大会要項・タイムテーブル・組編成・大会プログラム・リザルトを貼る。
 * 運用：エントリーデータをExcelで出力 → 微調整 → PDFを作って貼る。リザルトは写真判定機のPDFを貼る。
 */
class DocumentController extends Controller
{
    public function edit(Meet $meet): View
    {
        return view('admin.documents.edit', ['meet' => $meet]);
    }

    public function update(Request $request, Meet $meet): RedirectResponse
    {
        $rules = [
            'photo_url' => ['nullable', 'url', 'max:500'],
            'press_url' => ['nullable', 'url', 'max:500'],
        ];
        $names = ['photo_url' => '写真館のURL', 'press_url' => '取材・撮影のお申込みのURL'];
        foreach (Meet::DOCUMENTS as $key => $label) {
            $rules[$key.'_file'] = ['nullable', 'file', 'mimes:pdf', 'max:40960'];
            $rules[$key.'_url'] = ['nullable', 'url', 'max:500'];
            $rules[$key.'_note'] = ['nullable', 'string', 'max:30'];
            $names += [$key.'_file' => $label.'のPDF', $key.'_url' => $label.'のURL', $key.'_note' => $label.'のメモ'];
        }
        $data = $request->validate($rules, [], $names);

        $changed = [];
        foreach (Meet::DOCUMENTS as $key => $label) {
            unset($data[$key.'_file']);
            $old = $meet->{$key.'_path'};
            if ($request->hasFile($key.'_file')) {
                // 資料名と日時を付けた名前で保存する（差し替えのたびに別名になるので、古いPDFがキャッシュに残らない）
                $file = $request->file($key.'_file');
                $data[$key.'_path'] = $file->storeAs('meets/'.$meet->id, $key.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.pdf', 'public');
                $changed[] = $label;
            } elseif ($request->boolean($key.'_remove')) {
                $data[$key.'_path'] = null;
                $changed[] = $label.'（削除）';
            }
            if ($old && array_key_exists($key.'_path', $data) && $data[$key.'_path'] !== $old) {
                Storage::disk('public')->delete($old);
            }
        }
        $meet->update($data);

        return redirect()->route('admin.documents.edit', $meet)
            ->with('status', $changed ? implode('・', $changed).'を更新しました。大会開催ページに反映されています。' : '保存しました。');
    }

    /**
     * プログラム用Excel（競技日程＋組分け案つきのスタートリスト）。
     */
    public function programWorkbook(Meet $meet, ProgramWorkbook $workbook): StreamedResponse
    {
        $book = $workbook->build($meet);

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'),
            $meet->held_on->format('Ymd').'_プログラム用.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
