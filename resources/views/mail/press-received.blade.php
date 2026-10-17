{{ $press->media_name }}
{{ $press->contact_name }} 様

{{ $press->meet->name }}の取材・撮影のお申込みを受け付けました。
当日の受付場所・撮影エリアなどの詳細は、運営から改めてご連絡します。

■ 大会：{{ $press->meet->name }}（{{ $press->meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $press->meet->venue ? '・'.$press->meet->venue : '' }}）
■ 内容：{{ $press->coverage }}
■ 来場人数：{{ $press->people }}名
■ 電話番号：{{ $press->phone }}
@if ($press->purpose)
■ 掲載・使用予定：{{ $press->purpose }}
@endif
@if ($press->notes)
■ 備考：{{ $press->notes }}
@endif

――――――――――――――――
{{ config('kizuna.organization.name') }}
{{ config('kizuna.organization.email') }}
