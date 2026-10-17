@extends('layouts.app')
@section('title', $meet->name.' 申込・入金状況')

@section('content')
    <h1>{{ $meet->name }} 申込・入金状況</h1>
    @include('admin.nav')
    <form method="GET" class="actions" style="margin-bottom:16px">
        <select name="status" onchange="this.form.submit()" style="width:auto">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <a class="btn" href="{{ route('admin.entries.export', ['meet' => $meet, 'status' => $status]) }}">Excel（エントリーシート形式）でダウンロード</a>
        <span class="muted">{{ $entries->count() }}件・選手のべ{{ $entries->sum(fn ($e) => $e->items->count()) }}人・合計{{ number_format($entries->sum('total_amount')) }}円</span>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>受付番号</th><th>所属</th><th>代表者</th><th>内容</th><th class="num">金額</th><th>状態</th><th>入金日時</th><th>支払い方法</th><th>領収書</th></tr></thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->id }}
                        @if ($entry->isFromSheet())
                            <br><span class="badge">エクセル</span>
                            @if ($entry->sheet_path)
                                <br><a href="{{ route('admin.entries.sheet', $entry) }}">元のシート</a>
                            @endif
                        @endif
                    </td>
                    <td>{{ $entry->user->displayName() }}<br><span class="muted">{{ $entry->user->typeLabel() }}{{ $entry->user->isIndividual() && $entry->user->team_name ? '・'.$entry->user->team_name : '' }}</span></td>
                    <td>{{ $entry->user->name }}<br><span class="muted">{{ $entry->user->email }} / {{ $entry->user->phone }}</span></td>
                    <td style="white-space:normal">
                        @foreach ($entry->items as $item)
                            {{ $item->athlete->fullName() }}：{{ $item->event->name }}（{{ $item->declared_record }}）<br>
                        @endforeach
                    </td>
                    <td class="num">{{ number_format($entry->total_amount) }}円</td>
                    <td><span class="badge {{ $entry->status }}">{{ $entry->statusLabel() }}</span></td>
                    <td>{{ $entry->paid_at?->format('Y/m/d H:i') }}</td>
                    <td>
                        {{ $entry->methodLabel() }}<br>
                        @if ($entry->payment_id && ! $entry->isKonbini())
                            <a href="https://dashboard.stripe.com/payments/{{ $entry->payment_id }}" target="_blank" rel="noopener">Stripeで見る</a>
                        @elseif ($entry->payment_id)
                            <span class="muted">KOMOJU {{ $entry->payment_id }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($entry->isPaid() && $entry->receipt_number)
                            <a href="{{ route('admin.entries.receipt', $entry) }}" target="_blank">No. {{ $entry->receipt_number }}</a>
                            <form method="POST" action="{{ route('admin.entries.receipt.update', $entry) }}" class="receipt-form">
                                @csrf @method('PUT')
                                <input type="text" name="receipt_name" value="{{ $entry->receipt_name }}" placeholder="{{ $entry->user->displayName() }}" aria-label="領収書の宛名">
                                <button class="btn small secondary" type="submit">宛名を変更</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">該当するエントリーはありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
