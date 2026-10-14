<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'held_on', 'venue', 'entry_opens_at', 'entry_closes_at', 'max_events_per_athlete', 'is_published', 'notes'])]
class Meet extends Model
{
    use HasFactory;

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
}
