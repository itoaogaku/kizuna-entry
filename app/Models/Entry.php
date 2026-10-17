<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

#[Fillable(['meet_id', 'status', 'source', 'sheet_path', 'total_amount', 'payment_fee', 'payment_method', 'payment_session_id', 'payment_id', 'payment_instructions_url', 'paid_at', 'receipt_name', 'receipt_number'])]
class Entry extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_CARD = 'card';

    public const METHOD_KONBINI = 'konbini';

    public const SOURCE_WEB = 'web';

    public const SOURCE_SHEET = 'sheet';

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

    public function isFromSheet(): bool
    {
        return $this->source === self::SOURCE_SHEET;
    }

    /**
     * 申込者が状態を確認できるURL。エクセル添付（ログインなし）の場合は署名付きURL。
     */
    public function statusUrl(): string
    {
        return $this->isFromSheet()
            ? URL::signedRoute('sheet.status', $this)
            : route('entries.show', $this);
    }

    /**
     * 領収書の宛名（指定がなければ団体名、個人なら氏名）。
     */
    public function receiptAddressee(): string
    {
        return $this->receipt_name ?: $this->user->displayName();
    }

    public function receiptFilename(): string
    {
        return '領収書_'.$this->receipt_number.'.pdf';
    }

    /**
     * 申込者が領収書をダウンロードするURL。エクセル添付（ログインなし）の場合は署名付きURL。
     */
    public function receiptUrl(): string
    {
        return $this->isFromSheet()
            ? URL::signedRoute('sheet.receipt', $this)
            : route('entries.receipt', $this);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
