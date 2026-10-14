@extends('layouts.app')
@section('title', 'ログイン')

@section('content')
    <h1>ログイン</h1>
    <div class="card">
        <form method="POST" action="{{ route('login') }}">
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
    <p>はじめての方は <a href="{{ route('register') }}">チーム登録</a> へ。</p>
@endsection
