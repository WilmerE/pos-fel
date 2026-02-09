<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_name',
        'source_type',
        'total_items',
        'total_products_created',
        'total_products_updated',
        'total_categories_created',
        'total_suppliers_created',
        'total_presentations_created',
        'total_stock_batches_created',
        'imported_by',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    /**
     * Get the user who imported
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
