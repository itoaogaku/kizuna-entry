@extends('layouts.app')
@section('title', '大会開催')

@section('hero')
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-copy">
                <p class="eyebrow">KIZUNA RECORD CHALLENGE</p>
                <h1>大会開催</h1>
                <p class="lead">アスリートの活動支援と活躍の場の提供のため、<br class="pc">記録挑戦会を開催しています。</p>
                <div class="hero-actions">
                    <a class="btn btn-light" href="#schedule">開催予定を見る</a>
                    <a class="btn btn-outline-light" href="{{ route('home') }}">エントリーはこちら</a>
                </div>
            </div>
            @if ($next)
                <a class="next-card" href="#meet-{{ $next->id }}">
                    <span class="next-label">NEXT RACE</span>
                    <span class="next-date">
                        <span class="num">{{ $next->held_on->format('n.j') }}</span>
                        <span class="dow">{{ $next->held_on->isoFormat('ddd') }}</span>
                    </span>
                    <span class="next-name">{{ $next->name }}</span>
                    @php $days = (int) now()->startOfDay()->diffInDays($next->held_on, false); @endphp
                    <span class="next-meta">
                        <span class="stage stage-{{ $next->stage() }}">{{ $next->stageLabel() }}</span>
                        @if ($days > 0)<span>開催まで あと<strong>{{ $days }}</strong>日</span>@elseif ($days === 0)<span><strong>本日開催</strong></span>@endif
                    </span>
                </a>
            @endif
        </div>
    </section>
@endsection

