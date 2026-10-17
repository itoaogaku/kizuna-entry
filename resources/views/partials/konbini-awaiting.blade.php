<h1>コンビニでお支払いください</h1>
<div class="card">
    <p class="flash warn" style="margin-bottom:12px"><strong>必ず申込締切日（{{ $entry->meet->entry_closes_at->isoFormat('M月D日（ddd）') }}）までに、できる限り本日中にお支払いください。</strong></p>
    <p>払込番号を発行しました（受付番号 {{ $entry->id }}）。入金を確認した時点でエントリーが確定し、確認メールをお送りします。</p>
    <p>支払い方法と払込番号は、下のボタンの払込票と、メールで確認できます。</p>
    <a class="btn" href="{{ $entry->payment_instructions_url }}" target="_blank" rel="noopener">払込票を開く</a>
    <p class="muted">お支払い期限を過ぎると、お申し込みは自動で取り消されます。</p>
</div>
