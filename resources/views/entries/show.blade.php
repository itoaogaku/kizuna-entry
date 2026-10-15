@extends('layouts.app')
@section('title', 'エントリー詳細')

@section('content')
    <h1>エントリー詳細（受付番号 {{ $entry->id }}）</h1>
    <div class="card">
        <dl class="kv">
            <dt>大会</dt><dd>{{ $entry->meet->name }}（{{ $entry->meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}）</dd>
            <dt>状態</dt><dd><span class="badge {{ $entry->status }}">{{ $entry->statusLabel() }}</span></dd>
            <dt>合計金額</dt><dd>{{ number_format($entry->total_amount) }}円</dd>
            <dt>申込日時</dt><dd>{{ $entry->created_at->format('Y/m/d H:i') }}</dd>
            @if ($entry->paid_at)<dt>入金日時</dt><dd>{{ $entry->paid_at->format('Y/m/d H:i') }}</dd>@endif
        </dl>
        @if ($entry->status === \App\Models\Entry::STATUS_PENDING)
            <div class="actions">
                <form method="POST" action="{{ route('entries.pay', $entry) }}" class="inline-form">
                    @csrf
                    <button class="btn" type="submit">支払いを続ける</button>
                </form>
                <form method="POST" action="{{ route('entries.cancel', $entry) }}" class="inline-form"
                      onsubmit="return confirm('このエントリーを取り消しますか？');">
                    @csrf
                    <button class="btn danger" type="submit">取り消す</button>
                </form>
            </div>
        @endif
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>氏名</th><th>性別</th><th>申込み種目</th><th>申請タイム</th><th>自己ベスト</th><th>PRしたい一言</th><th class="num">料金</th></tr></thead>
            <tbody>
            @foreach ($entry->items as $item)
                <tr>
                    <td>{{ $item->athlete->fullName() }}</td>
                    <td>{{ $item->athlete->genderLabel() }}</td>
                    <td>{{ $item->event->name }}</td>
                    <td>{{ $item->declared_record ?? '―' }}</td>
                    <td>{{ $item->personal_best ?? '―' }}</td>
                    <td style="white-space:normal">{{ $item->pr_comment }}</td>
                    <td class="num">{{ number_format($item->fee) }}円</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
