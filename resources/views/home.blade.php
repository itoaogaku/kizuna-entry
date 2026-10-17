@extends('layouts.app')

@section('content')
    <h1>{{ $user->displayName() }} {{ $user->isTeam() ? 'の申込データ' : '様' }}</h1>

    @if (\App\Services\GradeAdvance::isDue($user))
        <div class="flash warn">新年度になりました。<a href="{{ route('athletes.advance') }}">年度更新</a>で選手の学年を上げてください（卒業した選手は対象外にできます）。</div>
    @endif

    @if ($user->isTeam() && $user->athletes()->doesntExist())
        <div class="flash warn">まだ選手が登録されていません。<a href="{{ route('athletes.index') }}">選手登録</a>から登録してください。</div>
    @endif

    <p class="muted">エクセル（エントリーシート）で申し込みたい場合は、<a href="{{ route('sheet.create') }}">エクセルを添付して申し込む</a>こともできます。</p>

    <h2>エントリー受付中の大会</h2>
    @if ($meets->isEmpty())
        <p class="muted">現在、受付中・受付予定の大会はありません。</p>
    @else
        @include('partials.meet-list', ['meets' => $meets])
    @endif
@endsection
