@extends('layouts.app')
@section('title', $meet->name.' スタートリスト')

@section('content')
    @include('site._meet-head', ['eyebrow' => 'START LIST', 'title' => 'スタートリスト'])
    <nav class="race-nav">
        @foreach ($races as $race)
            <a href="#race-{{ $race->id }}">{{ $race->name() }}</a>
        @endforeach
    </nav>
    @include('site._startlist')
    <div class="actions">
        <a class="btn" href="{{ route('site.timetable', $meet) }}">タイムテーブルを見る</a>
        <a class="btn secondary" href="{{ route('site.meets') }}#meet-{{ $meet->id }}">大会開催ページに戻る</a>
    </div>
@endsection
