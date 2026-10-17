<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['last_name', 'first_name', 'kana', 'grade', 'gender', 'association', 'category', 'birthdate', 'jaaf_id', 'is_active'])]
class Athlete extends Model
{
    use HasFactory;

    public const GENDERS = ['M' => '男', 'F' => '女'];

    /** エントリーシートの「種別」 */
    public const CATEGORIES = ['中学生', '高校生', '大学生', '一般'];

    /** 学年（大学院は M1・M2・D1〜D3） */
    public const GRADES = ['1', '2', '3', '4', '5', '6', 'M1', 'M2', 'D1', 'D2', 'D3'];

    /** 所属陸協（都道府県陸上競技協会。学生は「学連」で登録している場合がある） */
    public const ASSOCIATIONS = [
        '学連',
        '北海道', '青森', '岩手', '宮城', '秋田', '山形', '福島',
        '茨城', '栃木', '群馬', '埼玉', '千葉', '東京', '神奈川',
        '新潟', '富山', '石川', '福井', '山梨', '長野', '岐阜', '静岡', '愛知',
        '三重', '滋賀', '京都', '大阪', '兵庫', '奈良', '和歌山',
        '鳥取', '島根', '岡山', '広島', '山口',
        '徳島', '香川', '愛媛', '高知',
        '福岡', '佐賀', '長崎', '熊本', '大分', '宮崎', '鹿児島', '沖縄',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'grade' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return $this->last_name.' '.$this->first_name;
    }

    /**
     * 表彰の部門（中学生の部／高校生・大学生・一般の部）。
     */
    public function divisionLabel(): string
    {
        return Meet::divisionOf($this->category) === 'junior' ? '中学生の部' : '高校・大学・一般の部';
    }

    /** 例：「2年」「M1」 */
    public function gradeLabel(): string
    {
        return self::formatGrade($this->grade);
    }

    public static function formatGrade(?string $grade): string
    {
        if ($grade === null || $grade === '') {
            return '';
        }

        return ctype_digit($grade) ? $grade.'年' : $grade;
    }

    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? '';
    }
}
