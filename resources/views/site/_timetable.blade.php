<div class="table-wrap">
    <table class="timetable">
        <thead><tr><th>スタート</th><th>レース</th><th>組</th><th class="num">人数</th></tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td class="time">{{ $row['time'] ?? '未定' }}</td>
                <td>{{ $row['race']->name() }}</td>
                <td>{{ $row['heat'] }}組 / {{ $row['race']->heatCount() }}組</td>
                <td class="num">{{ $row['count'] }}人</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
