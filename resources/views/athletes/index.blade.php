@extends('layouts.app')
@section('title', '選手登録')

@section('content')
    @if (auth()->user()->isTeam())
        <h1>選手登録（{{ auth()->user()->team_name }}）</h1>
        <p class="muted">ここに登録した選手が、エントリー画面の一覧に表示されます。卒業・引退した選手は「エントリー対象外」にすると一覧から外れます。</p>
        <div class="actions" style="margin-bottom:16px">
            <a class="btn" href="{{ route('athletes.create') }}">選手を追加</a>
            <a class="btn secondary" href="{{ route('athletes.import') }}">Excel（CSV）でまとめて登録</a>
        </div>
    @else
        <h1>登録情報</h1>
        <p class="muted">エントリーに使う選手情報です。内容が変わったときは「編集」から更新してください。</p>
    @endif

    @if ($athletes->isEmpty())
        <div class="card"><p>まだ選手が登録されていません。</p></div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>氏名</th><th>フリガナ</th><th>性別</th><th>生年月日</th><th>区分</th><th>陸連登録番号</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($athletes as $athlete)
                    <tr class="{{ $athlete->is_active ? '' : 'inactive' }}">
                        <td>{{ $athlete->fullName() }} @unless ($athlete->is_active)<span class="badge">対象外</span>@endunless</td>
                        <td>{{ $athlete->fullKana() }}</td>
                        <td>{{ $athlete->genderLabel() }}</td>
                        <td>{{ $athlete->birthdate->format('Y/m/d') }}</td>
                        <td>{{ $athlete->category }}</td>
                        <td>{{ $athlete->jaaf_id }}</td>
                        <td><a href="{{ route('athletes.edit', $athlete) }}">編集</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
