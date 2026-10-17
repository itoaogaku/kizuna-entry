@extends('layouts.app')
@section('title', 'エントリー内容の確認')

@section('content')
    <h1>エントリー内容の確認</h1>
    <p>{{ $meet->name }}（{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}）</p>

    <div class="table-wrap">
        <table>
            <thead><tr><th>氏名</th><th>性別</th><th>申込み種目</th><th>申請タイム</th><th>PRしたい一言</th><th class="num">料金</th></tr></thead>
            <tbody>
            @foreach ($selections as $s)
                <tr>
                    <td>{{ $s['athlete']->fullName() }}</td>
                    <td>{{ $s['athlete']->genderLabel() }}</td>
                    <td>{{ $s['event']->name }}</td>
                    <td>{{ $s['record'] }}</td>
                    <td style="white-space:normal">{{ $s['pr'] ?? '' }}</td>
                    <td class="num">{{ number_format($s['event']->fee) }}円</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="5">合計（{{ $selections->count() }}件）</th>
                <th class="num">{{ number_format($total) }}円</th>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:16px">
        <form method="POST" action="{{ route('entries.store', $meet) }}">
            @csrf
            @include('partials.pay-methods', ['methods' => $methods])
            <div class="actions">
                @if ($methods !== [])
                    <button class="btn" type="submit">支払いへ進む（{{ number_format($total) }}円）</button>
                @endif
                <a class="btn secondary" href="{{ route('entries.create', $meet) }}">選択画面に戻る</a>
            </div>
        </form>
    </div>
@endsection
