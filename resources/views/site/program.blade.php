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
                <p>{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $meet->venue ? '　'.$meet->venue : '' }}</p>
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
            @include('site._startlist')
        </section>
    </div>
@endsection
