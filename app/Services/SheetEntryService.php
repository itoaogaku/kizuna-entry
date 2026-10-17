<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Rules\AthleteRules;
use App\Support\RaceTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * エクセル添付でのエントリー。
 * 今までのエントリーシート（申込み欄）を読み込み、Webエントリーと同じ形の申込データにする。
 */
class SheetEntryService
{
    public function __construct(private EntrySheetReader $reader, private EntryService $entries) {}

    /**
     * シートを読み、行ごとの内容とエラーを返す（まだ保存しない）。
     *
     * @return array{teamName: ?string, rows: list<array{line: int, athlete: array<string, mixed>, event: ?Event, eventName: string, record: ?string, best: ?string, pr: ?string, errors: list<string>}>}
     */
    public function parse(Meet $meet, string $path, string $extension): array
    {
        $lines = $this->reader->read($path, $extension);
        $events = $meet->events()->get();
        $rows = [];
        $seen = [];
        $perAthlete = [];

        foreach ($lines as ['line' => $line, 'data' => $data]) {
            $errors = [];
            $athlete = $this->athleteData($data);
            $v = Validator::make($athlete, AthleteRules::rules());
            if ($v->fails()) {
                $errors = array_merge($errors, $v->errors()->all());
            }

            $eventName = trim((string) ($data['event'] ?? ''));
            $event = $this->matchEvent($events, $eventName);
            if ($eventName === '') {
                $errors[] = '申込み種目が空欄です。';
            } elseif (! $event) {
                $errors[] = '申込み種目「'.$eventName.'」はこの大会にありません（'.$events->pluck('name')->implode('・').'）。';
            } elseif (isset($athlete['gender']) && in_array($athlete['gender'], ['M', 'F'], true) && ! $event->allowsGender($athlete['gender'])) {
                $errors[] = $event->name.'には出場できません（性別）。';
            }

            $record = RaceTime::normalize($data['record'] ?? null);
            if ($record === null) {
                $errors[] = '申請タイムが空欄です。';
            } elseif (! RaceTime::isValid($record)) {
                $errors[] = '申請タイム「'.$record.'」の形が正しくありません（例：14\'30）。';
            }
            $best = RaceTime::normalize($data['best'] ?? null);
            if ($best !== null && ! RaceTime::isValid($best)) {
                $best = null; // 自己ベストは任意なので、読めなければ使わない
            }

            $nameKey = $this->nameKey((string) ($athlete['last_name'] ?? ''), (string) ($athlete['first_name'] ?? ''));
            if ($event) {
                if (isset($seen[$nameKey.'|'.$event->id])) {
                    $errors[] = '同じ選手・同じ種目の行が重複しています（'.$seen[$nameKey.'|'.$event->id].'行目）。';
                }
                $seen[$nameKey.'|'.$event->id] = $line;
                $perAthlete[$nameKey] = ($perAthlete[$nameKey] ?? 0) + 1;
                if ($perAthlete[$nameKey] > $meet->max_events_per_athlete) {
                    $errors[] = '1人'.$meet->max_events_per_athlete.'種目までです。';
                }
            }

            $pr = trim((string) ($data['pr'] ?? ''));
            $rows[] = [
                'line' => $line,
                'athlete' => $athlete,
                'event' => $event,
                'eventName' => $eventName,
                'record' => $record,
                'best' => $best,
                'pr' => $pr === '' ? null : mb_substr($pr, 0, 100),
                'errors' => $errors,
            ];
        }

        return ['teamName' => $this->reader->teamName, 'rows' => $rows];
    }

    /**
     * 読み込んだ内容で決済待ちのエントリーを作る。選手は申込者の選手として登録（同じ姓名なら更新）する。
     *
     * @param  list<array<string, mixed>>  $rows  parse() の rows（エラーがないこと）
     */
    public function submit(User $user, Meet $meet, array $rows, string $method, ?string $sheetPath): Entry
    {
        $athletes = DB::transaction(function () use ($user, $rows) {
            $existing = $user->athletes()->get()->keyBy(fn ($a) => $this->nameKey($a->last_name, $a->first_name));
            $result = [];
            foreach ($rows as $row) {
                $key = $this->nameKey($row['athlete']['last_name'], $row['athlete']['first_name']);
                if (! isset($result[$key])) {
                    $data = collect($row['athlete'])->only(array_keys(AthleteRules::rules()))->all();
                    $athlete = $existing->get($key);
                    if ($athlete) {
                        $athlete->update(array_filter($data, fn ($v) => $v !== null) + ['is_active' => true]);
                    } else {
                        $athlete = $user->athletes()->create($data);
                    }
                    $result[$key] = $athlete;
                }
            }

            return $result;
        });

        $selections = collect($rows)->map(fn ($row) => [
            'athlete' => $athletes[$this->nameKey($row['athlete']['last_name'], $row['athlete']['first_name'])],
            'event' => $row['event'],
            'record' => $row['record'],
            'best' => $row['best'],
            'pr' => $row['pr'],
        ]);

        $entry = $this->entries->createPendingEntry($user, $meet, $selections, $method, Entry::SOURCE_SHEET);
        $entry->update(['sheet_path' => $sheetPath]);

        return $entry;
    }

    /**
     * @param  array<string, ?string>  $data
     * @return array<string, mixed>
     */
    private function athleteData(array $data): array
    {
        $athlete = collect($data)->only(['last_name', 'first_name', 'kana', 'grade', 'gender', 'association', 'category', 'birthdate', 'jaaf_id'])->all();
        $athlete['gender'] = match (trim((string) ($athlete['gender'] ?? ''))) {
            '男', '男子', 'M', 'm' => 'M',
            '女', '女子', 'F', 'f' => 'F',
            default => $athlete['gender'] ?? null,
        };
        $athlete['birthdate'] = EntrySheetReader::parseDate($athlete['birthdate'] ?? null);
        $athlete['category'] = AthleteRules::normalizeCategory($athlete['category'] ?? null);

        return AthleteRules::normalize($athlete);
    }

    /**
     * 「5000m」「５０００ｍ」「5000M」「5000 m」を同じ種目として扱う。
     *
     * @param  Collection<int, Event>  $events
     */
    private function matchEvent(Collection $events, string $name): ?Event
    {
        $normalize = fn (string $v) => mb_strtolower(preg_replace('/\s+/u', '', mb_convert_kana($v, 'as')));
        $target = $normalize($name);

        return $target === '' ? null : $events->first(fn (Event $e) => $normalize($e->name) === $target);
    }

    private function nameKey(string $last, string $first): string
    {
        return preg_replace('/\s+/u', '', mb_convert_kana($last.$first, 'asKV'));
    }
}
