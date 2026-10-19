{{ $entry->user->displayName() }}
{{ $entry->user->name }} 様

{{ $entry->meet->name }}へのお申し込みありがとうございます。
コンビニ払いの払込番号を発行しました。

必ず申込締切日（{{ $entry->meet->entry_closes_at->isoFormat('M月D日（ddd）') }}）までに、
できる限り本日中（お申し込みの当日中）にコンビニでお支払いください。
入金を確認した時点でエントリーが確定し、確定のメールをお送りします。

■ 受付番号：{{ $entry->id }}
■ 合計金額：{{ number_format($entry->total_amount) }}円{{ $entry->payment_fee ? '（コンビニ払い手数料 '.number_format($entry->payment_fee).'円を含む）' : '' }}
■ 払込票（支払い方法・払込番号）：
{{ $entry->payment_instructions_url }}

■ お申し込み内容
@foreach ($entry->items as $item)
・{{ $item->athlete->fullName() }}　{{ $item->event->name }}　申請タイム {{ $item->declared_record }}　{{ number_format($item->fee) }}円
@endforeach

お申し込みの状態は、次のページで確認できます。
{{ $entry->statusUrl() }}

※お支払い期限を過ぎると、お申し込みは自動で取り消されます。

――――――――――――――――
{{ config('kizuna.site.title') }}
{{ config('app.url') }}
