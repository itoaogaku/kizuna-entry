<div class="doc-head">
    <p class="section-eyebrow">{{ $eyebrow }}</p>
    <h1>{{ $meet->name }} {{ $title }}
        @if (! $meet->isProgramFinal())<span class="stage stage-open">暫定版</span>@endif
    </h1>
    <p class="muted">{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}{{ $meet->venue ? '・'.$meet->venue : '' }}
        @if ($meet->start_list_published_at)・{{ $meet->start_list_published_at->format('n/j H:i') }} 更新@endif</p>
    @unless ($meet->hasPublicStartList())
        <div class="flash warn">未公開です（運営だけが見ています）。</div>
    @endunless
    @if ($meet->start_list_status === 'provisional')
        <div class="flash warn">暫定版です。組変更希望がある場合は、組変更希望用紙を大会の5日前までにメールでご提出ください。すべての希望通りに変更できるとは限りません。</div>
    @endif
</div>
