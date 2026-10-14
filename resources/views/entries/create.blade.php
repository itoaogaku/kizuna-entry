@extends('layouts.app')
@section('title', $meet->name.' エントリー')

@section('content')
    <h1>{{ $meet->name }} エントリー</h1>
    <p class="muted">
        開催日 {{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}・締切 {{ $meet->entry_closes_at->format('Y/m/d H:i') }}・1人{{ $meet->max_events_per_athlete }}種目まで<br>
        出場する選手にチェックを入れ、種目と申告記録を選んでください。
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
                        <th>区分</th>
                        @for ($i = 0; $i < $meet->max_events_per_athlete; $i++)
                            <th>種目{{ $meet->max_events_per_athlete > 1 ? $i + 1 : '' }}</th>
                            <th>申告記録</th>
                        @endfor
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($athletes as $athlete)
                        @php
                            $row = $old[$athlete->id] ?? [];
                            $checked = ! empty($row['selected']);
                            $already = $enteredByAthlete->get($athlete->id, collect());
                        @endphp
                        <tr data-row class="{{ $checked ? 'selected' : '' }}">
                            <td class="sticky-col">
                                <label class="athlete-pick">
                                    <input type="checkbox" name="items[{{ $athlete->id }}][selected]" value="1" data-select @checked($checked)>
                                    <span>{{ $athlete->fullName() }}<br><span class="muted">{{ $athlete->fullKana() }}</span></span>
                                </label>
                                @if ($already->isNotEmpty())
                                    <span class="badge paid">申込済：{{ $events->whereIn('id', $already)->pluck('name')->implode('、') }}</span>
                                @endif
                            </td>
                            <td>{{ $athlete->genderLabel() }}</td>
                            <td>{{ $athlete->category }}</td>
                            @for ($i = 0; $i < $meet->max_events_per_athlete; $i++)
                                @php $slot = $row['events'][$i] ?? []; @endphp
                                <td>
                                    <select name="items[{{ $athlete->id }}][events][{{ $i }}][event_id]" data-event aria-label="種目">
                                        <option value="">―</option>
                                        @foreach ($events as $event)
                                            @continue (! $event->allowsGender($athlete->gender))
                                            @php
                                                $left = $remaining[$event->id];
                                                $isEntered = $already->contains($event->id);
                                            @endphp
                                            <option value="{{ $event->id }}" data-fee="{{ $event->fee }}"
                                                    @selected((string) ($slot['event_id'] ?? '') === (string) $event->id)
                                                    @disabled($isEntered || $left === 0)>
                                                {{ $event->name }}（{{ number_format($event->fee) }}円{{ $left === 0 ? '・定員到達' : ($left !== null && $left <= 10 ? '・残'.$left : '') }}{{ $isEntered ? '・申込済' : '' }}）
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $athlete->id }}][events][{{ $i }}][record]" value="{{ $slot['record'] ?? '' }}"
                                           placeholder="例 15:32.10" aria-label="申告記録" size="10">
                                </td>
                            @endfor
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
        // 種目を選んだら、その選手に自動でチェックを入れる
        if (e.target.matches('[data-event]') && e.target.value) {
            e.target.closest('tr').querySelector('[data-select]').checked = true;
        }
        update();
    });
    update();
})();
</script>
@endpush
