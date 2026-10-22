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
    'outline_note', 'timetable_url', 'timetable_path', 'timetable_note', 'startlist_url', 'startlist_path', 'startlist_note', 'result_note',
    'start_time', 'fee_junior_high', 'fee_student', 'fee_general',
])]
class Meet extends Model
{
    use HasFactory;

    /**
     * 大会開催ページの資料（PDFのアップロードかURL）。この順でボタンを並べる。
     * タイムテーブル・組編成・プログラムは、エントリーデータをExcelで出力・調整して作ったPDFを貼る。
     * リザルトは写真判定機から出力したPDFを貼る。
     */
    public const DOCUMENTS = [
        'outline' => '大会要項',
        'timetable' => 'タイムテーブル',
        'startlist' => '組編成（スタートリスト）',
        'program' => '大会プログラム',
        'result' => 'リザルト',
    ];

    /** 大会開催ページのボタンに出す短い名前 */
    public const DOCUMENT_BUTTONS = ['startlist' => 'スタートリスト'];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'entry_opens_at' => 'datetime',
            'entry_closes_at' => 'datetime',
            'is_published' => 'boolean',
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
        // 中学生の料金がない大会（中学生の部がない種目）に中学生が出る場合は、高校生・大学生の料金で受け付ける
        $fee = match ($category) {
            '中学生' => $this->fee_junior_high ?? $this->fee_student ?? $this->fee_general,
            '高校生', '大学生' => $this->fee_student ?? $this->fee_general,
            default => $this->fee_general ?? $this->fee_student,
        };

        return (int) ($fee ?? $event->fee);
    }

    /**
     * 部門：中学生は「中学生の部」、それ以外は「高校生・大学生・一般の部」。
     * レースは一緒に走り（組は種目×性別）、表彰を部門ごとに行う。
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

    public function pressRequests(): HasMany
    {
        return $this->hasMany(PressRequest::class)->latest();
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
     * 資料ボタンに添えるメモ（例：0628更新、暫定版）。
     */
    public function documentNote(string $key): ?string
    {
        return $this->{$key.'_note'} ?: null;
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
