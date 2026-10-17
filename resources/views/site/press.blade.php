@extends('layouts.app')
@section('title', $meet->name.' 取材・撮影のお申込み')

@section('content')
    <p class="section-eyebrow">PRESS</p>
    <h1>取材・撮影のお申込み</h1>
    <p>{{ $meet->name }}（{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $meet->venue ? '・'.$meet->venue : '' }}）</p>
    <div class="card">
        <form method="POST" action="{{ route('press.store', $meet) }}">
            @csrf
            <div class="row">
                <div class="field">
                    <label for="media_name">媒体名・会社名</label>
                    <input id="media_name" type="text" name="media_name" value="{{ old('media_name') }}" required>
                </div>
                <div class="field">
                    <label for="contact_name">担当者氏名</label>
                    <input id="contact_name" type="text" name="contact_name" value="{{ old('contact_name') }}" required autocomplete="name">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="email">メールアドレス</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </div>
                <div class="field">
                    <label for="phone">当日連絡のつく電話番号</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label>取材の内容</label>
                    @foreach (\App\Models\PressRequest::COVERAGES as $coverage)
                        <label class="inline"><input type="radio" name="coverage" value="{{ $coverage }}" @checked(old('coverage', '取材・撮影') === $coverage)> {{ $coverage }}</label>
                    @endforeach
                </div>
                <div class="field">
                    <label for="people">来場人数</label>
                    <input id="people" type="number" name="people" value="{{ old('people', 1) }}" min="1" max="30" required style="max-width:120px">
                </div>
            </div>
            <div class="field">
                <label for="purpose">掲載・使用予定 <span class="hint">（媒体・掲載時期など）</span></label>
                <textarea id="purpose" name="purpose">{{ old('purpose') }}</textarea>
            </div>
            <div class="field">
                <label for="notes">備考</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="field">
                <label class="inline"><input type="checkbox" name="agree" value="1" @checked(old('agree'))> 競技の妨げにならないよう、運営の指示に従って取材・撮影します</label>
            </div>
            <button class="btn" type="submit">申し込む</button>
        </form>
    </div>
@endsection
