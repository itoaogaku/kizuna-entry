<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['meet_id', 'status', 'total_amount', 'payment_method', 'payment_session_id', 'payment_id', 'payment_instructions_url', 'paid_at'])]
class Entry extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_CARD = 'card';

    public const METHOD_KONBINI = 'konbini';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => '決済待ち',
        self::STATUS_PAID => '入金済み',
        self::STATUS_CANCELLED => '取消',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meet(): BelongsTo
    {
        return $this->belongsTo(Meet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EntryItem::class);
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->isKonbini() && $this->payment_instructions_url) {
            return 'コンビニ支払待ち';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function methodLabel(): string
    {
        return $this->payment_method === self::METHOD_KONBINI ? 'コンビニ払い' : 'クレジットカード';
    }

    public function isKonbini(): bool
    {
        return $this->payment_method === self::METHOD_KONBINI;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
