<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * レース（種目×男女）。組分けとスタート時刻を持つ。
 */
#[Fillable(['event_id', 'gender', 'start_time', 'heat_size', 'heat_interval', 'fastest_last', 'sort_order'])]
class Race extends Model
{
    protected function casts(): array
    {
        return [
            'fastest_last' => 'boolean',
        ];
    }

    public function meet(): BelongsTo
    {
        return $this->belongsTo(Meet::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function startListEntries(): HasMany
    {
        return $this->hasMany(StartListEntry::class)->orderBy('heat')->orderBy('position');
    }

    /** 例：「男子 5000m」 */
    public function name(): string
    {
        return (Athlete::GENDERS[$this->gender] ?? '').'子 '.$this->event->name;
    }

    public function heatCount(): int
    {
        return (int) $this->startListEntries->max('heat');
    }

    /**
     * 何組目のスタート時刻（例：「10:20」）。開始時刻が未設定なら null。
     */
    public function heatStartTime(int $heat): ?string
    {
        if (! $this->start_time) {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', strlen($this->start_time) === 5 ? $this->start_time.':00' : $this->start_time)
            ->addMinutes(($heat - 1) * $this->heat_interval)
            ->format('G:i');
    }

    /**
     * @return Collection<int, Collection<int, StartListEntry>> 組番号 → 選手
     */
    public function heats(): Collection
    {
        return $this->startListEntries->groupBy('heat');
    }
}
