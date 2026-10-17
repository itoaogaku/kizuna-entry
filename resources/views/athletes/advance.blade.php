@extends('layouts.app')
@section('title', '年度更新')

@section('content')
    <h1>年度更新（{{ $fiscalYear }}年度）</h1>
    <p class="muted">学年のある選手の学年を1つ上げます。卒業学年の選手は、対象外にするか、進学して続けるかを選んでください。学年が空欄の選手（一般など）は変わりません。</p>

    @if ($doneThisYear)
        <div class="flash warn">今年度の年度更新はすでに行っています。もう一度行うと、学年がさらに1つ上がります。</div>
    @endif

    @if ($rows->isEmpty())
        <div class="card"><p>学年が登録されている選手がいません。</p></div>
    @else
        <form method="POST" action="{{ route('athletes.advance') }}">
            @csrf
            <div class="table-wrap">
                <table>
                    <thead><tr><th>氏名</th><th>今</th><th>更新後</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $row)
                        @php $a = $row['athlete']; @endphp
                        <tr class="{{ $row['graduating'] ? 'tie' : '' }}">
                            <td>{{ $a->fullName() }}<br><span class="muted">{{ $a->kana }}</span></td>
                            <td>{{ $a->category }} {{ $a->gradeLabel() }}</td>
                            <td>
                                @if ($row['graduating'])
                                    <select name="choices[{{ $a->id }}]" aria-label="{{ $a->fullName() }}の扱い">
                                        <option value="retire">卒業：エントリー対象外にする</option>
                                        <option value="promote">進学して続ける（{{ $row['promoteLabel'] }}）</option>
                                        <option value="keep">変更しない</option>
                                    </select>
                                @else
                                    {{ $row['after'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($doneThisYear)
                <div class="field" style="margin-top:12px">
                    <label class="inline"><input type="checkbox" name="again" value="1"> 年度更新をもう一度行う</label>
                </div>
            @endif
            <div class="actions">
                <button class="btn" type="submit">年度更新をする</button>
                <a href="{{ route('athletes.index') }}">戻る</a>
            </div>
        </form>
    @endif
@endsection
