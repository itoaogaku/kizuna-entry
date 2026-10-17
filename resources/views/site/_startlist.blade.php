@foreach ($races as $race)
    <section class="sl-race" id="race-{{ $race->id }}">
        <h2>{{ $race->name() }} <span class="muted">{{ $race->startListEntries->count() }}人</span></h2>
        @foreach ($race->heats() as $heat => $entries)
            <div class="table-wrap sl-heat">
                <table>
                    <thead>
                    <tr><th colspan="7" class="heat-head">{{ $heat }}組 @if ($time = $race->heatStartTime($heat))<span>{{ $time }} スタート</span>@endif</th></tr>
                    <tr><th class="num">ナンバー</th><th>氏名</th><th>フリガナ</th><th>所属</th><th>学年</th><th>部門</th><th>申請タイム</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($entries as $sle)
                        @php $item = $sle->entryItem; $user = $item->entry->user; $a = $item->athlete; @endphp
                        <tr>
                            <td class="num">{{ $sle->position }}</td>
                            <td class="name">{{ $a->fullName() }}</td>
                            <td class="kana">{{ $a->kana }}</td>
                            <td>{{ $user->isIndividual() ? ($user->team_name ?: '個人') : $user->displayName() }}</td>
                            <td>{{ $a->category ? mb_substr($a->category, 0, 1) : '' }}{{ $a->grade ?: '' }}</td>
                            <td>@if (\App\Models\Meet::divisionOf($a->category) === 'junior')<span class="div-junior">中学生の部</span>@else<span class="div-open">高校・大学・一般</span>@endif</td>
                            <td>{{ $item->declared_record }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </section>
@endforeach
