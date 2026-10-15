@extends('layouts.app')
@section('title', 'お支払いの受付')

@section('content')
    @if ($entry->isPaid())
        <h1>エントリーが完了しました</h1>
        <div class="card">
            <p>{{ $entry->meet->name }}へのエントリーと入金を確認しました（受付番号 {{ $entry->id }}）。</p>
            <p>確認メールを {{ auth()->user()->email }} にお送りしました。</p>
        </div>
    @elseif ($entry->isKonbini() && $entry->payment_instructions_url)
        <h1>コンビニでお支払いください</h1>
        <div class="card">
            <p>払込番号を発行しました（受付番号 {{ $entry->id }}）。<strong>お支払い期限までにコンビニで支払うと、エントリーが確定します。</strong></p>
            <p>支払い方法と払込番号は、下のボタンの払込票と、KOMOJUから届くメールで確認できます。</p>
            <a class="btn" href="{{ $entry->payment_instructions_url }}" target="_blank" rel="noopener">払込票を開く</a>
            <p class="muted">期限までにお支払いがない場合、このエントリーは自動で取り消されます。</p>
        </div>
    @else
        <h1>お支払いを確認しています</h1>
        <div class="card">
            <p>決済会社からの入金通知を待っています。数分たってもこの表示が変わらない場合は、エントリー履歴で状態を確認してください。</p>
        </div>
    @endif
    <div class="actions">
        <a class="btn" href="{{ route('entries.show', $entry) }}">エントリー内容を見る</a>
        <a class="btn secondary" href="{{ route('home') }}">トップへ</a>
    </div>
@endsection
