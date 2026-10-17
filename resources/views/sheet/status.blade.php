@extends('layouts.app')
@section('title', 'お申し込みの状態')

@section('content')
    @if ($entry->isPaid())
        <h1>エントリーが完了しました</h1>
        <div class="card"><p>{{ $entry->meet->name }}へのエントリーと入金を確認しました（受付番号 {{ $entry->id }}）。確認メールを {{ $entry->user->email }} にお送りしました。領収書（PDF）も添付しています。</p>
            @if ($entry->receipt_number)<a class="btn secondary" href="{{ $entry->receiptUrl() }}" target="_blank">領収書（PDF）を表示</a>@endif</div>
    @elseif ($entry->status === \App\Models\Entry::STATUS_CANCELLED)
        <h1>このお申し込みは取り消されています</h1>
        <div class="card">
            <p>お支払いがなかったため、取り消されました。もう一度お申し込みください。</p>
            <a class="btn" href="{{ route('sheet.create') }}">エクセルを添付して申し込む</a>
        </div>
    @elseif ($entry->isKonbini() && $entry->payment_instructions_url)
        @include('partials.konbini-awaiting', ['entry' => $entry])
    @else
        <h1>お支払いを確認しています</h1>
        <div class="card">
            <p>入金の確認ができ次第、エントリーが確定します。お支払いが済んでいない場合は、下のボタンから続けてください。</p>
            <a class="btn" href="{{ URL::signedRoute('sheet.pay', $entry) }}">支払いを続ける</a>
        </div>
    @endif

    <h2>お申し込み内容（受付番号 {{ $entry->id }}）</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>氏名</th><th>申込み種目</th><th>申請タイム</th><th class="num">料金</th></tr></thead>
            <tbody>
            @foreach ($entry->items as $item)
                <tr><td>{{ $item->athlete->fullName() }}</td><td>{{ $item->event->name }}</td><td>{{ $item->declared_record }}</td><td class="num">{{ number_format($item->fee) }}円</td></tr>
            @endforeach
            <tr><th colspan="3">合計（{{ $entry->methodLabel() }}）</th><th class="num">{{ number_format($entry->total_amount) }}円</th></tr>
            </tbody>
        </table>
    </div>
    <p class="muted">このページのURLは、お申し込みの状態を確認するためのものです。他の人には知らせないでください。</p>
@endsection
