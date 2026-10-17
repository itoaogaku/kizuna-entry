<?php

namespace App\Rules;

use App\Models\Athlete;
use Illuminate\Validation\Rule;

/**
 * 選手情報の入力チェック（団体の選手登録・個人登録・一括登録で共通）。
 * 項目はエントリーシート（申込み欄）に合わせている。
 */
class AthleteRules
{
    public static function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:50'],
            'kana' => ['nullable', 'string', 'max:100', 'regex:/^[ァ-ヶー・ 　]+$/u'],
            'grade' => ['nullable', Rule::in(Athlete::GRADES)],
            'gender' => ['required', Rule::in(array_keys(Athlete::GENDERS))],
            'association' => ['nullable', Rule::in(Athlete::ASSOCIATIONS)],
            'category' => ['nullable', Rule::in(Athlete::CATEGORIES)],
            'birthdate' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'jaaf_id' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * 入力のゆれをそろえる。
     * フリガナ：ひらがな・半角カナ → 全角カタカナ（姓と名の間は半角スペース1つ）
     * 学年：全角 → 半角（M1・M2 などはそのまま）、所属陸協：「東京陸協」「東京都」→「東京」
     */
    public static function normalize(array $input): array
    {
        if (isset($input['kana']) && is_string($input['kana'])) {
            $kana = mb_convert_kana(trim($input['kana']), 'KVCs');
            $input['kana'] = preg_replace('/\s+/u', ' ', $kana);
        }
        if (isset($input['grade']) && (is_string($input['grade']) || is_int($input['grade']))) {
            // 「２年」「2」「m1」「Ｍ１」→「2」「M1」
            $grade = strtoupper(mb_convert_kana(trim((string) $input['grade']), 'as'));
            $grade = preg_replace('/年$/u', '', $grade);
            $input['grade'] = preg_match('/^[MD]?\d$/', $grade) ? $grade : ($grade === '' ? null : $grade);
        }
        if (isset($input['association']) && is_string($input['association'])) {
            $input['association'] = self::normalizeAssociation($input['association']);
        }
        foreach (['association', 'category', 'birthdate', 'jaaf_id', 'grade'] as $k) {
            if (array_key_exists($k, $input) && $input[$k] === '') {
                $input[$k] = null;
            }
        }

        return $input;
    }

    public static function normalizeAssociation(string $value): ?string
    {
        $value = trim(mb_convert_kana($value, 's'));
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/(陸上競技協会|陸協)$/u', '', $value);
        if ($value !== '北海道') {
            $value = preg_replace('/[都府県]$/u', '', $value);
        }

        return $value;
    }

    /**
     * 「高校」「高校生」「大学」などを種別の選択肢にそろえる。
     */
    public static function normalizeCategory(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (Athlete::CATEGORIES as $category) {
            if (str_starts_with($category, mb_substr($value, 0, 2))) {
                return $category;
            }
        }

        return $value;
    }
}
