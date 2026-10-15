<?php

namespace App\Support;

/**
 * 申請タイム・自己ベストの表記（例：14'30、14:30、4'05"5、15:32.10）を扱う。
 */
class RaceTime
{
    /**
     * 全角や似た記号をそろえる。例：「１４’３０」→「14'30」
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim(mb_convert_kana((string) $value, 'as'));
        if ($value === '') {
            return null;
        }
        $value = str_replace(['’', '‘', '′', '´', '`'], "'", $value);
        $value = str_replace(['”', '“', '″', "''"], '"', $value);
        $value = preg_replace('/\s+/', '', $value);

        return self::fromDigits($value) ?? $value;
    }

    /**
     * 数字だけの入力を記録の形にする（スマホの数字キーだけで入力できるように）。
     * 「1430」→「14'30」、「405」→「4'05」、「40530」→「4'05"30」、「143012」→「14'30"12」
     */
    private static function fromDigits(string $value): ?string
    {
        if (! preg_match('/^\d{3,6}$/', $value)) {
            return null;
        }
        $fraction = '';
        if (strlen($value) >= 5) {
            $fraction = '"'.substr($value, -2);
            $value = substr($value, 0, -2);
        }

        return (int) substr($value, 0, -2)."'".substr($value, -2).$fraction;
    }

    /**
     * 「分'秒」「分:秒」「時間:分:秒」などの形になっているか。
     */
    public static function isValid(string $value): bool
    {
        if (! preg_match('/^(\d{1,2}[:\'])?\d{1,2}[:\']\d{1,2}(["．.]\d{1,2})?$|^\d{1,3}(["．.]\d{1,2})$/u', $value)) {
            return false;
        }
        // 「14'75」のように、分のあとの秒が60以上のものは誤り
        $parts = preg_split('/[:\']/', preg_split('/["．.]/u', $value)[0]);
        foreach (array_slice($parts, 1) as $part) {
            if ((int) $part >= 60) {
                return false;
            }
        }

        return true;
    }

    /**
     * 並べ替え用の秒数。読めなければ null。
     */
    public static function toSeconds(?string $value): ?float
    {
        $value = self::normalize($value);
        if ($value === null || ! self::isValid($value)) {
            return null;
        }
        [$main, $fraction] = array_pad(preg_split('/["．.]/u', $value, 2), 2, '0');
        $seconds = 0;
        foreach (preg_split('/[:\']/', $main) as $part) {
            $seconds = $seconds * 60 + (int) $part;
        }

        return $seconds + (float) ('0.'.$fraction);
    }
}
