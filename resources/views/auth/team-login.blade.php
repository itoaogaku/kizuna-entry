@extends('layouts.app')
@section('title', '団体申し込み')

@section('content')
    <p class="section-eyebrow">大会エントリー用ログイン</p>
    <h1>団体申し込み</h1>
    @include('auth._separate-account')
    <div class="card">
        <p class="muted">団体登録のときの代表者メールアドレスとパスワードを入力すると、団体の申込データ（選手・エントリー）が開きます。</p>
        <form method="POST" action="{{ route('team.login') }}">
            @csrf
            <div class="field">
                <label for="email">メールアドレス <span class="hint">（団体登録のときの代表者メールアドレス）</span></label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>
            <div class="field">
                <label for="password">パスワード</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label class="inline"><input type="checkbox" name="remember" value="1"> ログインしたままにする</label>
            </div>
            <div class="actions">
                <button class="btn" type="submit">申込データを開く</button>
                <a href="{{ route('password.request') }}">パスワードを忘れた方</a>
            </div>
        </form>
    </div>
    <div class="card">
        <p><strong>はじめて申し込む団体</strong>は、先に団体登録をしてください。所属選手を一度登録すれば、次回からは出場する選手を選ぶだけで申し込めます。</p>
        <a class="btn secondary" href="{{ route('team.register') }}">団体の新規登録</a>
    </div>
    <p><a href="{{ route('home') }}">← 申し込み方法の選択に戻る</a></p>
@endsection
