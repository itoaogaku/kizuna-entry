<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\Meet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeetController extends Controller
{
    public function index(): View
    {
        $meets = Meet::withCount(['entries as paid_count' => fn ($q) => $q->where('status', Entry::STATUS_PAID)])
            ->withSum(['entries as paid_total' => fn ($q) => $q->where('status', Entry::STATUS_PAID)], 'total_amount')
            ->orderByDesc('held_on')
            ->get();

        return view('admin.meets.index', compact('meets'));
    }

    /**
     * 新規大会登録。前回の大会（いちばん新しい大会）の内容と種目を最初から入れておく。
     */
    public function create(Request $request): View
    {
        $source = $request->filled('copy')
            ? Meet::with('events')->find($request->integer('copy'))
            : Meet::with('events')->orderByDesc('held_on')->first();

        $meet = new Meet([
            'name' => $source ? self::nextName($source->name) : '',
            'venue' => $source?->venue,
            'max_events_per_athlete' => $source->max_events_per_athlete ?? 2,
            'notes' => $source?->notes,
            'division' => $source->division ?? '公認の部',
            'eligible' => $source?->eligible,
            'start_time' => $source?->start_time,
            'fee_junior_high' => $source?->fee_junior_high,
            'fee_student' => $source?->fee_student,
            'fee_general' => $source?->fee_general,
        ]);
        $events = $source
            ? $source->events->map(fn ($e) => $e->only(['name', 'gender', 'fee', 'capacity']))->all()
            : [];

        return view('admin.meets.form', [
            'meet' => $meet,
            'events' => $events,
            'source' => $source,
            'meets' => Meet::orderByDesc('held_on')->get(['id', 'name', 'held_on']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $categoryFees = ($data['fee_junior_high'] ?? null) !== null || ($data['fee_student'] ?? null) !== null || ($data['fee_general'] ?? null) !== null;
        $events = $this->validatedEvents($request, $categoryFees);

        $meet = DB::transaction(function () use ($data, $events) {
            $meet = Meet::create($data);
            foreach (array_values($events) as $i => $event) {
                $meet->events()->create($event + ['sort_order' => ($i + 1) * 10]);
            }

            return $meet;
        });

        $message = '大会を登録しました（種目'.count($events).'件）。';
        $message .= $meet->is_published ? '受付期間になるとエントリーできます。' : '内容を確認して、「公開する」にすると受付が始まります。';

        return redirect()->route('admin.meets.show', $meet)->with('status', $message);
    }

    public function show(Meet $meet): View
    {
        $meet->load('events');

        return view('admin.meets.show', [
            'meet' => $meet,
            'reserved' => $meet->events->mapWithKeys(fn ($e) => [$e->id => $e->reservedCount()]),
        ]);
    }

    public function edit(Meet $meet): View
    {
        return view('admin.meets.form', compact('meet'));
    }

    public function update(Request $request, Meet $meet): RedirectResponse
    {
        $meet->update($this->validated($request, $meet));

        return redirect()->route('admin.meets.show', $meet)->with('status', '大会情報を更新しました。');
    }

    /**
     * 新規登録画面の種目欄。種目名が空の行は使わない。
     *
     * @return array<int, array{name: string, gender: ?string, fee: int, capacity: ?int}>
     */
    private function validatedEvents(Request $request, bool $categoryFees = false): array
    {
        $rows = collect((array) $request->input('events', []))
            ->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '')
            ->values()
            ->all();

        $validated = validator(['events' => $rows], [
            'events' => ['array'],
            'events.*.name' => ['required', 'string', 'max:100', 'distinct'],
            'events.*.gender' => ['nullable', Rule::in(['M', 'F'])],
            'events.*.fee' => $categoryFees ? ['nullable', 'integer', 'min:0', 'max:100000'] : ['required', 'integer', 'min:50', 'max:100000'],
            'events.*.capacity' => ['nullable', 'integer', 'min:1'],
        ], [
            'events.*.name.distinct' => '種目名「:input」が重複しています。',
            'events.*.fee.required' => '種目「:position」番目の料金を入力してください。',
        ], [
            'events.*.name' => '種目名',
            'events.*.fee' => '料金',
            'events.*.capacity' => '定員',
        ])->validate()['events'] ?? [];

        return array_map(fn ($e) => [
            'name' => trim($e['name']),
            'gender' => $e['gender'] ?? null,
            'fee' => (int) ($e['fee'] ?? 0),
            'capacity' => isset($e['capacity']) && $e['capacity'] !== '' ? (int) $e['capacity'] : null,
        ], $validated);
    }

    /**
     * 「第10回 絆記録会」→「第11回 絆記録会」。回数がなければそのまま。
     */
    private static function nextName(string $name): string
    {
        return preg_replace_callback('/第(\d+)回/u', fn ($m) => '第'.((int) $m[1] + 1).'回', $name, 1);
    }

    private function validated(Request $request, ?Meet $meet = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'held_on' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:100'],
            'entry_opens_at' => ['required', 'date'],
            'entry_closes_at' => ['required', 'date', 'after:entry_opens_at'],
            'max_events_per_athlete' => ['required', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'division' => ['nullable', 'string', 'max:50'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'fee_junior_high' => ['nullable', 'integer', 'min:50', 'max:100000'],
            'fee_student' => ['nullable', 'integer', 'min:50', 'max:100000'],
            'fee_general' => ['nullable', 'integer', 'min:50', 'max:100000'],
            'eligible' => ['nullable', 'string', 'max:100'],
            'program_note' => ['nullable', 'string', 'max:50'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'press_url' => ['nullable', 'url', 'max:500'],
        ];
        foreach (array_keys(Meet::DOCUMENTS) as $key) {
            $rules[$key.'_url'] = ['nullable', 'url', 'max:500'];
            $rules[$key.'_file'] = ['nullable', 'file', 'mimes:pdf', 'max:20480'];
        }
        $data = $request->validate($rules, [], [
            'outline_file' => '大会要項のPDF', 'program_file' => '大会プログラムのPDF', 'result_file' => 'リザルトのPDF',
            'outline_url' => '大会要項のURL', 'program_url' => '大会プログラムのURL', 'result_url' => 'リザルトのURL',
            'photo_url' => '写真館のURL', 'press_url' => '取材・撮影のお申込みのURL', 'division' => '部門', 'eligible' => '対象',
            'start_time' => '競技開始予定', 'fee_junior_high' => '中学生の参加料', 'fee_student' => '高校生・大学生の参加料', 'fee_general' => '一般・実業団の参加料',
        ]);

        // PDFは公開用の保存場所に置く（差し替え・削除のときは古いファイルを消す）
        foreach (array_keys(Meet::DOCUMENTS) as $key) {
            unset($data[$key.'_file']);
            $old = $meet?->{$key.'_path'};
            if ($request->hasFile($key.'_file')) {
                $data[$key.'_path'] = $request->file($key.'_file')->store('meets', 'public');
            } elseif ($request->boolean($key.'_remove')) {
                $data[$key.'_path'] = null;
            }
            if ($old && array_key_exists($key.'_path', $data) && $data[$key.'_path'] !== $old) {
                Storage::disk('public')->delete($old);
            }
        }

        $data['division'] = ($data['division'] ?? null) ?: '公認の部';

        return $data + ['is_published' => $request->boolean('is_published')];
    }
}
