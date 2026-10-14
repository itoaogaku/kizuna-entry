@extends('layouts.app')
@section('title', $meet->exists ? '大会の編集' : '大会の作成')

@section('content')
    <h1>{{ $meet->exists ? '大会の編集' : '大会の作成' }}</h1>
    @include('admin.nav')
    <div class="card">
        <form method="POST" action="{{ $meet->exists ? route('admin.meets.update', $meet) : route('admin.meets.store') }}">
            @csrf
            @if ($meet->exists) @method('PUT') @endif
            <div class="field">
                <label for="name">大会名</label>
                <input id="name" type="text" name="name" value="{{ old('name', $meet->name) }}" placeholder="例：第10回 絆記録会" required>
            </div>
            <div class="row">
                <div class="field">
                    <label for="held_on">開催日</label>
                    <input id="held_on" type="date" name="held_on" value="{{ old('held_on', $meet->held_on?->format('Y-m-d')) }}" required>
                </div>
                <div class="field">
                    <label for="venue">会場</label>
                    <input id="venue" type="text" name="venue" value="{{ old('venue', $meet->venue) }}">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="entry_opens_at">受付開始</label>
                    <input id="entry_opens_at" type="datetime-local" name="entry_opens_at" value="{{ old('entry_opens_at', $meet->entry_opens_at?->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="field">
                    <label for="entry_closes_at">受付締切</label>
                    <input id="entry_closes_at" type="datetime-local" name="entry_closes_at" value="{{ old('entry_closes_at', $meet->entry_closes_at?->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="field">
                    <label for="max_events_per_athlete">1人あたりの最大種目数</label>
                    <input id="max_events_per_athlete" type="number" min="1" max="10" name="max_events_per_athlete" value="{{ old('max_events_per_athlete', $meet->max_events_per_athlete) }}" required>
                </div>
            </div>
            <div class="field">
                <label for="notes">案内文 <span class="hint">（トップページに表示）</span></label>
                <textarea id="notes" name="notes">{{ old('notes', $meet->notes) }}</textarea>
            </div>
            <div class="field">
                <label class="inline"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $meet->is_published))> 公開する（受付期間中はエントリーできるようになります）</label>
            </div>
            <div class="actions">
                <button class="btn" type="submit">保存する</button>
                <a href="{{ $meet->exists ? route('admin.meets.show', $meet) : route('admin.meets.index') }}">戻る</a>
            </div>
        </form>
    </div>
@endsection
