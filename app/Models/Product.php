<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'name',
        'description',
        'category_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function presentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get total available stock across all batches
     */
    public function getTotalAvailableStock(): int
    {
        return $this->stockBatches()->sum('quantity_available');
    }
}
