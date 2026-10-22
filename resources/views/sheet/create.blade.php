@extends('layouts.app')
@section('title', 'エクセル添付でエントリー')

@section('content')
    <h1>エクセル（エントリーシート）を添付してエントリー</h1>
    <div class="card">
        <ol class="steps">
            <li><a href="{{ asset('downloads/kizuna-entry-sheet.xlsx') }}" download>エントリーシート（.xlsx）</a>をダウンロードし、「申込み」シートに記入します。</li>
            <li>下のフォームで大会を選び、記入したシートを添付します。</li>
            <li>読み込んだ内容を確認して、お支払い（カードまたはコンビニ）に進みます。入金を確認した時点でエントリー完了です。</li>
        </ol>
        <p class="muted">シートの「申込み種目」は大会の種目名（例：5000m）、「申請タイム」は 14'30 の形で書いてください。アカウント登録やログインは不要です。</p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('sheet.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="meet_id">大会</label>
                <select id="meet_id" name="meet_id" required>
                    @forelse ($meets as $meet)
                        <option value="{{ $meet->id }}" @selected((string) old('meet_id', $old['meet_id'] ?? '') === (string) $meet->id)>{{ $meet->name }}（{{ $meet->held_on->isoFormat('M月D日（ddd）') }}・締切 {{ $meet->entry_closes_at->format('n/j H:i') }}）</option>
                    @empty
                        <option value="">受付中の大会はありません</option>
                    @endforelse
                </select>
            </div>
            @if ($user)
                <p>申込者：<strong>{{ $user->displayName() }}</strong>（{{ $user->email }}）<span class="muted">としてお申し込みします。</span></p>
            @else
                <div class="row">
                    <div class="field">
                        <label for="team_name">団体名 <span class="hint">（個人の場合は空欄。空欄ならシートの団体名を使います）</span></label>
                        <input id="team_name" type="text" name="team_name" value="{{ old('team_name', $old['team_name'] ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="name">申込責任者の氏名</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $old['name'] ?? '') }}" required autocomplete="name">
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="email">メールアドレス <span class="hint">（受付・入金確認のメールが届きます）</span></label>
                        <input id="email" type="email" name="email" value="{{ old('email', $old['email'] ?? '') }}" required autocomplete="email">
                    </div>
                    <div class="field">
                        <label for="phone">電話番号</label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone', $old['phone'] ?? '') }}" required autocomplete="tel">
                    </div>
                </div>
            @endif
            <div class="field">
                <label for="file">記入したエントリーシート（.xlsx）</label>
                <input id="file" type="file" name="file" accept=".xlsx,.xls" required>
            </div>
            @unless ($user)
                @include('partials.agree')
            @endunless
            <button class="btn" type="submit" @disabled($meets->isEmpty())>読み込んで内容を確認する</button>
        </form>
    </div>
    <p class="muted">何度も参加される場合は、<a href="{{ route('home') }}">Webエントリー</a>に登録すると、次回から選手を選ぶだけで申し込めます。</p>
@endsection