@section('content')
    <section class="about">
        <p class="section-eyebrow">ABOUT</p>
        <h2 class="section-title">挑戦する、すべてのアスリートへ。</h2>
        <p>アスリートキャリアセンターでは、アスリートの活動支援や活躍の場の提供のため、大会の開催や後援を行っています。
            <span class="pill">賞品の贈呈</span><span class="pill">認定記録証の発行</span>など、様々な取り組みを行っていきます。</p>
        <p>皆様のお申込み、心よりお待ちしております。</p>
    </section>

    <section id="schedule" class="schedule">
        <div class="section-head">
            <div>
                <p class="section-eyebrow">SCHEDULE</p>
                <h2 class="section-title">{{ $year }}年度 大会開催予定</h2>
            </div>
            @if ($years->count() > 1)
                <nav class="year-tabs" aria-label="年度">
                    @foreach ($years as $y)
                        <a href="{{ route('site.meets', ['year' => $y]) }}#schedule" @class(['active' => $y === $year])>{{ $y }}年度</a>
                    @endforeach
                </nav>
            @endif
        </div>
        <p class="muted">各大会の詳細から、大会要項・プログラム・リザルトをご覧いただけます。エントリーはWebまたはエントリーシート（エクセル）の添付で受け付けています。</p>

        @forelse ($meets as $meet)
            <a href="#meet-{{ $meet->id }}" @class(['race', 'is-finished' => $meet->stage() === 'finished'])>
                <span class="race-date">
                    <span class="m">{{ $meet->held_on->format('n') }}<small>月</small></span>
                    <span class="d">{{ $meet->held_on->format('j') }}</span>
                    <span class="w">{{ $meet->held_on->isoFormat('ddd') }}</span>
                </span>
                <span class="race-body">
                    <span class="race-head">
                        <span class="race-name">{{ $meet->name }}</span>
                        <span class="stage stage-{{ $meet->stage() }}">{{ $meet->stageLabel() }}</span>
                    </span>
                    @foreach ($meet->eventsByGender() as $gender => $events)
                        <span class="race-line">
                            <span class="tag tag-{{ $gender === 'M' ? 'm' : 'f' }}">{{ $meet->division }}・{{ $gender === 'M' ? '男子' : '女子' }}</span>
                            @if ($meet->eligible)<span class="eligible">{{ $meet->eligible }}</span>@endif
                            <span class="chips">@foreach ($events as $event)<span class="chip">{{ $event->name }}</span>@endforeach</span>
                        </span>
                    @endforeach
                    @if ($meet->venue)<span class="race-venue">会場：{{ $meet->venue }}</span>@endif
                </span>
            </a>
        @empty
            <div class="card"><p>{{ $year }}年度の大会は準備中です。</p></div>
        @endforelse
    </section>

    @if ($details->isNotEmpty())
        <section class="details">
            <p class="section-eyebrow">RACES</p>
            <h2 class="section-title">各大会のご案内</h2>
            @foreach ($details as $meet)
                <article id="meet-{{ $meet->id }}" class="meet-card">
                    <header>
                        <span class="meet-date">{{ $meet->held_on->isoFormat('YYYY/M/D（ddd）') }}</span>
                        <h3>{{ $meet->name }}</h3>
                        <span class="stage stage-{{ $meet->stage() }}">{{ $meet->stageLabel() }}</span>
                    </header>
                    <p class="division">{{ $meet->division }}</p>
                    <dl class="meet-facts">
                        @if ($meet->venue)<div><dt>会場</dt><dd>{{ $meet->venue }}</dd></div>@endif
                        @if ($meet->startTimeLabel())<div><dt>競技開始</dt><dd>{{ $meet->startTimeLabel() }} 予定</dd></div>@endif
                        @if ($meet->hasCategoryFees() && $meet->stage() !== 'finished')
                            <div><dt>参加料</dt><dd>@foreach (\App\Models\Meet::FEE_LABELS as $key => $label)@if ($meet->{$key} !== null)<span class="fee">{{ $label }} {{ number_format($meet->{$key}) }}円</span>@endif @endforeach</dd></div>
                        @endif
                    </dl>
                    @if ($meet->stage() === 'open' || $meet->stage() === 'upcoming')
                        <p class="muted entry-period">エントリー受付：{{ $meet->entry_opens_at->isoFormat('M/D（ddd）H:mm') }} 〜 {{ $meet->entry_closes_at->isoFormat('M/D（ddd）H:mm') }}（参加料も締切日までにお支払いください）</p>
                    @endif
                    <div class="link-grid">
                        @if ($meet->stage() === 'open')
                            <a class="link-btn entry" href="{{ route('home') }}">エントリーする<small>Web／エクセル添付</small></a>
                        @endif
                        @foreach (\App\Models\Meet::DOCUMENTS as $key => $label)
                            @if ($url = $meet->documentUrl($key))
                                <a class="link-btn" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}@if ($key === 'program' && $meet->program_note)<small>{{ $meet->program_note }}</small>@endif</a>
                            @elseif ($key === 'program' && $meet->isProgramFinal())
                                {{-- PDFがなければ、確定したタイムテーブルとスタートリストから作ったプログラムを出す --}}
                                <a class="link-btn" href="{{ route('site.program', $meet) }}">大会プログラム<small>{{ $meet->start_list_published_at?->format('md') }}更新</small></a>
                            @endif
                            @if ($key === 'outline' && $meet->hasPublicStartList())
                                <a class="link-btn" href="{{ route('site.timetable', $meet) }}">タイムテーブル@unless ($meet->isProgramFinal())<small>暫定版</small>@endunless</a>
                                <a class="link-btn" href="{{ route('site.startlist', $meet) }}">スタートリスト@unless ($meet->isProgramFinal())<small>暫定版</small>@endunless</a>
                            @endif
                        @endforeach
                        @if ($meet->photo_url)
                            <a class="link-btn photo" href="{{ $meet->photo_url }}" target="_blank" rel="noopener">写真館</a>
                        @endif
                        @if ($meet->press_url && $meet->stage() !== 'finished')
                            <a class="link-btn outline" href="{{ $meet->press_url }}" target="_blank" rel="noopener">取材・撮影のお申込み</a>
                        @elseif ($meet->acceptsPressRequests())
                            <a class="link-btn outline" href="{{ route('press.create', $meet) }}">取材・撮影のお申込み</a>
                        @endif
                    </div>
                    @if ($meet->notes)
                        <p class="muted notes">{{ $meet->notes }}</p>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    @if ($pastResults)
        <section class="past">
            <p class="section-eyebrow">ARCHIVE</p>
            <h2 class="section-title">過去の大会結果</h2>
            <div class="year-links">
                @foreach ($pastResults as $y => $url)
                    <a href="{{ $url }}" @if (! str_starts_with($url, url('/'))) target="_blank" rel="noopener" @endif>
                        <span class="y">{{ $y }}</span><span class="t">年度の結果</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @php $contact = config('kizuna.contact'); @endphp
    <section class="contact-box">
        <h2>競技会担当者（お問い合わせ）</h2>
        <p>{{ $contact['name'] }}<br>
            電話番号 <a href="tel:{{ preg_replace('/[^0-9]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>　
            メール <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>
    </section>

    <section class="cta">
        <div>
            <h2>エントリー方法は2通り</h2>
            <p>何度も参加される方は<strong>Webエントリー</strong>が便利です。今までどおり<strong>エントリーシート（エクセル）を添付</strong>しての申し込みもできます。</p>
        </div>
        <div class="cta-actions">
            <a class="btn btn-light" href="{{ route('home') }}">エントリーへ進む</a>
            <a class="btn btn-outline-light" href="{{ asset('downloads/kizuna-entry-sheet.xlsx') }}" download>エントリーシートをダウンロード</a>
        </div>
    </section>
@endsection
