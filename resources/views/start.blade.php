@extends('layouts.app')

@section('content')
    <h1>エントリー受付</h1>
    <p>申し込みの方法を選んでください。</p>

    <div class="choice-grid">
        <a class="choice" href="{{ route('team.login') }}">
            <span class="choice-title">団体申し込み</span>
            <span class="choice-text">学校・クラブなどの代表者が、所属選手をまとめて申し込みます。<br>登録済みの団体は、団体名とパスワードで申込データを開けます。</span>
            <span class="btn">団体申し込みへ</span>
        </a>
        <a class="choice" href="{{ route('individual.login') }}">
            <span class="choice-title">個人申し込み</span>
            <span class="choice-text">団体に属さず、選手本人（または保護者）が申し込みます。<br>登録済みの方は、メールアドレスとパスワードでログインします。</span>
            <span class="btn">個人申し込みへ</span>
        </a>
    </div>

    <p class="muted">はじめての方：
        <a href="{{ route('team.register') }}">団体の新規登録</a>／
        <a href="{{ route('individual.register') }}">個人の新規登録</a>
    </p>

    @if ($meets->isNotEmpty())
        <h2>受付中・受付予定の大会</h2>
        @include('partials.meet-list', ['meets' => $meets])
    @endif
@endsection
