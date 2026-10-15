@extends('layouts.app')
@section('title', $meet->name.' エントリー')

@section('content')
    <h1>{{ $meet->name }} エントリー</h1>
    <p class="muted">
        開催日 {{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}・締切 {{ $meet->entry_closes_at->format('Y/m/d H:i') }}・1人{{ $meet->max_events_per_athlete }}種目まで<br>
        出場する選手にチェックを入れ、申込み種目と申請タイム（例 14'30）を入力してください。PRしたい一言は任意です。<br>
        前回の大会で同じ種目に出た選手は、種目を選ぶと前回の申請タイムが自動で入ります。
    </p>

    @if ($athletes->isEmpty())
        <div class="card">
            <p>エントリーできる選手が登録されていません。</p>
            <a class="btn" href="{{ route('athletes.index') }}">選手を登録する</a>
        </div>
    @else
        <form method="POST" action="{{ route('entries.review', $meet) }}" id="entry-form">
            @csrf
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
                                                $left = $remaining[$event->id];
                                                $isEntered = $already->contains($event->id);
                                            @endphp
                                            <option value="{{ $event->id }}" data-fee="{{ $event->fee }}" data-name="{{ $event->name }}"
                                                    @selected((string) ($slot['event_id'] ?? '') === (string) $event->id)
                                                    @disabled($isEntered || $left === 0)>
                                                {{ $event->name }}（{{ number_format($event->fee) }}円{{ $left === 0 ? '・定員到達' : ($left !== null && $left <= 10 ? '・残'.$left : '') }}{{ $isEntered ? '・申込済' : '' }}）
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="times">
                                        <input type="text" name="items[{{ $athlete->id }}][events][{{ $i }}][record]" value="{{ $slot['record'] ?? '' }}"
                                               placeholder="例 14'30" aria-label="申請タイム" data-record>
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
