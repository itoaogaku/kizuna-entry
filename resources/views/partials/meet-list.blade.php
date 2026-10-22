<div class="meet-list">
    @foreach ($meets as $meet)
        <div class="card">
            <h2 style="margin-top:0">{{ $meet->name }}</h2>
            <dl class="kv">
                <dt>開催日</dt><dd>{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}</dd>
                @if ($meet->venue)<dt>会場</dt><dd>{{ $meet->venue }}</dd>@endif
                <dt>受付期間</dt><dd>{{ $meet->entry_opens_at->format('Y/m/d H:i') }} 〜 {{ $meet->entry_closes_at->format('Y/m/d H:i') }}</dd>
            </dl>
            @if ($meet->notes)
                <p class="muted" style="white-space:pre-line">{{ $meet->notes }}</p>
            @endif
            @if (auth()->check() && ($actions ?? true))
                <div class="actions">
                    @if ($meet->isAcceptingEntries())
                        <a class="btn" href="{{ route('entries.create', $meet) }}">エントリーする</a>
                    @elseif (now()->lt($meet->entry_opens_at))
                        <span class="badge">受付開始前</span>
                    @else
                        <span class="badge">受付終了</span>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
