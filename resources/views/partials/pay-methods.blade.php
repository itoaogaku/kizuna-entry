@if ($methods === [])
    <p class="flash err">現在、お支払いを受け付けられません。運営にお問い合わせください。</p>
@else
    <div class="field">
        <label>お支払い方法</label>
        <div class="pay-methods">
            @foreach ($methods as $value => $label)
                <label class="pay-method">
                    <input type="radio" name="payment_method" value="{{ $value }}" @checked(old('payment_method', array_key_first($methods)) === $value)>
                    <span>
                        <strong>{{ $label }}</strong>@if ($value === \App\Models\Entry::METHOD_KONBINI && ($konbiniFee = \App\Services\EntryService::paymentFee($value)) > 0)<span class="muted">（手数料 {{ number_format($konbiniFee) }}円・合計 {{ number_format(($total ?? 0) + $konbiniFee) }}円）</span>@endif<br>
                        @if ($value === \App\Models\Entry::METHOD_CARD)
                            <span class="muted">その場で決済が完了し、エントリーが確定します。2回目以降はカード情報の入力を省けます（Link）。</span>
                        @else
                            <span class="muted">決済画面でコンビニを選ぶと払込番号が発行されます。<strong>必ず申込締切日{{ isset($meet) ? '（'.$meet->entry_closes_at->isoFormat('M月D日').'）' : '' }}までに、できる限りお申し込みの当日中に</strong>コンビニでお支払いください。入金を確認した時点でエントリーが確定します。</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="field">
        <label for="receipt_name">領収書の宛名 <span class="hint">（入金が完了すると、領収書（PDF）を自動で発行してメールに添付します）</span></label>
        <input id="receipt_name" type="text" name="receipt_name" value="{{ old('receipt_name', $receiptDefault ?? '') }}" maxlength="100" placeholder="例：絆陸上クラブ">
    </div>
    <div class="field terms">
        <label class="inline"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
            参加者全員が{{ \App\Services\GradeAdvance::fiscalYear() }}年度の日本陸上競技連盟の競技者登録を済ませています。また、申込み後は理由の如何に関わらず参加料がかかることに同意します。</label>
    </div>
    <p class="muted">「支払いへ進む」を押すと決済画面に移動します。入金を確認した時点でエントリーが確定し、確認メールをお送りします。決済画面で支払わずに戻った場合、このお申し込みは取り消されます。</p>
@endif
