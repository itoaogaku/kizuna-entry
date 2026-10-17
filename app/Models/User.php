<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 申込者のアカウント。団体（代表者が所属選手をまとめて申し込む）と個人の2種類。
 */
#[Fillable(['type', 'name', 'team_name', 'team_kana', 'phone', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const TYPE_TEAM = 'team';

    public const TYPE_INDIVIDUAL = 'individual';

    /** エクセル添付だけで申し込んだ人（ログインはしない） */
    public const TYPE_SHEET = 'sheet';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'grades_updated_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // 団体は団体名でログインするので、表記ゆれをそろえた名前を保存しておく
        static::saving(function (User $user) {
            $user->login_name = $user->isTeam() && $user->team_name !== null
                ? self::normalizeTeamName($user->team_name)
                : null;
        });
    }

    /**
     * 全角・半角、大文字・小文字、空白の違いを無視して団体名を比べるための形にする。
     */
    public static function normalizeTeamName(string $name): string
    {
        $name = mb_convert_kana($name, 'asKV');

        return mb_strtolower(preg_replace('/\s+/u', '', $name));
    }

    public static function findTeamByName(string $name): ?self
    {
        return self::where('type', self::TYPE_TEAM)
            ->where('login_name', self::normalizeTeamName($name))
            ->first();
    }

    public function isTeam(): bool
    {
        return ($this->type ?? self::TYPE_TEAM) === self::TYPE_TEAM;
    }

    public function isIndividual(): bool
    {
        return $this->type === self::TYPE_INDIVIDUAL;
    }

    /**
     * 画面やメールに出す申込者名（団体名、個人なら本人氏名）。
     */
    public function isSheetOnly(): bool
    {
        return $this->type === self::TYPE_SHEET;
    }

    public function displayName(): string
    {
        return $this->isIndividual() ? $this->name : (string) ($this->team_name ?: $this->name);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_INDIVIDUAL => '個人',
            self::TYPE_SHEET => 'エクセル',
            default => '団体',
        };
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
