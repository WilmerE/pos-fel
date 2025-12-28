<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPresentation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'factor',
        'price',
    ];

    protected $casts = [
        'factor' => 'integer',
        'price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Convert presentation quantity to base units
     */
    public function toBaseUnits(int $quantity): int
    {
        return $quantity * $this->factor;
    }
}
