@extends('layouts.app')
@section('title', '団体の新規登録')

@section('content')
    <h1>団体の新規登録</h1>
    <div class="card">
        <p class="muted">次回からは、ここで登録する<strong>団体名とパスワード</strong>で申込データを開きます。入金完了メールは代表者のメールアドレスに届きます。</p>
        <form method="POST" action="{{ route('team.register') }}">
            @csrf
            <div class="row">
                <div class="field">
                    <label for="team_name">団体名 <span class="hint">（ログインに使います）</span></label>
                    <input id="team_name" type="text" name="team_name" value="{{ old('team_name') }}" placeholder="例：絆陸上クラブ" required>
                </div>
                <div class="field">
                    <label for="team_kana">団体名のフリガナ <span class="hint">（任意）</span></label>
                    <input id="team_kana" type="text" name="team_kana" value="{{ old('team_kana') }}">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="name">代表者氏名</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                </div>
                <div class="field">
                    <label for="phone">電話番号</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel">
                </div>
            </div>
            <div class="field">
                <label for="email">代表者のメールアドレス <span class="hint">（入金完了メール・パスワード再設定に使います）</span></label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            </div>
            <div class="row">
                <div class="field">
                    <label for="password">パスワード <span class="hint">（8文字以上）</span></label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirmation">パスワード（確認）</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
            @include('partials.agree')
            <button class="btn" type="submit">団体を登録する</button>
        </form>
    </div>
    <p><a href="{{ route('team.login') }}">← 登録済みの団体はこちら</a></p>
@endsection
