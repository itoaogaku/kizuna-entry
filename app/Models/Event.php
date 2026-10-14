<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'gender', 'fee', 'capacity', 'sort_order'])]
class Event extends Model
{
    use HasFactory;

    public function meet(): BelongsTo
    {
        return $this->belongsTo(Meet::class);
    }

    public function entryItems(): HasMany
    {
        return $this->hasMany(EntryItem::class);
    }

    /**
     * 定員の計算に含める件数（入金済み＋決済手続き中）。
     */
    public function reservedCount(): int
    {
        return $this->entryItems()
            ->whereHas('entry', fn ($q) => $q->whereIn('status', [Entry::STATUS_PENDING, Entry::STATUS_PAID]))
            ->count();
    }

    public function allowsGender(string $gender): bool
    {
        return $this->gender === null || $this->gender === $gender;
    }
}
