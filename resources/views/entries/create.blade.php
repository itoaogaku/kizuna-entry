@extends('layouts.app')
@section('title', $meet->name.' エントリー')

@section('content')
    <h1>{{ $meet->name }} エントリー</h1>
    <p class="muted">
        開催日 {{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}・締切 {{ $meet->entry_closes_at->format('Y/m/d H:i') }}・1人{{ $meet->max_events_per_athlete }}種目まで<br>
        出場する選手にチェックを入れ、申込み種目と申請タイムを入力してください。PRしたい一言は任意です。<br>
        申請タイムは数字だけでも入力できます（1430 → 14'30、40530 → 4'05"30）。前回同じ種目に出た選手は、種目を選ぶと前回の申請タイムが入ります。
    </p>

    @if ($previousMeet && empty($old))
        <div class="card quick">
            <div>
                <strong>前回（{{ $previousMeet->name }}）と同じ内容で申し込む</strong><br>
                <span class="muted">前回の選手・種目・申請タイムをまとめて入れます。変えるところだけ直してください。</span>
            </div>
            <a class="btn" href="{{ route('entries.create', ['meet' => $meet, 'copy' => 'previous']) }}">前回と同じ内容を入れる</a>
        </div>
    @endif

    @if ($athletes->isEmpty())
        <div class="card">
            <p>エントリーできる選手が登録されていません。</p>
            <a class="btn" href="{{ route('athletes.index') }}">選手を登録する</a>
        </div>
    @else
        <form method="POST" action="{{ route('entries.review', $meet) }}" id="entry-form">
            @csrf
            <div class="bulk" data-bulk>
                <span class="bulk-label">一括入力：</span>
                <select data-bulk-event aria-label="一括で設定する種目">
                    <option value="">種目を選ぶ</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </select>
                <button class="btn small secondary" type="button" data-bulk-apply>チェックした選手に設定</button>
                <span class="muted" data-bulk-message></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th class="sticky-col">出場・氏名</th>
                        <th>性別</th>
                        <th>種別・学年</th>
                        @for ($i = 0; $i < $meet->max_events_per_athlete; $i++)
                            <th>申込み種目{{ $meet->max_events_per_athlete > 1 ? $i + 1 : '' }}<br><span class="muted">申請タイム</span></th>
                        @endfor
                        <th>PRしたい一言 <span class="muted">（任意・実況で使用）</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($athletes as $athlete)
                        @php
                            $row = $old[$athlete->id] ?? [];
                            $checked = ! empty($row['selected']);
                            $already = $enteredByAthlete->get($athlete->id, collect());
                        @endphp
                        <tr data-row class="{{ $checked ? 'selected' : '' }}" data-previous='@json($previousTargets[$athlete->id] ?? (object) [])'>
                            <td class="sticky-col">
                                <label class="athlete-pick">
                                    <input type="checkbox" name="items[{{ $athlete->id }}][selected]" value="1" data-select @checked($checked)>
                                    <span>{{ $athlete->fullName() }}<br><span class="muted">{{ $athlete->kana }}</span></span>
                                </label>
                                @if ($already->isNotEmpty())
                                    <span class="badge paid">申込済：{{ $events->whereIn('id', $already)->pluck('name')->implode('、') }}</span>
                                @endif
                            </td>
                            <td>{{ $athlete->genderLabel() }}</td>
                            <td>{{ $athlete->category }}{{ $athlete->grade ? ' '.$athlete->grade.'年' : '' }}</td>
                            @for ($i = 0; $i < $meet->max_events_per_athlete; $i++)
                                @php $slot = $row['events'][$i] ?? []; @endphp
                                <td class="slot">
                                    <select name="items[{{ $athlete->id }}][events][{{ $i }}][event_id]" data-event aria-label="申込み種目">
                                        <option value="">―</option>
                                        @foreach ($events as $event)
                                            @continue (! $event->allowsGender($athlete->gender))
                                            @php
                                                $fee = $meet->feeFor($athlete->category, $event);
                                                $left = $remaining[$event->id];
                                                $isEntered = $already->contains($event->id);
                                            @endphp
                                            <option value="{{ $event->id }}" data-fee="{{ $fee }}" data-name="{{ $event->name }}"
                                                    @selected((string) ($slot['event_id'] ?? '') === (string) $event->id)
                                                    @disabled($isEntered || $left === 0)>
                                                {{ $event->name }}（{{ number_format($fee) }}円{{ $left === 0 ? '・定員到達' : ($left !== null && $left <= 10 ? '・残'.$left : '') }}{{ $isEntered ? '・申込済' : '' }}）
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="times">
                                        <input type="text" name="items[{{ $athlete->id }}][events][{{ $i }}][record]" value="{{ $slot['record'] ?? '' }}"
                                               placeholder="例 1430" aria-label="申請タイム" data-record inputmode="numeric" autocomplete="off">
                                    </div>
                                </td>
                            @endfor
                            <td>
                                <input type="text" name="items[{{ $athlete->id }}][pr]" value="{{ $row['pr'] ?? '' }}" maxlength="100"
                                       placeholder="例：チーム内No.1の練習量" aria-label="PRしたい一言" class="pr">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="total-bar">
                <div>選択中 <strong id="count">0</strong> 人・<strong id="items">0</strong> 種目　合計 <strong id="total">0</strong> 円</div>
                <button class="btn" type="submit">確認画面へ</button>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('entry-form');
    if (!form) return;
    const update = () => {
        let people = 0, items = 0, total = 0;
        form.querySelectorAll('tr[data-row]').forEach(tr => {
            const on = tr.querySelector('[data-select]').checked;
            tr.classList.toggle('selected', on);
            if (!on) return;
            people++;
            tr.querySelectorAll('[data-event]').forEach(sel => {
                const opt = sel.selectedOptions[0];
                if (opt && opt.dataset.fee) { items++; total += Number(opt.dataset.fee); }
            });
        });
        document.getElementById('count').textContent = people;
        document.getElementById('items').textContent = items;
        document.getElementById('total').textContent = total.toLocaleString('ja-JP');
    };
    // 数字だけの申請タイムを「14'30」の形にして見せる（サーバー側でも同じ変換をする）
    const formatTime = v => {
        const d = v.replace(/[０-９]/g, c => String.fromCharCode(c.charCodeAt(0) - 0xFEE0)).trim();
        if (!/^\d{3,6}$/.test(d)) return v;
        let main = d, frac = '';
        if (d.length >= 5) { main = d.slice(0, -2); frac = '"' + d.slice(-2); }
        return String(Number(main.slice(0, -2))) + "'" + main.slice(-2) + frac;
    };
    form.addEventListener('focusout', e => {
        if (e.target.matches('[data-record]')) e.target.value = formatTime(e.target.value);
    });

    // 一括入力：チェックした選手の空いている種目欄に、同じ種目を入れる
    const bulk = form.querySelector('[data-bulk]');
    bulk?.querySelector('[data-bulk-apply]').addEventListener('click', () => {
        const eventId = bulk.querySelector('[data-bulk-event]').value;
        const message = bulk.querySelector('[data-bulk-message]');
        if (!eventId) { message.textContent = '種目を選んでください。'; return; }
        let applied = 0, skipped = 0;
        form.querySelectorAll('tr[data-row]').forEach(tr => {
            if (!tr.querySelector('[data-select]').checked) return;
            const selects = [...tr.querySelectorAll('[data-event]')];
            if (selects.some(sel => sel.value === eventId)) return;
            const target = selects.find(sel => !sel.value);
            const option = target?.querySelector(`option[value="${eventId}"]`);
            if (!target || !option || option.disabled) { skipped++; return; }
            target.value = eventId;
            target.dispatchEvent(new Event('change', { bubbles: true }));
            applied++;
        });
        message.textContent = applied + '人に設定しました。' + (skipped ? `（${skipped}人は性別・定員・種目数の都合で設定できませんでした）` : '');
        update();
    });

    form.addEventListener('change', e => {
        // 種目を選んだら、その選手に自動でチェックを入れ、前回の申請タイムがあれば入れる
        if (e.target.matches('[data-event]') && e.target.value) {
            const tr = e.target.closest('tr');
            tr.querySelector('[data-select]').checked = true;
            const record = e.target.closest('td').querySelector('[data-record]');
            const name = e.target.selectedOptions[0].dataset.name;
            const previous = JSON.parse(tr.dataset.previous || '{}');
            if (record && !record.value && previous[name]) record.value = previous[name];
        }
        update();
    });
    update();
})();
</script>
@endpush
