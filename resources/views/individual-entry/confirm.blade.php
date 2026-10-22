@extends('layouts.app')
@section('title', '入力内容の確認')

@section('content')
    <p class="section-eyebrow">個人申し込み</p>
    <h1>入力内容の確認</h1>
    @php $a = $draft['athlete']; @endphp
    <div class="card">
        <dl class="kv">
            <dt>大会</dt><dd>{{ $meet->name }}（{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}）</dd>
            <dt>氏名</dt><dd>{{ $a['last_name'] }} {{ $a['first_name'] }}{{ $a['kana'] ? '（'.$a['kana'].'）' : '' }}</dd>
            <dt>性別・種別</dt><dd>{{ \App\Models\Athlete::GENDERS[$a['gender']] ?? '' }}・{{ $a['category'] }}{{ $a['grade'] ? ' '.\App\Models\Athlete::formatGrade((string) $a['grade']) : '' }}</dd>
            <dt>所属陸協</dt><dd>{{ $a['association'] ?: '―' }}</dd>
            <dt>所属</dt><dd>{{ $draft['team_name'] ?: '―' }}</dd>
            <dt>連絡先</dt><dd>{{ $draft['email'] }}／{{ $draft['phone'] }}</dd>
            @if ($draft['pr'] ?? null)<dt>PRしたい一言</dt><dd>{{ $draft['pr'] }}</dd>@endif
        </dl>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>申込み種目</th><th>申請タイム</th><th class="num">参加料</th></tr></thead>
            <tbody>
            @foreach ($rows as $row)
                <tr><td>{{ $row['event']->name }}</td><td>{{ $row['record'] }}</td><td class="num">{{ number_format($row['fee']) }}円</td></tr>
            @endforeach
            <tr><th colspan="2">合計</th><th class="num">{{ number_format($total) }}円</th></tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:16px">
        <form method="POST" action="{{ route('individual-entry.store') }}">
            @csrf
            @include('partials.pay-methods', ['methods' => $methods])
            <div class="actions">
                @if ($methods !== [])
                    <button class="btn" type="submit">この内容で支払いへ進む（{{ number_format($total) }}円）</button>
                @endif
                <a class="btn secondary" href="{{ route('individual-entry.create', ['meet' => $meet->id]) }}">入力内容を直す</a>
            </div>
        </form>
    </div>
@endsection
