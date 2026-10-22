@extends('layouts.app')
@section('title', '選手のまとめて登録')

@section('content')
    <h1>エントリーシート（Excel）で選手をまとめて登録</h1>
    <div class="card quick">
        <div>
            <strong>選手登録用エクセルシート</strong><br>
            <span class="muted">学年・性別・所属陸協・種別は一覧から選べます。入力したら、下の「登録する」からそのままアップロードしてください。</span>
        </div>
        <a class="btn" href="{{ route('athletes.template-xlsx') }}">選手登録用エクセルシートをダウンロード</a>
    </div>
    <div class="card">
        <p><strong>上の選手登録用エクセルシート、またはこれまで提出していたエントリーシート（.xlsx）をアップロードできます。</strong><br>
            姓・名・フリガナ・学年・性別・所属陸協・種別 を読み取って選手を登録します。</p>
        <ul class="muted">
            <li>同じ選手が複数の種目で何行かあっても、1人として登録します。</li>
            <li>すでに登録済みの選手（同じ姓名）は飛ばします。</li>
            <li>種目・申請タイムなどは大会ごとのエントリー画面で入力するので、ここでは読み取りません。</li>
            <li>エラーの行がある場合は、1人も登録されません。表示された行を直して、もう一度アップロードしてください。</li>
        </ul>
        <form method="POST" action="{{ route('athletes.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="file">選手登録用エクセルシート・エントリーシート（.xlsx）またはCSV</label>
                <input id="file" type="file" name="file" accept=".xlsx,.xls,.csv" required>
            </div>
            <div class="actions">
                <button class="btn" type="submit">登録する</button>
                <a href="{{ route('athletes.index') }}">戻る</a>
            </div>
        </form>
    </div>
    <p class="muted">CSVで作る場合は、<a href="{{ route('athletes.template') }}">ひな形（CSV）</a>に入力してアップロードすることもできます（列：{{ implode('・', $header) }}）。</p>
@endsection
