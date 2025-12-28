<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'batch_number',
        'expiration_date',
        'quantity_initial',
        'quantity_available',
        'location',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'quantity_initial' => 'integer',
        'quantity_available' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Check if batch has available stock
     */
    public function hasAvailableStock(): bool
    {
        return $this->quantity_available > 0;
    }

    /**
     * Decrease available quantity
     */
    public function decreaseStock(int $quantity): void
    {
        $this->quantity_available -= $quantity;
        $this->save();
    }

    /**
     * Increase available quantity
     */
    public function increaseStock(int $quantity): void
    {
        $this->quantity_available += $quantity;
        $this->save();
    }
}
