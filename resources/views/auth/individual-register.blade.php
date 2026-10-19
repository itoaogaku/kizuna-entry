@extends('layouts.app')
@section('title', '個人の新規登録')

@section('content')
    <p class="section-eyebrow">大会エントリー用アカウント</p>
    <h1>個人の新規登録</h1>
    @include('auth._separate-account')
    <div class="card">
        <p class="muted">出場する選手本人の情報を登録してください。未成年の場合は、保護者の連絡先でも構いません。</p>
        <form method="POST" action="{{ route('individual.register') }}">
            @csrf
            @include('athletes.fields', ['athlete' => new \App\Models\Athlete(['gender' => 'M'])])
            <div class="row">
                <div class="field">
                    <label for="team_name">所属 <span class="hint">（任意。学校名・クラブ名など）</span></label>
                    <input id="team_name" type="text" name="team_name" value="{{ old('team_name') }}">
                </div>
                <div class="field">
                    <label for="phone">電話番号</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel">
                </div>
            </div>
            <div class="field">
                <label for="email">メールアドレス <span class="hint">（ログインと入金完了メールに使います）</span></label>
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
            <button class="btn" type="submit">登録する</button>
        </form>
    </div>
    <p><a href="{{ route('individual.login') }}">← 登録済みの方はこちら</a></p>
@endsection
