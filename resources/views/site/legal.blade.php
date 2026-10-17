@extends('layouts.app')
@section('title', '特定商取引法に基づく表記')

@section('content')
    <p class="section-eyebrow">LEGAL</p>
    <h1>特定商取引法に基づく表記</h1>
    @php($receipt = config('kizuna.receipt'))
    @php($contact = config('kizuna.contact'))
    <div class="table-wrap legal">
        <table>
            <tr><th>販売事業者</th><td>{{ $receipt['issuer'] }}</td></tr>
            <tr><th>運営責任者</th><td>{{ config('kizuna.legal.manager') }}</td></tr>
            <tr><th>所在地</th><td>{{ $receipt['address'] }}</td></tr>
            <tr><th>電話番号</th><td>{{ $receipt['phone'] }}（大会に関するお問い合わせは {{ $contact['phone'] }}）</td></tr>
            <tr><th>メールアドレス</th><td><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></td></tr>
            <tr><th>販売価格</th><td>各大会の大会要項・エントリー画面に記載の参加料（税込）。種目・種別ごとに定めます。</td></tr>
            <tr><th>商品代金以外の必要料金</th><td>コンビニ払いの場合の手数料（エントリー画面に表示）、インターネット接続にかかる通信料</td></tr>
            <tr><th>支払方法</th><td>クレジットカード（Stripe）、コンビニ払い（KOMOJU）</td></tr>
            <tr><th>支払時期</th><td>クレジットカードはお申込み時。コンビニ払いはお申込み時に表示される支払期限までにお支払いください（申込締切日までのお支払いをお願いしています）。</td></tr>
            <tr><th>役務の提供時期</th><td>各大会の開催日（大会要項に記載）</td></tr>
            <tr><th>キャンセル・返金</th><td>お支払い後のキャンセル・返金はお受けしていません。{{ config('kizuna.legal.cancellation') }}</td></tr>
            <tr><th>動作環境</th><td>最新版の主要ブラウザ（Chrome・Safari・Edge・Firefox）</td></tr>
        </table>
    </div>
@endsection
