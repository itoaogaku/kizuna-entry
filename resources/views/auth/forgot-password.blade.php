@extends('layouts.app')
@section('title', 'パスワードの再設定')

@section('content')
    <h1>パスワードの再設定</h1>
    <div class="card">
        <p class="muted">登録したメールアドレスに、パスワード再設定用のリンクを送ります。</p>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">メールアドレス</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <button class="btn" type="submit">送信する</button>
        </form>
    </div>
@endsection
