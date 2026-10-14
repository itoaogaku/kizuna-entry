@extends('layouts.app')
@section('title', $athlete->exists ? '選手情報の編集' : '選手の追加')

@section('content')
    <h1>{{ $athlete->exists ? '選手情報の編集' : '選手の追加' }}</h1>
    <div class="card">
        <form method="POST" action="{{ $athlete->exists ? route('athletes.update', $athlete) : route('athletes.store') }}">
            @csrf
            @if ($athlete->exists) @method('PUT') @endif
            @include('athletes.fields')
            @if ($athlete->exists && auth()->user()->isTeam())
                <div class="field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="inline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $athlete->is_active))> エントリー対象（チェックを外すと一覧に表示されません）</label>
                </div>
            @endif
            <div class="actions">
                <button class="btn" type="submit">保存する</button>
                <a href="{{ route('athletes.index') }}">戻る</a>
            </div>
        </form>
    </div>
@endsection
