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
                        <strong>{{ $label }}</strong><br>
                        @if ($value === \App\Models\Entry::METHOD_CARD)
                            <span class="muted">その場で決済が完了し、エントリーが確定します。2回目以降はカード情報の入力を省けます（Link）。</span>
                        @else
                            <span class="muted">決済画面でコンビニを選ぶと払込番号が発行されます。<strong>できる限りお申し込みの当日中に</strong>コンビニでお支払いください。入金を確認した時点でエントリーが確定します。</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>
    <p class="muted">「支払いへ進む」を押すと決済画面に移動します。入金を確認した時点でエントリーが確定し、確認メールをお送りします。決済画面で支払わずに戻った場合、このお申し込みは取り消されます。</p>
@endif
