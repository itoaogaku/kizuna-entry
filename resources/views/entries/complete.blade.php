@extends('layouts.app')
@section('title', 'お支払いの受付')

@section('content')
    @if ($entry->isPaid())
        <h1>エントリーが完了しました</h1>
        <div class="card">
            <p>{{ $entry->meet->name }}へのエントリーと入金を確認しました（受付番号 {{ $entry->id }}）。</p>
            <p>確認メールを {{ auth()->user()->email }} にお送りしました。</p>
        </div>
    @else
        <h1>お支払いを確認しています</h1>
        <div class="card">
            <p>決済会社からの入金通知を待っています。数分たってもこの表示が変わらない場合は、エントリー履歴で状態を確認してください。</p>
            <p class="muted">コンビニ払いを選んだ場合は、お支払い後に入金済みになります。</p>
        </div>
    @endif
    <div class="actions">
        <a class="btn" href="{{ route('entries.show', $entry) }}">エントリー内容を見る</a>
        <a class="btn secondary" href="{{ route('home') }}">トップへ</a>
    </div>
@endsection
