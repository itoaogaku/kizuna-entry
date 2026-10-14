@extends('layouts.app')

@section('content')
    <h1>{{ $user->displayName() }} {{ $user->isTeam() ? 'の申込データ' : '様' }}</h1>

    @if ($user->isTeam() && $user->athletes()->doesntExist())
        <div class="flash warn">まだ選手が登録されていません。<a href="{{ route('athletes.index') }}">選手登録</a>から登録してください。</div>
    @endif

    <h2>エントリー受付中の大会</h2>
    @if ($meets->isEmpty())
        <p class="muted">現在、受付中・受付予定の大会はありません。</p>
    @else
        @include('partials.meet-list', ['meets' => $meets])
    @endif
@endsection
