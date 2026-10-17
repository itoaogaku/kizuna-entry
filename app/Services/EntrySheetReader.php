<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * 絆記録会のエントリーシート（Excel）や、同じ列のCSVから選手情報を読み取る。
 * 見出しの文字で列を探すので、列の順番が多少違っても読める。
 */
class EntrySheetReader
{
    /** 見出しの先頭の文字 → 項目名 */
    private const HEADERS = [
        '姓' => 'last_name',
        '名' => 'first_name',
        'フリガナ' => 'kana',
        'ﾌﾘｶﾞﾅ' => 'kana',
        '学年' => 'grade',
        '性別' => 'gender',
        '所属陸協' => 'association',
        '種別' => 'category',
        '生年月日' => 'birthdate',
        '陸連登録番号' => 'jaaf_id',
        // 大会の申込み欄（エクセル添付でのエントリーで使う）
        '申込み種目' => 'event',
        '申込種目' => 'event',
        '申請タイム' => 'record',
        '自己ベスト' => 'best',
        'PRしたい一言' => 'pr',
    ];

    /** シート上部の「団体名」欄に書かれていた団体名 */
    public ?string $teamName = null;

    /**
     * @return array<int, array{line: int, data: array<string, ?string>}> 行番号つきの選手データ
     */
    public function read(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'csv'
            ? $this->readCsv($path)
            : $this->readSpreadsheet($path);

        $this->teamName = $this->findTeamName($rows);
        [$headerIndex, $columns] = $this->findHeader($rows);
        if ($headerIndex === null) {
            throw new \RuntimeException('「姓」「名」の見出しが見つかりませんでした。エントリーシートかひな形の形式で作成してください。');
        }

        $result = [];
        foreach ($rows as $i => $row) {
            if ($i <= $headerIndex) {
                continue;
            }
            $first = trim((string) ($row[0] ?? ''));
            if ($first === '例') {
                continue; // 記入例の行
            }
            $data = [];
            foreach ($columns as $col => $field) {
                $value = $row[$col] ?? null;
                $data[$field] = $value === null ? null : trim((string) $value);
            }
            if (($data['last_name'] ?? '') === '' && ($data['first_name'] ?? '') === '') {
                continue; // 空行（番号だけの行）
            }
            $result[] = ['line' => $i + 1, 'data' => $data];
        }

        return $result;
    }

    /**
     * 「団体名」と書かれたセルの右側にある最初の値。
     */
    private function findTeamName(array $rows): ?string
    {
        foreach (array_slice($rows, 0, 8) as $row) {
            $row = array_values($row);
            foreach ($row as $col => $cell) {
                if (trim((string) $cell) !== '団体名') {
                    continue;
                }
                foreach (array_slice($row, $col + 1) as $value) {
                    $value = trim((string) $value);
                    if (in_array($value, ['合計人数', '合計料金'], true)) {
                        break; // 団体名が空欄
                    }
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: ?int, 1: array<int, string>}
     */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 30, true) as $i => $row) {
            $columns = [];
            foreach ($row as $col => $cell) {
                $label = trim(preg_split('/[\n（(　 ※]/u', (string) $cell)[0]);
                if (isset(self::HEADERS[$label]) && ! in_array(self::HEADERS[$label], $columns, true)) {
                    $columns[$col] = self::HEADERS[$label];
                }
            }
            if (in_array('last_name', $columns, true) && in_array('first_name', $columns, true)) {
                return [$i, $columns];
            }
        }

        return [null, []];
    }

    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        // 「申込み」シートを優先し、なければ最初のシート
        $sheet = $book->getSheetByName('申込み') ?? $book->getSheet(0);

        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $rows[] = array_map(fn ($v) => is_float($v) && floor($v) == $v ? (string) (int) $v : $v, $row);
        }

        // 生年月日がExcelの日付（シリアル値）の場合に備えて、列ごとの変換は呼び出し側で行う
        return $rows;
    }

    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * 「2008/4/1」「2008年4月1日」やExcelの日付シリアル値を Y-m-d にする。
     */
    public static function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $value = mb_convert_kana($value, 'n');
        if (preg_match('/^(\d{4})\D(\d{1,2})\D(\d{1,2})/u', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3])
                : $value;
        }

        return $value;
    }
}
