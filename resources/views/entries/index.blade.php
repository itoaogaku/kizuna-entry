@extends('layouts.app')
@section('title', 'エントリー履歴')

@section('content')
    <h1>エントリー履歴</h1>
    @if ($entries->isEmpty())
        <div class="card"><p>まだエントリーはありません。</p></div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>受付番号</th><th>大会</th><th class="num">件数</th><th class="num">金額</th><th>状態</th><th>申込日時</th><th></th></tr></thead>
                <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $entry->id }}</td>
                        <td>{{ $entry->meet->name }}</td>
                        <td class="num">{{ $entry->items->count() }}</td>
                        <td class="num">{{ number_format($entry->total_amount) }}円</td>
                        <td><span class="badge {{ $entry->status }}">{{ $entry->statusLabel() }}</span></td>
                        <td>{{ $entry->created_at->format('Y/m/d H:i') }}</td>
                        <td><a href="{{ route('entries.show', $entry) }}">詳細</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
