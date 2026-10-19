@extends('layouts.admin')
@section('title', $meet->name.' 大会資料（PDF）')

@section('content')
    <h1>{{ $meet->name }} 大会資料（PDF）</h1>

    <div class="card workflow">
        <strong>タイムテーブル・組編成・大会プログラムの作り方</strong>
        <ol>
            <li><a href="{{ route('admin.documents.program', $meet) }}"><strong>プログラム用Excel</strong></a>を出力する（入金済みの選手で組分け案を作った、大会プログラムと同じ形のスタートリストと競技日程）
                <br><span class="muted">エントリーシートと同じ列の一覧が必要なときは <a href="{{ route('admin.entries.export', ['meet' => $meet, 'status' => 'paid']) }}">エントリーデータをExcelで出力</a></span></li>
            <li>Excelで組・ORD・競技時刻を微調整する（競技時刻を入れると招集時刻は自動で入ります。右側の灰色の列は調整用で印刷されません）</li>
            <li>PDFに保存して、下の欄に貼る</li>
        </ol>
        <p class="muted">リザルトは、写真判定機から出力したPDFをそのまま貼ってください。PDFを貼ると、すぐに大会開催ページにボタンが表示されます。</p>
    </div>

    <form method="POST" action="{{ route('admin.documents.update', $meet) }}" enctype="multipart/form-data">
        @csrf
        @foreach (\App\Models\Meet::DOCUMENTS as $key => $label)
            <div class="card doc-card">
                <div class="doc-card-head">
                    <h2>{{ $label }}</h2>
                    @if ($url = $meet->documentUrl($key))
                        <a class="badge paid" href="{{ $url }}" target="_blank" rel="noopener">公開中：表示する</a>
                    @else
                        <span class="badge">未登録</span>
                    @endif
                </div>
                <div class="row">
                    <div class="field">
                        <label for="{{ $key }}_file">PDFを貼る{{ $meet->{$key.'_path'} ? '（差し替え）' : '' }}</label>
                        <input id="{{ $key }}_file" type="file" name="{{ $key }}_file" accept="application/pdf,.pdf">
                        @if ($meet->{$key.'_path'})
                            <label class="inline hint"><input type="checkbox" name="{{ $key }}_remove" value="1"> 貼ってあるPDFを削除する</label>
                        @endif
                    </div>
                    <div class="field">
                        <label for="{{ $key }}_note">ボタンに添えるメモ <span class="hint">（例：暫定版、0628更新）</span></label>
                        <input id="{{ $key }}_note" type="text" name="{{ $key }}_note" value="{{ old($key.'_note', $meet->documentNote($key)) }}" maxlength="30">
                    </div>
                </div>
                <details @if ($meet->{$key.'_url'}) open @endif>
                    <summary class="hint">PDFの代わりにURLを使う</summary>
                    <input type="url" name="{{ $key }}_url" value="{{ old($key.'_url', $meet->{$key.'_url'}) }}" placeholder="https://" aria-label="{{ $label }}のURL">
                    <p class="hint">PDFとURLの両方がある場合はPDFを表示します。</p>
                </details>
            </div>
        @endforeach

        <div class="card">
            <h2 style="margin-top:0">リンク</h2>
            <div class="row">
                <div class="field">
                    <label for="photo_url">写真館（URL）</label>
                    <input id="photo_url" type="url" name="photo_url" value="{{ old('photo_url', $meet->photo_url) }}" placeholder="https://">
                </div>
                <div class="field">
                    <label for="press_url">取材・撮影のお申込み（外部フォームのURL）</label>
                    <input id="press_url" type="url" name="press_url" value="{{ old('press_url', $meet->press_url) }}" placeholder="空欄ならこのシステムのフォームで受け付けます">
                </div>
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">保存する</button>
            <a href="{{ route('admin.meets.show', $meet) }}">大会の画面に戻る</a>
        </div>
    </form>
@endsection
