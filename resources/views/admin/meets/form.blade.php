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
        <form method="POST" action="{{ $meet->exists ? route('admin.meets.update', $meet) : route('admin.meets.store') }}" enctype="multipart/form-data">
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
            <div class="row">
                <div class="field">
                    <label for="start_time">競技開始予定</label>
                    <input id="start_time" type="time" name="start_time" value="{{ old('start_time', $meet->start_time ? substr($meet->start_time, 0, 5) : '') }}">
                </div>
                @foreach (\App\Models\Meet::FEE_LABELS as $key => $label)
                    <div class="field">
                        <label for="{{ $key }}">参加料：{{ $label }}（円）</label>
                        <input id="{{ $key }}" type="number" name="{{ $key }}" value="{{ old($key, $meet->{$key}) }}" min="0" placeholder="例：{{ ['fee_junior_high' => 1500, 'fee_student' => 2200, 'fee_general' => 3300][$key] }}">
                    </div>
                @endforeach
            </div>
            <p class="hint" style="margin-top:-8px">区分別の参加料を入れると、種目の料金より優先されます（1種目ごと）。空欄なら種目ごとの料金を使います。</p>
            <div class="row">
                <div class="field">
                    <label for="division">部門</label>
                    <input id="division" type="text" name="division" value="{{ old('division', $meet->division ?? '公認の部') }}" required>
                </div>
                <div class="field">
                    <label for="eligible">対象 <span class="hint">（大会開催ページに表示）</span></label>
                    <input id="eligible" type="text" name="eligible" value="{{ old('eligible', $meet->eligible) }}" placeholder="例：中学生・高校生・大学生・一般">
                </div>
            </div>

            <h2>大会開催ページの資料・リンク</h2>
            <p class="muted">PDFをアップロードするか、URLを入れてください（両方ある場合はPDFを表示します）。入っているものだけボタンとして表示されます。</p>
            @foreach (\App\Models\Meet::DOCUMENTS as $key => $label)
                <div class="doc-field">
                    <strong>{{ $label }}</strong>
                    <div class="row">
                        <div class="field">
                            <label for="{{ $key }}_file">PDF</label>
                            <input id="{{ $key }}_file" type="file" name="{{ $key }}_file" accept="application/pdf,.pdf">
                            @if ($meet->{$key.'_path'})
                                <p class="hint"><a href="{{ $meet->documentUrl($key) }}" target="_blank" rel="noopener">登録済みのPDFを見る</a>
                                    <label class="inline"><input type="checkbox" name="{{ $key }}_remove" value="1"> PDFを削除</label></p>
                            @endif
                        </div>
                        <div class="field">
                            <label for="{{ $key }}_url">またはURL</label>
                            <input id="{{ $key }}_url" type="url" name="{{ $key }}_url" value="{{ old($key.'_url', $meet->{$key.'_url'}) }}" placeholder="https://">
                        </div>
                        @if ($key === 'program')
                            <div class="field">
                                <label for="program_note">更新メモ <span class="hint">（例：0628更新）</span></label>
                                <input id="program_note" type="text" name="program_note" value="{{ old('program_note', $meet->program_note) }}">
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="row">
                <div class="field">
                    <label for="photo_url">写真館（URL）</label>
                    <input id="photo_url" type="url" name="photo_url" value="{{ old('photo_url', $meet->photo_url) }}" placeholder="https://">
                </div>
                <div class="field">
                    <label for="press_url">取材・撮影のお申込み（URL）</label>
                    <input id="press_url" type="url" name="press_url" value="{{ old('press_url', $meet->press_url) }}" placeholder="https://">
                </div>
            </div>

            <div class="field">
                <label for="notes">案内文 <span class="hint">（大会開催ページ・エントリー画面に表示）</span></label>
                <textarea id="notes" name="notes">{{ old('notes', $meet->notes) }}</textarea>
            </div>
            @unless ($meet->exists)
                @php
                    $rows = old('events', $events);
                    $rows = array_merge(array_values($rows), array_fill(0, max(3, 8 - count($rows)), []));
                @endphp
                <h2>種目・料金</h2>
                <p class="muted">種目名が空の行は登録されません。区分別の参加料を入れた場合、種目の料金は空欄で構いません。登録後も、大会の画面から種目の追加・変更ができます。</p>
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
