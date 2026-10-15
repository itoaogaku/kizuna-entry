@extends('layouts.app')
@section('title', $meet->name)

@section('content')
    <h1>{{ $meet->name }}</h1>
    @include('admin.nav')
    <div class="card">
        <dl class="kv">
            <dt>開催日</dt><dd>{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }} {{ $meet->venue }}</dd>
            <dt>受付期間</dt><dd>{{ $meet->entry_opens_at->format('Y/m/d H:i') }} 〜 {{ $meet->entry_closes_at->format('Y/m/d H:i') }}</dd>
            <dt>公開</dt><dd>{{ $meet->is_published ? '公開' : '非公開' }}{{ $meet->isAcceptingEntries() ? '（受付中）' : '' }}</dd>
            <dt>最大種目数</dt><dd>1人{{ $meet->max_events_per_athlete }}種目</dd>
        </dl>
        <div class="actions">
            <a class="btn secondary" href="{{ route('admin.meets.edit', $meet) }}">大会情報を編集</a>
            <a class="btn" href="{{ route('admin.roster.index', $meet) }}">申込一覧（目標タイム順）</a>
            <a class="btn secondary" href="{{ route('admin.entries.index', $meet) }}">申込・入金状況</a>
        </div>
    </div>

    <h2>種目</h2>
    <p class="muted">「申込数」は入金済みと決済待ちの合計です。定員が空欄の種目は無制限です。</p>
    {{-- 表の各行の入力欄は form 属性でこれらのフォームに結び付ける --}}
    @foreach ($meet->events as $event)
        <form method="POST" action="{{ route('admin.events.update', $event) }}" id="event-{{ $event->id }}">@csrf @method('PUT')</form>
        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" id="event-delete-{{ $event->id }}"
              onsubmit="return confirm('この種目を削除しますか？');">@csrf @method('DELETE')</form>
    @endforeach
    <form method="POST" action="{{ route('admin.events.store', $meet) }}" id="event-new">@csrf</form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>順</th><th>種目名</th><th>性別</th><th>参加費（円）</th><th>定員</th><th class="num">申込数</th><th></th></tr></thead>
            <tbody>
            @foreach ($meet->events as $event)
                <tr>
                    <td><input form="event-{{ $event->id }}" type="number" name="sort_order" value="{{ $event->sort_order }}" style="width:70px"></td>
                    <td><input form="event-{{ $event->id }}" type="text" name="name" value="{{ $event->name }}" required></td>
                    <td>
                        <select form="event-{{ $event->id }}" name="gender">
                            <option value="" @selected($event->gender === null)>共通</option>
                            <option value="M" @selected($event->gender === 'M')>男子</option>
                            <option value="F" @selected($event->gender === 'F')>女子</option>
                        </select>
                    </td>
                    <td><input form="event-{{ $event->id }}" type="number" name="fee" value="{{ $event->fee }}" min="50" style="width:100px" required></td>
                    <td><input form="event-{{ $event->id }}" type="number" name="capacity" value="{{ $event->capacity }}" min="1" style="width:90px"></td>
                    <td class="num">{{ $reserved[$event->id] }}</td>
                    <td>
                        <button form="event-{{ $event->id }}" class="btn small" type="submit">保存</button>
                        <button form="event-delete-{{ $event->id }}" class="btn danger small" type="submit">削除</button>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td><input form="event-new" type="number" name="sort_order" value="{{ ($meet->events->max('sort_order') ?? 0) + 10 }}" style="width:70px"></td>
                <td><input form="event-new" type="text" name="name" placeholder="例：一般男子5000m" required></td>
                <td>
                    <select form="event-new" name="gender">
                        <option value="">共通</option>
                        <option value="M">男子</option>
                        <option value="F">女子</option>
                    </select>
                </td>
                <td><input form="event-new" type="number" name="fee" min="50" style="width:100px" required></td>
                <td><input form="event-new" type="number" name="capacity" min="1" style="width:90px"></td>
                <td></td>
                <td><button form="event-new" class="btn small" type="submit">追加</button></td>
            </tr>
            </tbody>
        </table>
    </div>
@endsection
