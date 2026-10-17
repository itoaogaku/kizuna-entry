@extends('layouts.app')
@section('title', 'エントリー詳細')

@section('content')
    <h1>エントリー詳細（受付番号 {{ $entry->id }}）</h1>
    <div class="card">
        <dl class="kv">
            <dt>大会</dt><dd>{{ $entry->meet->name }}（{{ $entry->meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}）</dd>
            <dt>状態</dt><dd><span class="badge {{ $entry->status }}">{{ $entry->statusLabel() }}</span></dd>
            <dt>合計金額</dt><dd>{{ number_format($entry->total_amount) }}円（{{ $entry->methodLabel() }}）</dd>
            <dt>申込日時</dt><dd>{{ $entry->created_at->format('Y/m/d H:i') }}</dd>
            @if ($entry->paid_at)<dt>入金日時</dt><dd>{{ $entry->paid_at->format('Y/m/d H:i') }}</dd>@endif
            @if ($entry->isPaid() && $entry->receipt_number)
                <dt>領収書</dt><dd><a class="btn small" href="{{ $entry->receiptUrl() }}" target="_blank">領収書（PDF）を表示</a>
                    <span class="muted">No. {{ $entry->receipt_number }}・宛名 {{ $entry->receiptAddressee() }} 様</span></dd>
            @endif
        </dl>
        @if ($entry->status === \App\Models\Entry::STATUS_PENDING)
            <div class="actions">
                @if ($entry->isKonbini() && $entry->payment_instructions_url)
                    <a class="btn" href="{{ $entry->payment_instructions_url }}" target="_blank" rel="noopener">払込票を開く</a>
                    <span class="muted"><strong>できる限りお申し込みの当日中に</strong>コンビニでお支払いください。</span>
                @else
                <form method="POST" action="{{ route('entries.pay', $entry) }}" class="inline-form">
                    @csrf
                    <button class="btn" type="submit">支払いを続ける</button>
                </form>
                <form method="POST" action="{{ route('entries.cancel', $entry) }}" class="inline-form"
                      onsubmit="return confirm('このエントリーを取り消しますか？');">
                    @csrf
                    <button class="btn danger" type="submit">取り消す</button>
                </form>
                @endif
            </div>
        @endif
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>氏名</th><th>性別</th><th>申込み種目</th><th>申請タイム</th><th>PRしたい一言</th><th class="num">料金</th></tr></thead>
            <tbody>
            @foreach ($entry->items as $item)
                <tr>
                    <td>{{ $item->athlete->fullName() }}</td>
                    <td>{{ $item->athlete->genderLabel() }}</td>
                    <td>{{ $item->event->name }}</td>
                    <td>{{ $item->declared_record ?? '―' }}</td>
                    <td style="white-space:normal">{{ $item->pr_comment }}</td>
                    <td class="num">{{ number_format($item->fee) }}円</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
