<?php

namespace App\Rules;

use App\Models\Athlete;
use Illuminate\Validation\Rule;

/**
 * 選手情報の入力チェック（団体の選手登録・個人登録で共通）。
 */
class AthleteRules
{
    public static function rules(): array
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

    /**
     * フリガナのひらがな・半角カナを全角カタカナにそろえる。
     */
    public static function normalize(array $input): array
    {
        foreach (['last_kana', 'first_kana'] as $k) {
            if (isset($input[$k]) && is_string($input[$k])) {
                $input[$k] = mb_convert_kana(trim($input[$k]), 'KVC');
            }
        }

        return $input;
    }
}
