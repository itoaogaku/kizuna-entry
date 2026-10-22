@if (! $entry->user->isIndividual() && $entry->user->team_name)
{{ $entry->user->team_name }}
@endif
{{ $entry->user->name }} 様

@if ($entry->isMemberFree())
{{ $entry->meet->name }}へのエントリーを、アスリートキャリアセンター{{ $entry->membershipLabel() }}の無料枠で受け付けました。
会員であること（シルバー会員は今年度の利用回数）は事務局で確認します。確認できない場合は、事務局からご連絡します。
@else
{{ $entry->meet->name }}へのエントリーと参加費の入金を確認しました。
ありがとうございます。
@endif

■ 受付番号：{{ $entry->id }}
■ 大会：{{ $entry->meet->name }}（{{ $entry->meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $entry->meet->venue ? '・'.$entry->meet->venue : '' }}）
■ {{ $entry->isMemberFree() ? '受付日時' : '入金日時' }}：{{ $entry->paid_at->format('Y/m/d H:i') }}
■ 合計金額：{{ number_format($entry->total_amount) }}円（{{ $entry->methodLabel() }}{{ $entry->payment_fee ? '・手数料 '.number_format($entry->payment_fee).'円を含む' : '' }}）

■ エントリー内容
@foreach ($entry->items as $item)
・{{ $item->athlete->fullName() }}（{{ $item->athlete->genderLabel() }}）　{{ $item->event->name }}{{ $item->declared_record ? '　申請タイム '.$item->declared_record : '' }}　{{ number_format($item->fee) }}円
@endforeach

エントリー内容は次のページで確認できます。
{{ $entry->statusUrl() }}

@if ($entry->receipt_number)
領収書（PDF）をこのメールに添付しています（宛名：{{ $entry->receiptAddressee() }} 様）。
次のページからもダウンロードできます。
{{ $entry->receiptUrl() }}
@endif

――――――――――――――――
{{ config('kizuna.site.title') }}
{{ config('app.url') }}
