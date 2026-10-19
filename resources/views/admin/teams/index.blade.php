@extends('layouts.admin')
@section('title', '登録団体・個人')

@section('content')
    <h1>登録団体・個人</h1>
    <div class="table-wrap">
        <table>
            <thead><tr><th>区分</th><th>団体名・所属</th><th>代表者・氏名</th><th>メール</th><th>電話</th><th class="num">登録選手</th><th>登録日</th></tr></thead>
            <tbody>
            @foreach ($teams as $team)
                <tr>
                    <td>{{ $team->typeLabel() }}</td>
                    <td>{{ $team->team_name }} @if ($team->is_admin)<span class="badge">運営</span>@endif</td>
                    <td>{{ $team->name }}</td>
                    <td>{{ $team->email }}</td>
                    <td>{{ $team->phone }}</td>
                    <td class="num">{{ $team->athletes_count }}</td>
                    <td>{{ $team->created_at->format('Y/m/d') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
