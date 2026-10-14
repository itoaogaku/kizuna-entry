@extends('layouts.app')
@section('title', 'パスワードの再設定')

@section('content')
    <h1>新しいパスワードの設定</h1>
    <div class="card">
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">メールアドレス</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
            </div>
            <div class="field">
                <label for="password">新しいパスワード <span class="hint">（8文字以上）</span></label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password_confirmation">新しいパスワード（確認）</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn" type="submit">設定する</button>
        </form>
    </div>
@endsection
