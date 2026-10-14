@extends('layouts.app')
@section('title', 'チーム登録')

@section('content')
    <h1>チーム登録</h1>
    <div class="card">
        <p class="muted">チームの代表者（エントリーと支払いを行う方）の情報を登録してください。入金完了メールはこのメールアドレスに届きます。</p>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="row">
                <div class="field">
                    <label for="team_name">所属（チーム名）</label>
                    <input id="team_name" type="text" name="team_name" value="{{ old('team_name') }}" required>
                </div>
                <div class="field">
                    <label for="team_kana">所属のフリガナ <span class="hint">（任意）</span></label>
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
                <label for="email">メールアドレス</label>
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
            <div class="field">
                <label class="inline"><input type="checkbox" name="agree" value="1" @checked(old('agree'))> 個人情報の取り扱いに同意します</label>
                <p class="hint">登録された選手情報は、大会の運営（番組編成・記録の発表など）にのみ使用します。</p>
            </div>
            <button class="btn" type="submit">登録する</button>
        </form>
    </div>
@endsection
