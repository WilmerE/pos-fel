<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'opened_by',
        'closed_by',
        'opening_amount',
        'closing_amount',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * Check if cash box is open
     */
    public function isOpen(): bool
    {
        return is_null($this->closed_at);
    }

    /**
     * Check if cash box is closed
     */
    public function isClosed(): bool
    {
        return !is_null($this->closed_at);
    }

    /**
     * Get total income (ventas + otros ingresos)
     */
    public function getTotalIncome(): float
    {
        return (float) $this->movements()
            ->where('type', CashMovement::TYPE_INCOME)
            ->sum('amount');
    }

    /**
     * Get total expenses
     */
    public function getTotalExpenses(): float
    {
        return (float) $this->movements()
            ->where('type', CashMovement::TYPE_EXPENSE)
            ->sum('amount');
    }

    /**
     * Get total reversals
     */
    public function getTotalReversals(): float
    {
        return (float) $this->movements()
            ->where('type', CashMovement::TYPE_REVERSAL)
            ->sum('amount');
    }

    /**
     * Calculate expected closing amount
     */
    public function calculateExpectedClosing(): float
    {
        $income = $this->getTotalIncome();
        $expenses = $this->getTotalExpenses();
        $reversals = $this->getTotalReversals();

        return $this->opening_amount + $income - $expenses - $reversals;
    }

    /**
     * Calculate difference (real vs expected)
     */
    public function calculateDifference(): ?float
    {
        if ($this->isClosed() && !is_null($this->closing_amount)) {
            return $this->closing_amount - $this->calculateExpectedClosing();
        }

        return null;
    }

    /**
     * Mark as closed
     */
    public function close(int $userId, float $closingAmount): void
    {
        $this->closed_by = $userId;
        $this->closing_amount = $closingAmount;
        $this->closed_at = now();
        $this->save();
    }
}
