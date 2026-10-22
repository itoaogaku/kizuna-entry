@extends('layouts.app')
@section('title', '個人でエントリー')

@section('content')
    <p class="section-eyebrow">個人申し込み</p>
    <h1>個人でエントリー</h1>
    <p class="muted">アカウント登録・ログインは不要です。必要事項を入力して確認画面へ進み、そのままお支払い（カードまたはコンビニ）に進めます。入金を確認した時点でエントリー完了です。</p>

    @if ($meets->isEmpty())
        <div class="card"><p>現在、エントリーを受け付けている大会はありません。</p></div>
    @else
        <form method="GET" action="{{ route('individual-entry.create') }}" class="card" id="meet-form">
            <div class="field">
                <label for="meet">大会</label>
                <select id="meet" name="meet" onchange="this.form.submit()">
                    @foreach ($meets as $m)
                        <option value="{{ $m->id }}" @selected($m->id === $meet->id)>{{ $m->name }}（{{ $m->held_on->isoFormat('M月D日（ddd）') }}・締切 {{ $m->entry_closes_at->format('n/j H:i') }}）</option>
                    @endforeach
                </select>
            </div>
            <noscript><button class="btn secondary small" type="submit">この大会にする</button></noscript>
        </form>

        @php
            $a = $draft['athlete'] ?? [];
            $slots = old('events', $draft['events'] ?? []);
            $maxSlots = $meet->max_events_per_athlete;
            $visible = min($maxSlots, max(1, count(array_filter($slots, fn ($s) => ($s['event_id'] ?? '') !== ''))));
        @endphp
        <form method="POST" action="{{ route('individual-entry.review') }}" class="card" id="individual-form">
            @csrf
            <input type="hidden" name="meet_id" value="{{ $meet->id }}">

            <h2>選手の情報</h2>
            <div class="row">
                <div class="field">
                    <label for="last_name">姓</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $a['last_name'] ?? '') }}" placeholder="鈴木" required autocomplete="family-name">
                </div>
                <div class="field">
                    <label for="first_name">名</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $a['first_name'] ?? '') }}" placeholder="太郎" required autocomplete="given-name">
                </div>
                <div class="field">
                    <label for="kana">フリガナ <span class="hint">（姓と名の間にスペース）</span></label>
                    <input id="kana" type="text" name="kana" value="{{ old('kana', $a['kana'] ?? '') }}" placeholder="スズキ タロウ">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label>性別</label>
                    @foreach (\App\Models\Athlete::GENDERS as $value => $label)
                        <label class="inline"><input type="radio" name="gender" value="{{ $value }}" @checked(old('gender', $a['gender'] ?? '') === $value) required> {{ $label }}</label>
                    @endforeach
                </div>
                <div class="field">
                    <label for="category">種別</label>
                    <select id="category" name="category" required>
                        <option value="">選んでください</option>
                        @foreach (\App\Models\Athlete::CATEGORIES as $category)
                            <option value="{{ $category }}" @selected(old('category', $a['category'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="grade">学年 <span class="hint">（学生の場合）</span></label>
                    <select id="grade" name="grade">
                        <option value="">―</option>
                        @foreach (\App\Models\Athlete::GRADES as $g)
                            <option value="{{ $g }}" @selected((string) old('grade', $a['grade'] ?? '') === $g)>{{ \App\Models\Athlete::formatGrade($g) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="association">所属陸協</label>
                    <select id="association" name="association">
                        <option value="">―</option>
                        @foreach (\App\Models\Athlete::ASSOCIATIONS as $association)
                            <option value="{{ $association }}" @selected(old('association', $a['association'] ?? '') === $association)>{{ $association }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="team_name">所属 <span class="hint">（学校名・チーム名など。プログラムの所属欄に載ります。なければ空欄）</span></label>
                <input id="team_name" type="text" name="team_name" value="{{ old('team_name', $draft['team_name'] ?? '') }}" maxlength="100" placeholder="例：〇〇大学、〇〇RC">
            </div>

            <h2>申込み種目</h2>
            @php
                $feeText = $meet->hasCategoryFees()
                    ? collect(\App\Models\Meet::FEE_LABELS)->map(fn ($label, $key) => $meet->{$key} ? $label.' '.number_format($meet->{$key}).'円' : null)->filter()->implode('　')
                    : $events->map(fn ($e) => $e->name.' '.number_format($e->fee).'円')->implode('　');
            @endphp
            <p class="muted">参加料（1種目）：{{ $feeText }}・1人{{ $maxSlots }}種目まで。申請タイムは数字だけでも入力できます（1430 → 14'30）。</p>
            @for ($i = 0; $i < $maxSlots; $i++)
                <div class="row slot-row" data-slot @if ($i >= $visible) hidden @endif>
                    <div class="field">
                        <label for="event-{{ $i }}">申込み種目{{ $maxSlots > 1 ? $i + 1 : '' }}</label>
                        <select id="event-{{ $i }}" name="events[{{ $i }}][event_id]" data-event>
                            <option value="">―</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}" data-gender="{{ $event->gender }}" @selected((string) ($slots[$i]['event_id'] ?? '') === (string) $event->id)>{{ $event->gender ? \App\Models\Athlete::GENDERS[$event->gender].'子' : '' }}{{ $event->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="record-{{ $i }}">申請タイム</label>
                        <input id="record-{{ $i }}" type="text" name="events[{{ $i }}][record]" value="{{ $slots[$i]['record'] ?? '' }}" placeholder="例 1430" inputmode="numeric" autocomplete="off" data-record>
                    </div>
                </div>
            @endfor
            @if ($maxSlots > 1)
                <p><button type="button" class="btn secondary small" data-add-slot @if ($visible >= $maxSlots) hidden @endif>＋ 種目を追加</button></p>
            @endif
            <div class="field">
                <label for="pr">PRしたい一言 <span class="hint">（任意・実況で使用）</span></label>
                <input id="pr" type="text" name="pr" value="{{ old('pr', $draft['pr'] ?? '') }}" maxlength="100" placeholder="例：初の14分台を狙います">
            </div>

            <h2>連絡先</h2>
            <div class="row">
                <div class="field">
                    <label for="email">メールアドレス <span class="hint">（受付・入金確認のメールと領収書が届きます）</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email', $draft['email'] ?? '') }}" required autocomplete="email">
                </div>
                <div class="field">
                    <label for="phone">電話番号</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone', $draft['phone'] ?? '') }}" required autocomplete="tel">
                </div>
            </div>
            @include('partials.agree')
            <button class="btn" type="submit">確認画面へ</button>
        </form>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('individual-form');
    if (!form) return;
    const add = form.querySelector('[data-add-slot]');
    add?.addEventListener('click', () => {
        const next = form.querySelector('[data-slot][hidden]');
        if (next) next.hidden = false;
        if (!form.querySelector('[data-slot][hidden]')) add.hidden = true;
    });
    // 性別に合わない種目は選べないようにする
    const syncGender = () => {
        const gender = form.querySelector('input[name="gender"]:checked')?.value;
        form.querySelectorAll('[data-event] option[data-gender]').forEach(o => {
            o.disabled = !!(gender && o.dataset.gender && o.dataset.gender !== gender);
        });
    };
    form.querySelectorAll('input[name="gender"]').forEach(r => r.addEventListener('change', syncGender));
    syncGender();
    // 数字だけの申請タイムを「14'30」の形にして見せる（サーバー側でも同じ変換をする）
    form.addEventListener('focusout', e => {
        if (!e.target.matches('[data-record]')) return;
        const d = e.target.value.replace(/[０-９]/g, c => String.fromCharCode(c.charCodeAt(0) - 0xFEE0)).trim();
        if (!/^\d{3,6}$/.test(d)) return;
        let main = d, frac = '';
        if (d.length >= 5) { main = d.slice(0, -2); frac = '"' + d.slice(-2); }
        e.target.value = String(Number(main.slice(0, -2))) + "'" + main.slice(-2) + frac;
    });
})();
</script>
@endpush
