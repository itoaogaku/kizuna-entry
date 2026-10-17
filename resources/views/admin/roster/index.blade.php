@extends('layouts.app')
@section('title', $meet->name.' 申込一覧')

@section('content')
    <h1>{{ $meet->name }} 申込一覧（全団体・個人）</h1>
    @include('admin.nav')

    <form method="GET" class="filters">
        <label>種目
            <select name="event" onchange="this.form.submit()">
                <option value="">すべての種目</option>
                @foreach ($events as $event)
                    <option value="{{ $event->id }}" @selected($eventId === $event->id)>{{ $event->name }}</option>
                @endforeach
            </select>
        </label>
        <label>団体・個人
            <select name="team" onchange="this.form.submit()">
                <option value="">すべて</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected($teamId === $team->id)>{{ $team->displayName() }}（{{ $team->typeLabel() }}）</option>
                @endforeach
            </select>
        </label>
        <label>状態
            <select name="status" onchange="this.form.submit()">
                @foreach ($statusFilters as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <noscript><button class="btn small" type="submit">表示</button></noscript>
        <a class="btn secondary" href="{{ route('admin.entries.export', ['meet' => $meet, 'status' => $status]) }}">Excelでダウンロード</a>
    </form>

    <p class="muted">
        全{{ $total }}人（のべ）・並び順：申請タイムの速い順 → 同じ申請タイムは自己ベストの速い順。
        @if ($tied->isNotEmpty())
            <span class="tie-note">黄色の行</span>は申請タイムが同じ選手です。自己ベストを入力すると順番が決まります。
        @endif
    </p>

    @forelse ($groups as $eventItems)
        @php $event = $eventItems->first()->event; @endphp
        <h2>{{ $event->name }} <span class="muted">（{{ $eventItems->count() }}人{{ $event->capacity ? '／定員'.$event->capacity : '' }}）</span></h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th class="num">順</th><th>申請タイム</th><th>自己ベスト</th><th>氏名</th><th>フリガナ</th>
                    <th>団体名・所属</th><th>種別・学年</th><th>部門</th><th>性別</th><th>所属陸協</th><th>PRしたい一言</th><th>状態</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($eventItems as $item)
                    <tr class="{{ $tied->contains($item->id) ? 'tie' : '' }}">
                        <td class="num">{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->declared_record ?? '―' }}</strong></td>
                        <td>
                            <form method="POST" action="{{ route('admin.roster.best', $item) }}" class="best-form">
                                @csrf @method('PUT')
                                <input type="text" name="personal_best" value="{{ $item->personal_best }}" placeholder="未入力" aria-label="{{ $item->athlete->fullName() }}の自己ベスト">
                                <button class="btn small secondary" type="submit">保存</button>
                            </form>
                        </td>
                        <td>{{ $item->athlete->fullName() }}</td>
                        <td>{{ $item->athlete->kana }}</td>
                        <td>{{ $item->entry->user->isIndividual() ? ($item->entry->user->team_name ?: '個人') : $item->entry->user->displayName() }}
                            @if ($item->entry->isFromSheet())<span class="badge" title="エクセル添付で申込み">Excel</span>@endif
                        </td>
                        <td>{{ $item->athlete->category }}{{ $item->athlete->grade ? ' '.$item->athlete->gradeLabel() : '' }}</td>
                        <td>{{ $item->athlete->divisionLabel() }}</td>
                        <td>{{ $item->athlete->genderLabel() }}</td>
                        <td>{{ $item->athlete->association }}</td>
                        <td style="white-space:normal;min-width:160px">{{ $item->pr_comment }}</td>
                        <td><span class="badge {{ $item->entry->status }}">{{ $item->entry->statusLabel() }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="card"><p>該当する申込はありません。</p></div>
    @endforelse
@endsection
