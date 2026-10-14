@extends('layouts.app')
@section('title', '選手のまとめて登録')

@section('content')
    <h1>Excel（CSV）で選手をまとめて登録</h1>
    <div class="card">
        <ol>
            <li><a href="{{ route('athletes.template') }}">ひな形（CSV）</a>をダウンロードし、Excelで開きます。</li>
            <li>1行目の見出しは残したまま、2行目から選手を1人1行で入力します。<br>
                <span class="muted">列：{{ implode('・', $header) }}（性別は「男」「女」、生年月日は「2008/04/01」の形）</span></li>
            <li>「CSV UTF-8（コンマ区切り）」または「CSV（コンマ区切り）」で保存し、下からアップロードします。</li>
        </ol>
        <p class="muted">すでに登録済みの選手を重ねて登録すると二重になるので、新しい選手だけを入れてください。エラーがある場合は1人も登録されません。</p>
        <form method="POST" action="{{ route('athletes.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="csv">CSVファイル</label>
                <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
            </div>
            <div class="actions">
                <button class="btn" type="submit">登録する</button>
                <a href="{{ route('athletes.index') }}">戻る</a>
            </div>
        </form>
    </div>
@endsection
