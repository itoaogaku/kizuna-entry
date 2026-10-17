<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['race_id', 'entry_item_id', 'heat', 'position'])]
class StartListEntry extends Model
{
    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function entryItem(): BelongsTo
    {
        return $this->belongsTo(EntryItem::class);
    }
}
