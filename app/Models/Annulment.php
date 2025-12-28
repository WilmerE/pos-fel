<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annulment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_document_id',
        'user_id',
        'reason',
        'status',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if annulment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if annulment is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if annulment is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Mark annulment as approved
     */
    public function markAsApproved(): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->save();
    }

    /**
     * Mark annulment as rejected
     */
    public function markAsRejected(): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->save();
    }
}
