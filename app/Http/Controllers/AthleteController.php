<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AthleteController extends Controller
{
    private const CSV_HEADER = ['姓', '名', 'セイ', 'メイ', '性別', '生年月日', '区分', '陸連登録番号'];

    public function index(Request $request): View
    {
        $athletes = $request->user()->athletes()
            ->orderByDesc('is_active')
            ->orderBy('last_kana')
            ->orderBy('first_kana')
            ->get();

        return view('athletes.index', compact('athletes'));
    }

    public function create(): View
    {
        return view('athletes.form', ['athlete' => new Athlete(['gender' => 'M', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
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
        $athlete->update($this->validated($request->all()) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('athletes.index')->with('status', '選手情報を更新しました。');
    }

    public function importForm(): View
    {
        return view('athletes.import', ['header' => self::CSV_HEADER]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADER);
            fputcsv($out, ['絆', '太郎', 'キズナ', 'タロウ', '男', '2008/04/01', '高校', '']);
            fclose($out);
        }, 'athletes_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Excelで作ったCSV（UTF-8またはShift_JIS）から選手をまとめて登録する。
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'max:1024']]);

        $content = file_get_contents($request->file('csv')->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $rows = [];
        $errors = [];
        foreach ($lines as $n => $line) {
            if ($n === 0 || trim($line) === '') {
                continue; // 1行目は見出し
            }
            $cols = array_pad(str_getcsv($line), 8, '');
            $data = [
                'last_name' => trim($cols[0]),
                'first_name' => trim($cols[1]),
                'last_kana' => mb_convert_kana(trim($cols[2]), 'KVC'),
                'first_kana' => mb_convert_kana(trim($cols[3]), 'KVC'),
                'gender' => match (trim($cols[4])) {
                    '男', '男子', 'M', 'm' => 'M',
                    '女', '女子', 'F', 'f' => 'F',
                    default => trim($cols[4]),
                },
                'birthdate' => $this->parseDate(trim($cols[5])),
                'category' => trim($cols[6]) ?: null,
                'jaaf_id' => trim($cols[7]) ?: null,
            ];
            $v = Validator::make($data, $this->rules());
            if ($v->fails()) {
                $errors[] = ($n + 1).'行目：'.implode(' ', $v->errors()->all());

                continue;
            }
            $rows[] = $v->validated();
        }

        if ($errors !== []) {
            return back()->withErrors($errors);
        }
        if ($rows === []) {
            return back()->withErrors(['csv' => '登録できる行がありませんでした。']);
        }

        DB::transaction(function () use ($request, $rows) {
            foreach ($rows as $row) {
                $request->user()->athletes()->create($row);
            }
        });

        return redirect()->route('athletes.index')->with('status', count($rows).'人の選手を登録しました。');
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) { // Excelのシリアル値
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }
        try {
            return Carbon::parse(str_replace(['年', '月', '日', '.'], ['-', '-', '', '-'], $value))->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_kana' => ['required', 'string', 'max:50', 'regex:/^[ァ-ヶー　 ]+$/u'],
            'first_kana' => ['required', 'string', 'max:50', 'regex:/^[ァ-ヶー　 ]+$/u'],
            'gender' => ['required', Rule::in(array_keys(Athlete::GENDERS))],
            'birthdate' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'category' => ['nullable', 'string', 'max:30'],
            'jaaf_id' => ['nullable', 'string', 'max:30'],
        ];
    }

    private function validated(array $input): array
    {
        foreach (['last_kana', 'first_kana'] as $k) {
            if (isset($input[$k])) {
                $input[$k] = mb_convert_kana(trim($input[$k]), 'KVC');
            }
        }

        return Validator::make($input, $this->rules())->validate();
    }

    private function authorizeOwner(Request $request, Athlete $athlete): void
    {
        abort_unless($athlete->user_id === $request->user()->id, 404);
    }
}
