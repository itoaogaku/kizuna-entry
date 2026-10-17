@extends('layouts.app')
@section('title', '読み込んだ内容の確認')

@section('content')
    <h1>読み込んだ内容の確認</h1>
    <p>{{ $meet->name }}（{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}）・添付ファイル：{{ $draft['filename'] }}</p>
    <p>申込者：{{ auth()->user()?->displayName() ?? (($draft['team_name'] ?: '個人').'・'.$draft['name']) }}</p>

    @unless ($valid)
        <div class="flash err">
            赤い行に直すところがあります。エクセルを直して、<a href="{{ route('sheet.create') }}">もう一度添付</a>してください。
        </div>
    @endunless

    <div class="table-wrap">
        <table>
            <thead>
            <tr><th class="num">行</th><th>氏名</th><th>フリガナ</th><th>性別</th><th>種別・学年</th><th>所属陸協</th><th>申込み種目</th><th>申請タイム</th><th>自己ベスト</th><th class="num">料金</th></tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                @php $a = $row['athlete']; @endphp
                <tr class="{{ $row['errors'] ? 'row-error' : '' }}">
                    <td class="num">{{ $row['line'] }}</td>
                    <td>{{ $a['last_name'] }} {{ $a['first_name'] }}</td>
                    <td>{{ $a['kana'] }}</td>
                    <td>{{ \App\Models\Athlete::GENDERS[$a['gender']] ?? $a['gender'] }}</td>
                    <td>{{ $a['category'] }}{{ $a['grade'] ? ' '.\App\Models\Athlete::formatGrade((string) $a['grade']) : '' }}</td>
                    <td>{{ $a['association'] }}</td>
                    <td>{{ $row['event']?->name ?? $row['eventName'] }}</td>
                    <td>{{ $row['record'] }}</td>
                    <td>{{ $row['best'] }}</td>
                    <td class="num">{{ $row['event'] ? number_format($row['fee']).'円' : '' }}</td>
                </tr>
                @if ($row['errors'])
                    <tr class="row-error"><td></td><td colspan="9" style="white-space:normal">{{ implode(' ', $row['errors']) }}</td></tr>
                @endif
            @endforeach
            <tr><th colspan="9">合計（{{ count($rows) }}件）</th><th class="num">{{ number_format($total) }}円</th></tr>
            </tbody>
        </table>
    </div>

    @if ($valid)
        <div class="card" style="margin-top:16px">
            <form method="POST" action="{{ route('sheet.store') }}">
                @csrf
                @include('partials.pay-methods', ['methods' => $methods])
                <div class="actions">
                    @if ($methods !== [])
                        <button class="btn" type="submit">この内容で支払いへ進む（{{ number_format($total) }}円）</button>
                    @endif
                    <a class="btn secondary" href="{{ route('sheet.create') }}">添付し直す</a>
                </div>
            </form>
        </div>
    @endif
@endsection
