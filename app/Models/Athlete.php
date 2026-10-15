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

    /** 所属陸協（都道府県陸上競技協会） */
    public const ASSOCIATIONS = [
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
            'grade' => 'integer',
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

    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? '';
    }
}
