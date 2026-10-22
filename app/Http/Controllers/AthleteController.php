<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Rules\AthleteRules;
use App\Services\AthleteTemplate;
use App\Services\EntrySheetReader;
use App\Services\GradeAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AthleteController extends Controller
{
    private const CSV_HEADER = ['姓', '名', 'フリガナ', '学年', '性別', '所属陸協', '種別'];

    public function index(Request $request): View
    {
        $athletes = $request->user()->athletes()
            ->orderByDesc('is_active')
            ->orderBy('kana')
            ->get();

        return view('athletes.index', compact('athletes'));
    }

    public function create(Request $request): View
    {
        $this->denyIndividual($request);

        return view('athletes.form', ['athlete' => new Athlete(['gender' => 'M', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->denyIndividual($request);
        $request->user()->athletes()->create($this->validated($request->all()));

        return redirect()->route('athletes.index')->with('status', '選手を登録しました。');
    }

    public function edit(Request $request, Athlete $athlete): View
    {
        $this->authorizeOwner($request, $athlete);

        return view('athletes.form', compact('athlete'));
    }

    public function update(Request $request, Athlete $athlete): RedirectResponse
    {
        $this->authorizeOwner($request, $athlete);
        if ($request->user()->isIndividual()) {
            $athlete->update($this->validated($request->all()));
            $request->user()->update(['name' => $athlete->fullName()]);

            return redirect()->route('athletes.index')->with('status', '登録情報を更新しました。');
        }
        $athlete->update($this->validated($request->all()) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('athletes.index')->with('status', '選手情報を更新しました。');
    }

    /**
     * 年度更新の確認画面。
     */
    public function advanceForm(Request $request, GradeAdvance $advance): View
    {
        return view('athletes.advance', [
            'rows' => $advance->preview($request->user()),
            'doneThisYear' => GradeAdvance::isDoneThisYear($request->user()),
            'fiscalYear' => GradeAdvance::fiscalYear(),
        ]);
    }

    public function advance(Request $request, GradeAdvance $advance): RedirectResponse
    {
        if (GradeAdvance::isDoneThisYear($request->user()) && ! $request->boolean('again')) {
            return back()->withErrors(['again' => '今年度の年度更新はすでに行っています。もう一度行う場合は、確認のチェックを入れてください。']);
        }
        $choices = array_filter((array) $request->input('choices', []), fn ($v) => in_array($v, [GradeAdvance::RETIRE, GradeAdvance::PROMOTE, GradeAdvance::KEEP], true));
        $result = $advance->apply($request->user(), $choices);

        return redirect()->route('athletes.index')->with('status', sprintf(
            '年度更新をしました（学年を上げた選手 %d人・進学 %d人・対象外 %d人）。',
            $result['advanced'], $result['promoted'], $result['retired'],
        ));
    }

    public function importForm(Request $request): View
    {
        $this->denyIndividual($request);

        return view('athletes.import', ['header' => self::CSV_HEADER]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADER);
            fputcsv($out, ['鈴木', '太郎', 'スズキ タロウ', '3', '男', '東京', '高校生']);
            fclose($out);
        }, 'athletes_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 選手登録用のエクセルシート（学年・性別などは選択式）。
     */
    public function templateWorkbook(AthleteTemplate $template): StreamedResponse
    {
        $book = $template->build();

        return response()->streamDownload(function () use ($book) {
            IOFactory::createWriter($book, 'Xlsx')->save('php://output');
        }, '選手登録シート.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * エントリーシート（Excel）またはCSVから選手をまとめて登録する。
     * 同じ選手が複数行（複数種目）あっても1人として登録し、登録済みの選手は飛ばす。
     */
    public function import(Request $request, EntrySheetReader $reader): RedirectResponse
    {
        $this->denyIndividual($request);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls,csv']]);
        $file = $request->file('file');

        try {
            $lines = $reader->read($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'ファイルを読み込めませんでした。Excel（.xlsx）またはCSVで保存したファイルを選んでください。']);
        }

        $existing = $request->user()->athletes()->get()
            ->map(fn ($a) => $this->nameKey($a->last_name, $a->first_name));
        $rows = [];
        $errors = [];
        $skipped = 0;
        foreach ($lines as ['line' => $line, 'data' => $data]) {
            $data['gender'] = match (trim((string) ($data['gender'] ?? ''))) {
                '男', '男子', 'M', 'm' => 'M',
                '女', '女子', 'F', 'f' => 'F',
                default => $data['gender'] ?? null,
            };
            $data['birthdate'] = EntrySheetReader::parseDate($data['birthdate'] ?? null);
            $data['category'] = AthleteRules::normalizeCategory($data['category'] ?? null);
            $data = AthleteRules::normalize($data);

            $key = $this->nameKey((string) ($data['last_name'] ?? ''), (string) ($data['first_name'] ?? ''));
            if ($existing->contains($key) || isset($rows[$key])) {
                $skipped++;

                continue;
            }

            $v = Validator::make($data, $this->rules());
            if ($v->fails()) {
                $errors[] = $line.'行目（'.($data['last_name'] ?? '').' '.($data['first_name'] ?? '').'）：'.implode(' ', $v->errors()->all());

                continue;
            }
            $rows[$key] = $v->validated();
        }

        if ($errors !== []) {
            return back()->withErrors($errors);
        }
        if ($rows === []) {
            return back()->withErrors(['file' => $skipped > 0 ? 'ファイルの選手はすべて登録済みでした。' : '登録できる選手が見つかりませんでした。']);
        }

        DB::transaction(function () use ($request, $rows) {
            foreach ($rows as $row) {
                $request->user()->athletes()->create($row);
            }
        });

        $message = count($rows).'人の選手を登録しました。';
        if ($skipped > 0) {
            $message .= '（登録済み・重複の'.$skipped.'行は飛ばしました）';
        }

        return redirect()->route('athletes.index')->with('status', $message);
    }

    private function nameKey(string $last, string $first): string
    {
        return preg_replace('/\s+/u', '', mb_convert_kana($last.$first, 'asKV'));
    }

    private function rules(): array
    {
        return AthleteRules::rules();
    }

    private function validated(array $input): array
    {
        return Validator::make(AthleteRules::normalize($input), $this->rules())->validate();
    }

    /**
     * 個人の申込者は本人1人だけなので、選手の追加はできない。
     */
    private function denyIndividual(Request $request): void
    {
        abort_if($request->user()->isIndividual(), 404);
    }

    private function authorizeOwner(Request $request, Athlete $athlete): void
    {
        abort_unless($athlete->user_id === $request->user()->id, 404);
    }
}
