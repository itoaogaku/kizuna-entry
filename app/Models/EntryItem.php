<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['athlete_id', 'event_id', 'declared_record', 'personal_best', 'pr_comment', 'fee'])]
class EntryItem extends Model
{
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
