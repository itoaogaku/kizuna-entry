@extends('layouts.app')
@section('title', $meet->exists ? '大会の編集' : '新規大会登録')

@section('content')
    <h1>{{ $meet->exists ? '大会の編集' : '新規大会登録' }}</h1>
    @include('admin.nav')

    @if (! $meet->exists && $meets->isNotEmpty())
        <form method="GET" class="filters">
            <label>内容を引き継ぐ大会
                <select name="copy" onchange="this.form.submit()">
                    @foreach ($meets as $m)
                        <option value="{{ $m->id }}" @selected($source?->id === $m->id)>{{ $m->name }}（{{ $m->held_on->format('Y/m/d') }}）</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="btn small" type="submit">引き継ぐ</button></noscript>
            <span class="muted">会場・種目・料金・定員・案内文を引き継ぎます。日付と受付期間は入れ直してください。</span>
        </form>
    @endif
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
            @unless ($meet->exists)
                @php
                    $rows = old('events', $events);
                    $rows = array_merge(array_values($rows), array_fill(0, max(3, 8 - count($rows)), []));
                @endphp
                <h2>種目・料金</h2>
                <p class="muted">種目名が空の行は登録されません。登録後も、大会の画面から種目の追加・変更ができます。</p>
                <div class="table-wrap">
                    <table data-events>
                        <thead><tr><th>種目名</th><th>性別</th><th>料金（円）</th><th>定員 <span class="muted">空欄＝無制限</span></th></tr></thead>
                        <tbody>
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td><input type="text" name="events[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="例：5000m" aria-label="種目名"></td>
                                <td>
                                    <select name="events[{{ $i }}][gender]" aria-label="性別">
                                        <option value="" @selected(($row['gender'] ?? null) === null)>男女共通</option>
                                        <option value="M" @selected(($row['gender'] ?? null) === 'M')>男子</option>
                                        <option value="F" @selected(($row['gender'] ?? null) === 'F')>女子</option>
                                    </select>
                                </td>
                                <td><input type="number" name="events[{{ $i }}][fee]" value="{{ $row['fee'] ?? '' }}" min="50" style="width:110px" aria-label="料金"></td>
                                <td><input type="number" name="events[{{ $i }}][capacity]" value="{{ $row['capacity'] ?? '' }}" min="1" style="width:100px" aria-label="定員"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn small secondary" type="button" data-add-event style="margin:8px 0 16px">行を追加</button>
            @endunless
            <div class="field">
                <label class="inline"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $meet->is_published))> 公開する（受付期間中はエントリーできるようになります）</label>
            </div>
            <div class="actions">
                <button class="btn" type="submit">{{ $meet->exists ? '保存する' : '大会を登録する' }}</button>
                <a href="{{ $meet->exists ? route('admin.meets.show', $meet) : route('admin.meets.index') }}">戻る</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-add-event]')?.addEventListener('click', () => {
    const body = document.querySelector('[data-events] tbody');
    const last = body.lastElementChild;
    const row = last.cloneNode(true);
    const index = body.children.length;
    row.querySelectorAll('input, select').forEach(el => {
        el.name = el.name.replace(/events\[\d+\]/, `events[${index}]`);
        if (el.tagName === 'INPUT') el.value = ''; else el.selectedIndex = 0;
    });
    body.appendChild(row);
});
</script>
@endpush
