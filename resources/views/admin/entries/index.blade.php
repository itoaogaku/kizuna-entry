@extends('layouts.app')
@section('title', $meet->name.' エントリー一覧')

@section('content')
    <h1>{{ $meet->name }} エントリー一覧</h1>
    @include('admin.nav')
    <form method="GET" class="actions" style="margin-bottom:16px">
        <select name="status" onchange="this.form.submit()" style="width:auto">
            @foreach (['paid' => '入金済み', 'pending' => '決済待ち', 'cancelled' => '取消', 'all' => 'すべて'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <a class="btn" href="{{ route('admin.entries.export', ['meet' => $meet, 'status' => $status]) }}">CSVダウンロード（Excel用）</a>
        <span class="muted">{{ $entries->count() }}件・選手のべ{{ $entries->sum(fn ($e) => $e->items->count()) }}人・合計{{ number_format($entries->sum('total_amount')) }}円</span>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>受付番号</th><th>所属</th><th>代表者</th><th>内容</th><th class="num">金額</th><th>状態</th><th>入金日時</th><th>Stripe</th></tr></thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->id }}</td>
                    <td>{{ $entry->user->displayName() }}<br><span class="muted">{{ $entry->user->typeLabel() }}{{ $entry->user->isIndividual() && $entry->user->team_name ? '・'.$entry->user->team_name : '' }}</span></td>
                    <td>{{ $entry->user->name }}<br><span class="muted">{{ $entry->user->email }} / {{ $entry->user->phone }}</span></td>
                    <td style="white-space:normal">
                        @foreach ($entry->items as $item)
                            {{ $item->athlete->fullName() }}：{{ $item->event->name }}{{ $item->declared_record ? '（'.$item->declared_record.'）' : '' }}<br>
                        @endforeach
                    </td>
                    <td class="num">{{ number_format($entry->total_amount) }}円</td>
                    <td><span class="badge {{ $entry->status }}">{{ $entry->statusLabel() }}</span></td>
                    <td>{{ $entry->paid_at?->format('Y/m/d H:i') }}</td>
                    <td>
                        @if ($entry->stripe_payment_intent_id)
                            <a href="https://dashboard.stripe.com/payments/{{ $entry->stripe_payment_intent_id }}" target="_blank" rel="noopener">決済を見る</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">該当するエントリーはありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
