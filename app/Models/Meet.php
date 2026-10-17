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
