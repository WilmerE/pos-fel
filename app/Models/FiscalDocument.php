<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FiscalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'uuid',
        'serie',
        'number',
        'status',
        'xml',
        'pdf_path',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_ANNULLED = 'annulled';
    const STATUS_REJECTED = 'rejected';

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function annulment(): HasOne
    {
        return $this->hasOne(Annulment::class);
    }

    /**
     * Check if document is authorized
     */
    public function isAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    /**
     * Check if document is annulled
     */
    public function isAnnulled(): bool
    {
        return $this->status === self::STATUS_ANNULLED;
    }

    /**
     * Check if document can be annulled
     */
    public function canBeAnnulled(): bool
    {
        return $this->isAuthorized() && !$this->hasAnnulment();
    }

    /**
     * Check if document has annulment request
     */
    public function hasAnnulment(): bool
    {
        return $this->annulment()->exists();
    }

    /**
     * Mark document as annulled
     */
    public function markAsAnnulled(): void
    {
        $this->status = self::STATUS_ANNULLED;
        $this->save();
    }

    /**
     * Mark document as authorized
     */
    public function markAsAuthorized(): void
    {
        $this->status = self::STATUS_AUTHORIZED;
        $this->save();
    }

    /**
     * Mark document as rejected
     */
    public function markAsRejected(): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->save();
    }
}
