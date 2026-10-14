@extends('layouts.app')

@section('content')
    <h1>エントリー受付中の大会</h1>

    @guest
        <div class="card">
            <p>エントリーには、チームの代表者アカウントが必要です。最初に一度チームと選手を登録すれば、次回からは出場する選手をチェックするだけで申し込めます。</p>
            <div class="actions">
                <a class="btn" href="{{ route('register') }}">チームを登録する</a>
                <a class="btn secondary" href="{{ route('login') }}">ログイン</a>
            </div>
        </div>
    @endguest

    @auth
        @if ($user->athletes()->doesntExist())
            <div class="flash warn">まだ選手が登録されていません。<a href="{{ route('athletes.index') }}">選手登録</a>から登録してください。</div>
        @endif
    @endauth

    <div class="meet-list">
        @forelse ($meets as $meet)
            <div class="card">
                <h2 style="margin-top:0">{{ $meet->name }}</h2>
                <dl class="kv">
                    <dt>開催日</dt><dd>{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}</dd>
                    @if ($meet->venue)<dt>会場</dt><dd>{{ $meet->venue }}</dd>@endif
                    <dt>受付期間</dt><dd>{{ $meet->entry_opens_at->format('Y/m/d H:i') }} 〜 {{ $meet->entry_closes_at->format('Y/m/d H:i') }}</dd>
                </dl>
                @if ($meet->notes)
                    <p class="muted" style="white-space:pre-line">{{ $meet->notes }}</p>
                @endif
                <div class="actions">
                    @if ($meet->isAcceptingEntries())
                        <a class="btn" href="{{ route('entries.create', $meet) }}">エントリーする</a>
                    @elseif (now()->lt($meet->entry_opens_at))
                        <span class="badge">受付開始前</span>
                    @else
                        <span class="badge">受付終了</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">現在、受付中・受付予定の大会はありません。</p>
        @endforelse
    </div>
@endsection
