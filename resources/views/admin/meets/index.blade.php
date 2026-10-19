@extends('layouts.admin')
@section('title', '管理画面')

@section('content')
    <h1>管理画面：大会一覧</h1>
    <div class="actions" style="margin-bottom:16px">
        <a class="btn" href="{{ route('admin.meets.create') }}">新規大会登録</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>開催日</th><th>大会名</th><th>受付期間</th><th>公開</th><th class="num">入金済</th><th class="num">入金額</th><th></th></tr></thead>
            <tbody>
            @forelse ($meets as $meet)
                <tr>
                    <td>{{ $meet->held_on->format('Y/m/d') }}</td>
                    <td><a href="{{ route('admin.meets.show', $meet) }}">{{ $meet->name }}</a></td>
                    <td>{{ $meet->entry_opens_at->format('m/d H:i') }}〜{{ $meet->entry_closes_at->format('m/d H:i') }}</td>
                    <td>{{ $meet->is_published ? '公開' : '非公開' }}</td>
                    <td class="num">{{ $meet->paid_count }}件</td>
                    <td class="num">{{ number_format($meet->paid_total ?? 0) }}円</td>
                    <td><a href="{{ route('admin.roster.index', $meet) }}">申込一覧</a>　<a href="{{ route('admin.entries.index', $meet) }}">入金状況</a>　<a href="{{ route('admin.documents.edit', $meet) }}">大会資料（PDF）</a></td>
                </tr>
            @empty
                <tr><td colspan="7">大会がありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
