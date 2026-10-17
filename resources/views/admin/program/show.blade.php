@extends('layouts.app')
@section('title', $meet->name.' プログラム作成')

@section('content')
    <h1>{{ $meet->name }} プログラム作成</h1>
    @include('admin.nav')

    @php
        $statusLabel = ['none' => '未公開', 'provisional' => '暫定版を公開中', 'final' => '確定（プログラム完成）'][$meet->start_list_status];
    @endphp
    <div class="card program-status">
        <div>
            <span class="muted">タイムテーブル・スタートリスト</span><br>
            <strong class="status-{{ $meet->start_list_status }}">{{ $statusLabel }}</strong>
            @if ($meet->start_list_published_at)<span class="muted">（{{ $meet->start_list_published_at->format('n/j H:i') }}）</span>@endif
        </div>
        <ol class="flow">
            <li @class(['done' => $generated])>① 組分けを作る</li>
            <li @class(['done' => $meet->hasPublicStartList()])>② 暫定版を公開</li>
            <li>③ 組変更を反映</li>
            <li @class(['done' => $meet->isProgramFinal()])>④ 確定（プログラム完成）</li>
        </ol>
        <form method="POST" action="{{ route('admin.program.status', $meet) }}" class="actions">
            @csrf @method('PUT')
            @if ($meet->start_list_status !== 'provisional')
                <button class="btn secondary" name="status" value="provisional" @disabled(! $generated)>暫定版として公開</button>
            @endif
            @if ($meet->start_list_status !== 'final')
                <button class="btn" name="status" value="final" @disabled(! $generated)
                        onclick="return confirm('タイムテーブルとスタートリストを確定し、大会プログラムを公開しますか？');">確定してプログラムを完成させる</button>
            @endif
            @if ($meet->start_list_status !== 'none')
                <button class="btn danger" name="status" value="none">非公開に戻す</button>
            @endif
        </form>
        <div class="actions" style="margin-top:4px">
            <a href="{{ route('site.timetable', $meet) }}" target="_blank">タイムテーブルを見る</a>
            <a href="{{ route('site.startlist', $meet) }}" target="_blank">スタートリストを見る</a>
            <a href="{{ route('site.program', $meet) }}" target="_blank">プログラム（印刷用）を見る</a>
            <a href="{{ route('admin.program.export', $meet) }}">スタートリストをExcelで</a>
            <a href="{{ route('admin.press.index', $meet) }}">取材・撮影のお申込み（{{ $meet->pressRequests()->count() }}件）</a>
        </div>
        @unless ($meet->hasPublicStartList())
            <p class="muted" style="margin:6px 0 0">未公開のあいだは、運営（管理者）だけが上のリンクから確認できます。</p>
        @endunless
    </div>

    @if ($unassigned->isNotEmpty())
        <div class="flash warn">
            組分けのあとに入金された申込が {{ $unassigned->count() }} 件あります（{{ $unassigned->take(5)->map(fn ($i) => $i->athlete->fullName().'・'.$i->event->name)->implode('、') }}{{ $unassigned->count() > 5 ? ' ほか' : '' }}）。
            「組分けを作り直す」で入れるか、作り直さずに組変更で調整してください。
        </div>
    @endif
    @php $few = $races->filter(fn ($r) => $r->startListEntries->isNotEmpty() && $r->startListEntries->count() < config('kizuna.min_participants')); @endphp
    @if ($few->isNotEmpty())
        <div class="flash warn">最小遂行人数（{{ config('kizuna.min_participants') }}名）に満たないレースがあります：{{ $few->map(fn ($r) => $r->name().' '.$r->startListEntries->count().'名')->implode('、') }}</div>
    @endif
    @if ($withdrawn > 0)
        <div class="flash warn">組分けのあとに取り消された申込が {{ $withdrawn }} 件あります（スタートリストでは「取消」と表示されます）。</div>
    @endif

    <h2>タイムテーブルの設定</h2>
    @if ($races->isEmpty())
        <div class="card"><p>入金済みの申込がまだありません。</p></div>
    @else
        <form method="POST" action="{{ route('admin.program.races', $meet) }}">
            @csrf @method('PUT')
            <div class="table-wrap">
                <table>
                    <thead><tr><th>順</th><th>レース</th><th class="num">人数</th><th class="num">組数</th><th>1組目のスタート</th><th>1組の人数</th><th>組の間隔（分）</th><th>速い組を最後に</th></tr></thead>
                    <tbody>
                    @foreach ($races as $race)
                        <tr>
                            <td><input type="number" name="races[{{ $race->id }}][sort_order]" value="{{ $race->sort_order }}" style="width:70px" aria-label="順"></td>
                            <td><strong>{{ $race->name() }}</strong></td>
                            <td class="num">{{ $race->startListEntries->count() ?: '―' }}</td>
                            <td class="num">{{ $race->heatCount() ?: '―' }}</td>
                            <td><input type="time" name="races[{{ $race->id }}][start_time]" value="{{ $race->start_time ? substr($race->start_time, 0, 5) : '' }}" aria-label="スタート時刻"></td>
                            <td><input type="number" name="races[{{ $race->id }}][heat_size]" value="{{ $race->heat_size }}" min="1" style="width:80px" aria-label="1組の人数"></td>
                            <td><input type="number" name="races[{{ $race->id }}][heat_interval]" value="{{ $race->heat_interval }}" min="0" style="width:80px" aria-label="組の間隔"></td>
                            <td><input type="checkbox" name="races[{{ $race->id }}][fastest_last]" value="1" @checked($race->fastest_last) aria-label="速い組を最後に"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="field" style="margin-top:12px">
                <label for="program_notes">プログラムに載せる注意事項 <span class="hint">（招集・ナンバーカード・競技上の注意など）</span></label>
                <textarea id="program_notes" name="program_notes">{{ old('program_notes', $meet->program_notes) }}</textarea>
            </div>
            <div class="actions">
                <button class="btn" type="submit">設定を保存</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.program.generate', $meet) }}" class="actions"
              @if ($generated) onsubmit="return confirm('組分けを作り直します。手で動かした組変更は元に戻ります。よろしいですか？');" @endif>
            @csrf
            <button class="btn {{ $generated ? 'secondary' : '' }}" type="submit">{{ $generated ? '組分けを作り直す' : '組分けを作る' }}</button>
            <span class="muted">入金済みの申込を、レースごとに申請タイム順（同タイムは自己ベスト順）で組に分けます。</span>
        </form>
    @endif

    @if ($generated)
        <h2>スタートリスト（組変更）</h2>
        <p class="muted">組変更希望があった選手は「組」を変えて「組変更を保存」を押してください。組の中の順番は申請タイム順に並べ直します。</p>
        <form method="POST" action="{{ route('admin.program.heats', $meet) }}">
            @csrf @method('PUT')
            @foreach ($races as $race)
                @continue ($race->startListEntries->isEmpty())
                <h3 class="race-title">{{ $race->name() }} <span class="muted">{{ $race->startListEntries->count() }}人・{{ $race->heatCount() }}組</span></h3>
                @foreach ($race->heats() as $heat => $entries)
                    <div class="table-wrap heat-table">
                        <table>
                            <thead><tr><th colspan="7">{{ $heat }}組 @if ($time = $race->heatStartTime($heat))<span class="muted">{{ $time }} スタート</span>@endif</th></tr>
                            <tr><th class="num">ナンバー</th><th>氏名</th><th>所属</th><th>種別・学年</th><th>申請タイム</th><th>自己ベスト</th><th>組</th></tr></thead>
                            <tbody>
                            @foreach ($entries as $sle)
                                @php $item = $sle->entryItem; $user = $item->entry->user; @endphp
                                <tr @class(['withdrawn' => ! $item->entry->isPaid()])>
                                    <td class="num">{{ $sle->position }}</td>
                                    <td>{{ $item->athlete->fullName() }} @unless ($item->entry->isPaid())<span class="badge cancelled">取消</span>@endunless</td>
                                    <td>{{ $user->isIndividual() ? ($user->team_name ?: '個人') : $user->displayName() }}</td>
                                    <td>{{ $item->athlete->category }}{{ $item->athlete->grade ? ' '.$item->athlete->grade.'年' : '' }}</td>
                                    <td>{{ $item->declared_record }}</td>
                                    <td>{{ $item->personal_best }}</td>
                                    <td>
                                        <select name="heats[{{ $sle->id }}]" aria-label="{{ $item->athlete->fullName() }}の組" style="min-width:80px">
                                            @for ($h = 1; $h <= $race->heatCount() + 1; $h++)
                                                <option value="{{ $h }}" @selected($h === $sle->heat)>{{ $h }}組{{ $h > $race->heatCount() ? '（新）' : '' }}</option>
                                            @endfor
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endforeach
            <div class="actions">
                <button class="btn" type="submit">組変更を保存</button>
            </div>
        </form>
    @endif
@endsection
