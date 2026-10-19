@extends('layouts.admin')
@section('title', $meet->name.' 取材・撮影のお申込み')

@section('content')
    <h1>{{ $meet->name }} 取材・撮影のお申込み</h1>
    <p><a href="{{ route('admin.meets.show', $meet) }}">← 大会の画面に戻る</a></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>受付日時</th><th>媒体名・会社名</th><th>担当者</th><th>連絡先</th><th>内容</th><th class="num">人数</th><th>掲載・使用予定／備考</th></tr></thead>
            <tbody>
            @forelse ($requests as $r)
                <tr>
                    <td>{{ $r->created_at->format('n/j H:i') }}</td>
                    <td>{{ $r->media_name }}</td>
                    <td>{{ $r->contact_name }}</td>
                    <td>{{ $r->email }}<br>{{ $r->phone }}</td>
                    <td>{{ $r->coverage }}</td>
                    <td class="num">{{ $r->people }}</td>
                    <td style="white-space:normal;min-width:200px">{{ $r->purpose }}@if ($r->notes)<br><span class="muted">{{ $r->notes }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7">まだお申込みはありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
