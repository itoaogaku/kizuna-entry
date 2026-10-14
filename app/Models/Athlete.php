<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['last_name', 'first_name', 'last_kana', 'first_kana', 'gender', 'birthdate', 'category', 'jaaf_id', 'is_active'])]
class Athlete extends Model
{
    use HasFactory;

    public const GENDERS = ['M' => '男子', 'F' => '女子'];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
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

    public function fullKana(): string
    {
        return $this->last_kana.' '.$this->first_kana;
    }

    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? '';
    }
}
