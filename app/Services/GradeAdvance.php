<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 年度更新：学年を1つ上げる。卒業学年の選手は「対象外にする／進学して続ける／変更しない」を選ぶ。
 */
class GradeAdvance
{
    public const RETIRE = 'retire';

    public const PROMOTE = 'promote';

    public const KEEP = 'keep';

    /** 種別ごとの最終学年と、進学後の種別 */
    private const LAST_GRADE = ['中学生' => 3, '高校生' => 3, '大学生' => 4];

    private const NEXT_CATEGORY = ['中学生' => '高校生', '高校生' => '大学生', '大学生' => '一般'];

    /**
     * 年度（4月始まり）。
     */
    public static function fiscalYear(?\DateTimeInterface $date = null): int
    {
        $date ??= now();

        return (int) $date->format('n') >= 4 ? (int) $date->format('Y') : (int) $date->format('Y') - 1;
    }

    public static function isDoneThisYear(User $user): bool
    {
        return $user->grades_updated_year !== null && $user->grades_updated_year >= self::fiscalYear();
    }

    /**
     * 新年度（4〜6月）で、学年のある選手がいるのに年度更新をしていない。
     */
    public static function isDue(User $user): bool
    {
        return in_array((int) now()->format('n'), [4, 5, 6], true)
            && ! self::isDoneThisYear($user)
            && $user->athletes()->where('is_active', true)->whereNotNull('grade')->exists();
    }

    public static function isGraduating(Athlete $athlete): bool
    {
        $grade = (string) $athlete->grade;
        if ($grade === '') {
            return false;
        }
        // 大学院：修士2年・博士3年で修了
        if (in_array($grade, ['M2', 'D3'], true)) {
            return true;
        }
        if (! ctype_digit($grade)) {
            return false;
        }
        $last = self::LAST_GRADE[$athlete->category] ?? null;

        return $last !== null && (int) $grade >= $last;
    }

    /**
     * 1つ上の学年（「2」→「3」、「M1」→「M2」）。
     */
    public static function nextGrade(string $grade): string
    {
        if (preg_match('/^([MD])(\d)$/', $grade, $m)) {
            return $m[1].((int) $m[2] + 1);
        }

        return (string) min(6, (int) $grade + 1);
    }

    /**
     * 画面に出す変更内容の一覧。
     *
     * @return Collection<int, array{athlete: Athlete, graduating: bool, after: string}>
     */
    public function preview(User $user): Collection
    {
        return $user->athletes()->where('is_active', true)->whereNotNull('grade')
            ->orderBy('category')->orderByDesc('grade')->orderBy('kana')
            ->get()
            ->map(fn (Athlete $a) => [
                'athlete' => $a,
                'graduating' => self::isGraduating($a),
                'after' => self::isGraduating($a) ? '' : $a->category.' '.Athlete::formatGrade(self::nextGrade((string) $a->grade)),
                'promoteLabel' => self::promoted($a)['category'].(self::promoted($a)['grade'] ? ' '.Athlete::formatGrade(self::promoted($a)['grade']) : ''),
            ]);
    }

    /**
     * @param  array<int|string, string>  $choices  卒業学年の選手ID → retire / promote / keep
     * @return array{advanced: int, retired: int, promoted: int}
     */
    public function apply(User $user, array $choices): array
    {
        $result = ['advanced' => 0, 'retired' => 0, 'promoted' => 0];

        DB::transaction(function () use ($user, $choices, &$result) {
            foreach ($user->athletes()->where('is_active', true)->whereNotNull('grade')->get() as $athlete) {
                if (! self::isGraduating($athlete)) {
                    $athlete->update(['grade' => self::nextGrade((string) $athlete->grade)]);
                    $result['advanced']++;

                    continue;
                }
                match ($choices[$athlete->id] ?? self::RETIRE) {
                    self::PROMOTE => $athlete->update(self::promoted($athlete)) && $result['promoted']++,
                    self::KEEP => null,
                    default => $athlete->update(['is_active' => false]) && $result['retired']++,
                };
            }
            $user->forceFill(['grades_updated_year' => self::fiscalYear()])->save();
        });

        return $result;
    }

    /**
     * 卒業後に進学して続ける場合の種別・学年（大学院修了・大学卒業は一般）。
     *
     * @return array{category: string, grade: ?string}
     */
    private static function promoted(Athlete $athlete): array
    {
        if (in_array((string) $athlete->grade, ['M2', 'D3'], true)) {
            return ['category' => '一般', 'grade' => null];
        }
        $next = self::NEXT_CATEGORY[$athlete->category] ?? $athlete->category;

        return ['category' => $next, 'grade' => $next === '一般' ? null : '1'];
    }
}
