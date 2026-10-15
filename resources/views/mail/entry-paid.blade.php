@if ($entry->user->isTeam())
{{ $entry->user->team_name }}
@endif
{{ $entry->user->name }} 様

{{ $entry->meet->name }}へのエントリーと参加費の入金を確認しました。
ありがとうございます。

■ 受付番号：{{ $entry->id }}
■ 大会：{{ $entry->meet->name }}（{{ $entry->meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $entry->meet->venue ? '・'.$entry->meet->venue : '' }}）
■ 入金日時：{{ $entry->paid_at->format('Y/m/d H:i') }}
■ 合計金額：{{ number_format($entry->total_amount) }}円（{{ $entry->methodLabel() }}）

■ エントリー内容
@foreach ($entry->items as $item)
・{{ $item->athlete->fullName() }}（{{ $item->athlete->genderLabel() }}）　{{ $item->event->name }}{{ $item->declared_record ? '　申請タイム '.$item->declared_record : '' }}　{{ number_format($item->fee) }}円
@endforeach

エントリー内容はマイページからも確認できます。
{{ route('entries.show', $entry) }}

@unless ($entry->isKonbini())
お支払いの領収書は、Stripeから別途メールでお送りしています。
@endunless

――――――――――――――――
{{ config('app.name') }}
{{ config('app.url') }}
