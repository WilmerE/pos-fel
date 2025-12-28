<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cashier_id',
        'customer_name',
        'customer_nit',
        'status',
        'subtotal',
        'tax',
        'total',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ANNULLED = 'annulled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function fiscalDocument(): HasOne
    {
        return $this->hasOne(FiscalDocument::class);
    }

    /**
     * Check if sale is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if sale is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if sale is annulled
     */
    public function isAnnulled(): bool
    {
        return $this->status === self::STATUS_ANNULLED;
    }

    /**
     * Check if sale has fiscal document
     */
    public function hasFiscalDocument(): bool
    {
        return $this->fiscalDocument()->exists();
    }

    /**
     * Mark sale as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->save();
    }

    /**
     * Mark sale as annulled
     */
    public function markAsAnnulled(): void
    {
        $this->status = self::STATUS_ANNULLED;
        $this->save();
    }

    /**
     * Calculate totals based on items
     */
    public function calculateTotals(float $taxRate = 0.12): void
    {
        $subtotal = $this->items->sum('total');
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;

        $this->subtotal = $subtotal;
        $this->tax = $tax;
        $this->total = $total;
        $this->save();
    }
}
