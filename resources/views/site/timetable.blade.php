@extends('layouts.app')
@section('title', $meet->name.' タイムテーブル')

@section('content')
    @include('site._meet-head', ['eyebrow' => 'TIME TABLE', 'title' => 'タイムテーブル'])
    @include('site._timetable')
    <div class="actions">
        <a class="btn" href="{{ route('site.startlist', $meet) }}">スタートリストを見る</a>
        <a class="btn secondary" href="{{ route('site.meets') }}#meet-{{ $meet->id }}">大会開催ページに戻る</a>
    </div>
@endsection
