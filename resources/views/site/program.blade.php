@extends('layouts.app')
@section('title', $meet->name.' 大会プログラム')

@section('content')
    <div class="program">
        <div class="no-print actions" style="margin-bottom:12px">
            <button class="btn" type="button" onclick="window.print()">印刷する／PDFで保存</button>
            <a class="btn secondary" href="{{ route('site.meets') }}#meet-{{ $meet->id }}">大会開催ページに戻る</a>
        </div>

        <header class="program-cover">
            <img src="{{ asset('images/logo.png') }}" alt="" width="64">
            <div>
                <p class="section-eyebrow">OFFICIAL PROGRAM</p>
                <h1>{{ $meet->name }}</h1>
                <p>{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $meet->startTimeLabel() ? '　'.$meet->startTimeLabel().' 競技開始予定' : '' }}{{ $meet->venue ? '　'.$meet->venue : '' }}</p>
                <p class="muted">主催：{{ config('kizuna.organization.name') }}
                    @unless ($meet->isProgramFinal())　<strong>※暫定版</strong>@endunless</p>
            </div>
        </header>

        @if ($meet->program_notes)
            <section class="program-notes">
                <h2>競技上の注意</h2>
                <p>{{ $meet->program_notes }}</p>
            </section>
        @endif

        <section>
            <h2>タイムテーブル</h2>
            @include('site._timetable')
        </section>

        <section class="page-break">
            <h2>スタートリスト</h2>
            <p class="muted">ナンバーカードは各自で用意し、スタートリストのナンバーを白地に黒数字で明確に書き、胸部と背部につけてください。</p>
            @include('site._startlist')
        </section>

        <p class="muted" style="margin-top:20px">競技会担当者（お問い合わせ）：{{ config('kizuna.contact.name') }}　{{ config('kizuna.contact.phone') }}　{{ config('kizuna.contact.email') }}</p>
    </div>
@endsection
