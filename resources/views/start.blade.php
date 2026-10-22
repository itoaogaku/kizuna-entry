@extends('layouts.app')

@section('content')
    <h1>エントリー受付</h1>
    <section class="entry-group">
        <h2><span class="group-tag">団体</span>団体でお申し込み</h2>
        <p class="muted">学校・クラブ・実業団など、複数の選手をまとめて申し込む場合です。2つの方法から選べます。</p>
        <div class="method-grid">
            <div class="method">
                <span class="tag">方法1・おすすめ（何度も参加する団体）</span>
                <h3>Webでエントリー</h3>
                <ul>
                    <li>最初に一度、団体と所属選手を登録します</li>
                    <li>次回からは選手にチェックを入れ、種目と申請タイムを入れるだけ</li>
                    <li>「前回と同じ内容で申し込む」ボタンもあります</li>
                </ul>
                <div class="actions" style="margin-top:4px">
                    @if ($user && $user->isTeam() && ! $user->is_admin)
                        <a class="btn" href="{{ route('entry.web') }}">Webエントリーへ進む</a>
                        <span class="muted">ログイン中：{{ $user->displayName() }}</span>
                    @else
                        <a class="btn" href="{{ route('team.login') }}">ログインして申し込む</a>
                        <a class="btn secondary" href="{{ route('team.register') }}">団体の新規登録</a>
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
    </section>

    <section class="entry-group">
        <h2><span class="group-tag individual">個人</span>個人でお申し込み</h2>
        <div class="method">
            <h3>フォームに入力して申し込む</h3>
            <ul>
                <li>選手1人で申し込む場合です（アカウント登録・ログインは不要）</li>
                <li>氏名・種目・申請タイム・連絡先を入力し、確認画面からそのままお支払いへ進みます</li>
            </ul>
            <div class="actions" style="margin-top:4px">
                <a class="btn" href="{{ route('individual-entry.create') }}">個人で申し込む</a>
            </div>
        </div>
    </section>
    <p class="muted">お支払いはクレジットカードまたはコンビニ払いです。参加料は申込締切日までにお支払いください（コンビニ払いはできる限りお申し込みの当日中に）。</p>

    @if ($meets->isNotEmpty())
        <h2>受付中・受付予定の大会</h2>
        @include('partials.meet-list', ['meets' => $meets])
    @endif
@endsection
