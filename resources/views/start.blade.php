@extends('layouts.app')

@section('content')
    <h1>エントリー受付</h1>
    @guest
        @include('auth._separate-account')
    @endguest
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
                @if ($user && ! $user->is_admin)
                    <a class="btn" href="{{ route('entry.web') }}">Webエントリーへ進む</a>
                    <span class="muted">ログイン中：{{ $user->displayName() }}（{{ $user->typeLabel() }}）</span>
                @else
                    <a class="btn" href="{{ route('team.login') }}">団体申し込み</a>
                    <a class="btn" href="{{ route('individual.login') }}">個人申し込み</a>
                @endif
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
            </div>
        </div>
    </div>
    <p class="muted">お支払いはクレジットカードまたはコンビニ払いです。参加料は申込締切日までにお支払いください（コンビニ払いはできる限りお申し込みの当日中に）。</p>

    @if ($meets->isNotEmpty())
        <h2>受付中・受付予定の大会</h2>
        @include('partials.meet-list', ['meets' => $meets])
    @endif
@endsection
