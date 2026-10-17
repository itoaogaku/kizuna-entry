@extends('layouts.app')

@section('content')
    <h1>エントリー受付</h1>
    <p>エントリー方法は <strong>2通り</strong> あります。どちらでも同じように受け付けます。</p>

    <div class="method-grid">
        <div class="method">
            <span class="tag">方法1・おすすめ（何度も参加する方）</span>
            <h3>Webでエントリー</h3>
            <ul>
                <li>最初に一度、団体（または個人）と選手を登録します</li>
                <li>次回からは選手にチェックを入れ、種目と申請タイムを入れるだけ</li>
                <li>「前回と同じ内容で申し込む」ボタンもあります</li>
            </ul>
            <div class="actions" style="margin-top:4px">
                <a class="btn" href="{{ route('team.login') }}">団体申し込み</a>
                <a class="btn" href="{{ route('individual.login') }}">個人申し込み</a>
            </div>
        </div>
        <div class="method">
            <span class="tag">方法2（今までどおりの方法）</span>
            <h3>エクセル（エントリーシート）を添付</h3>
            <ul>
                <li>今までのエントリーシートに記入して添付するだけ</li>
                <li>アカウント登録・ログインは不要です</li>
                <li>読み込んだ内容を確認して、そのままお支払いへ進みます</li>
            </ul>
            <div class="actions" style="margin-top:4px">
                <a class="btn" href="{{ route('sheet.create') }}">エクセルを添付して申し込む</a>
                <a class="btn secondary" href="{{ asset('downloads/kizuna-entry-sheet.xlsx') }}" download>シートをダウンロード</a>
            </div>
        </div>
    </div>

    <p class="muted">Webエントリーがはじめての方：
        <a href="{{ route('team.register') }}">団体の新規登録</a>／
        <a href="{{ route('individual.register') }}">個人の新規登録</a>
    </p>
    <p class="muted">お支払いはクレジットカードまたはコンビニ払いです。コンビニ払いの場合は、できる限りお申し込みの当日中にお支払いください。</p>

    @if ($meets->isNotEmpty())
        <h2>受付中・受付予定の大会</h2>
        @include('partials.meet-list', ['meets' => $meets])
    @endif
@endsection
