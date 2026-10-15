@extends('layouts.app')
@section('title', 'エントリー内容の確認')

@section('content')
    <h1>エントリー内容の確認</h1>
    <p>{{ $meet->name }}（{{ $meet->held_on->isoFormat('YYYY年M月D日（ddd）') }}）</p>

    <div class="table-wrap">
        <table>
            <thead><tr><th>氏名</th><th>性別</th><th>申込み種目</th><th>申請タイム</th><th>PRしたい一言</th><th class="num">料金</th></tr></thead>
            <tbody>
            @foreach ($selections as $s)
                <tr>
                    <td>{{ $s['athlete']->fullName() }}</td>
                    <td>{{ $s['athlete']->genderLabel() }}</td>
                    <td>{{ $s['event']->name }}</td>
                    <td>{{ $s['record'] }}</td>
                    <td style="white-space:normal">{{ $s['pr'] ?? '' }}</td>
                    <td class="num">{{ number_format($s['event']->fee) }}円</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="5">合計（{{ $selections->count() }}件）</th>
                <th class="num">{{ number_format($total) }}円</th>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:16px">
        <form method="POST" action="{{ route('entries.store', $meet) }}">
            @csrf
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
                                        <span class="muted">決済画面でコンビニを選ぶと払込番号が発行されます。期限までにコンビニで支払うと、エントリーが確定します。</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <p class="muted">「支払いへ進む」を押すと決済画面に移動します。入金を確認した時点でエントリーが確定し、確認メールをお送りします。決済画面で支払わずに戻った場合、このエントリーは取り消されます。</p>
            @endif
            <div class="actions">
                @if ($methods !== [])
                    <button class="btn" type="submit">支払いへ進む（{{ number_format($total) }}円）</button>
                @endif
                <a class="btn secondary" href="{{ route('entries.create', $meet) }}">選択画面に戻る</a>
            </div>
        </form>
    </div>
@endsection
