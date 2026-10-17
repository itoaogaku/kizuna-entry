<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_name', 'contact_name', 'email', 'phone', 'coverage', 'people', 'purpose', 'notes'])]
class PressRequest extends Model
{
    public const COVERAGES = ['取材', '撮影', '取材・撮影'];

    public function meet(): BelongsTo
    {
        return $this->belongsTo(Meet::class);
    }
}
