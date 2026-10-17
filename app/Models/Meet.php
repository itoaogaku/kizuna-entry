<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'held_on', 'venue', 'entry_opens_at', 'entry_closes_at', 'max_events_per_athlete', 'is_published', 'notes',
    'division', 'eligible', 'outline_url', 'outline_path', 'program_url', 'program_path', 'program_note',
    'result_url', 'result_path', 'photo_url', 'press_url',
    'start_list_status', 'start_list_published_at', 'program_notes',
    'start_time', 'fee_junior_high', 'fee_student', 'fee_general',
])]
class Meet extends Model
{
    use HasFactory;

    /** 大会開催ページの資料（PDFのアップロードかURL） */
    public const DOCUMENTS = ['outline' => '大会要項', 'program' => '大会プログラム', 'result' => 'リザルト'];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'entry_opens_at' => 'datetime',
            'entry_closes_at' => 'datetime',
            'is_published' => 'boolean',
            'start_list_published_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('sort_order')->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public const START_LIST_NONE = 'none';

    public const START_LIST_PROVISIONAL = 'provisional';

    public const START_LIST_FINAL = 'final';

    /** 区分別の参加料の表示名 */
    public const FEE_LABELS = ['fee_junior_high' => '中学生', 'fee_student' => '高校生・大学生', 'fee_general' => '一般・実業団'];

    /**
     * 区分別の参加料が設定されているか（要項の「中学生1,500円・高校生大学生2,200円・一般3,300円」の形）。
     */
    public function hasCategoryFees(): bool
    {
        return $this->fee_junior_high !== null || $this->fee_student !== null || $this->fee_general !== null;
    }

    /**
     * 選手の種別と種目から、その選手の参加料を決める。区分別の参加料がなければ種目の料金。
     */
    public function feeFor(?string $category, Event $event): int
    {
        if (! $this->hasCategoryFees()) {
            return (int) $event->fee;
        }
        $fee = match ($category) {
            '中学生' => $this->fee_junior_high,
            '高校生', '大学生' => $this->fee_student,
            default => $this->fee_general,
        };

        return (int) ($fee ?? $event->fee);
    }

    /**
     * 部門：中学生は「中学生の部」、それ以外は「高校生・大学生・一般の部」。組分けはこの部門ごとに分ける。
     */
    public static function divisionOf(?string $category): string
    {
        return $category === '中学生' ? 'junior' : 'open';
    }

    public const DIVISION_LABELS = ['junior' => '中学生の部', 'open' => '高校生・大学生・一般の部'];

    public function startTimeLabel(): ?string
    {
        return $this->start_time ? ltrim(substr($this->start_time, 0, 5), '0') : null;
    }

    public function races(): HasMany
    {
        return $this->hasMany(Race::class)->orderBy('sort_order')->orderBy('id');
    }

    public function pressRequests(): HasMany
    {
        return $this->hasMany(PressRequest::class)->latest();
    }

    /** タイムテーブル・スタートリストを公開しているか（暫定版を含む） */
    public function hasPublicStartList(): bool
    {
        return in_array($this->start_list_status, [self::START_LIST_PROVISIONAL, self::START_LIST_FINAL], true);
    }

    /** タイムテーブル・スタートリストが確定し、プログラムが完成しているか */
    public function isProgramFinal(): bool
    {
        return $this->start_list_status === self::START_LIST_FINAL;
    }

    /** 取材・撮影のお申込みをこのシステムのフォームで受け付けるか（外部URLがなく、大会前） */
    public function acceptsPressRequests(): bool
    {
        return ! $this->press_url && $this->stage() !== 'finished';
    }

    public function isAcceptingEntries(): bool
    {
        return $this->is_published && now()->between($this->entry_opens_at, $this->entry_closes_at);
    }

    /**
     * 大会開催ページに出す状態：open=受付中 / upcoming=受付前 / closed=受付終了 / finished=終了
     */
    public function stage(): string
    {
        return match (true) {
            $this->held_on->endOfDay()->isPast() => 'finished',
            $this->isAcceptingEntries() => 'open',
            now()->lt($this->entry_opens_at) => 'upcoming',
            default => 'closed',
        };
    }

    public function stageLabel(): string
    {
        return ['open' => 'エントリー受付中', 'upcoming' => '受付開始前', 'closed' => '受付終了', 'finished' => '大会終了'][$this->stage()];
    }

    /**
     * 資料のリンク先。PDFがアップロードされていればそれ、なければURL。
     */
    public function documentUrl(string $key): ?string
    {
        $path = $this->{$key.'_path'};
        if ($path) {
            return Storage::disk('public')->url($path);
        }

        return $this->{$key.'_url'} ?: null;
    }

    /**
     * 男子・女子それぞれが出られる種目（男女共通の種目は両方に入る）。
     *
     * @return Collection<string, Collection<int, Event>> M / F → 種目
     */
    public function eventsByGender(): Collection
    {
        return collect(['M' => '男子', 'F' => '女子'])
            ->map(fn ($label, $gender) => $this->events->filter(fn (Event $e) => $e->allowsGender($gender))->values())
            ->filter(fn ($events) => $events->isNotEmpty());
    }

    /**
     * 年度（4月始まり）。
     */
    public function fiscalYear(): int
    {
        return (int) $this->held_on->format('n') >= 4 ? (int) $this->held_on->format('Y') : (int) $this->held_on->format('Y') - 1;
    }
}
