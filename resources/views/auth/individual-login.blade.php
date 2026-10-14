@extends('layouts.app')
@section('title', '個人申し込み')

@section('content')
    <h1>個人申し込み</h1>
    <div class="card">
        <p class="muted">登録済みの方は、メールアドレスとパスワードでログインしてください。</p>
        <form method="POST" action="{{ route('individual.login') }}">
            @csrf
            <div class="field">
                <label for="email">メールアドレス</label>
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
                <button class="btn" type="submit">ログイン</button>
                <a href="{{ route('password.request') }}">パスワードを忘れた方</a>
            </div>
        </form>
    </div>
    <div class="card">
        <p><strong>はじめての方</strong>は、選手本人の情報を登録してください。</p>
        <a class="btn secondary" href="{{ route('individual.register') }}">個人の新規登録</a>
    </div>
    <p><a href="{{ route('home') }}">← 申し込み方法の選択に戻る</a></p>
@endsection
