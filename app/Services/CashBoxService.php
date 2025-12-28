<?php

namespace App\Services;

use App\Models\CashBox;
use App\Models\CashMovement;
use App\Traits\ValidatesPermissions;
use Exception;
use Illuminate\Support\Facades\DB;

class CashBoxService
{
    use ValidatesPermissions;

    /**
     * Open a new cash box
     *
     * @param int $userId
     * @param float $openingAmount
     * @return CashBox
     * @throws Exception
     */
    public function openCashBox(int $userId, float $openingAmount): CashBox
    {
        return DB::transaction(function () use ($userId, $openingAmount) {
            // Validate no other cash box is open
            $openCashBox = $this->getOpenCashBox();
            if ($openCashBox) {
                throw new Exception(
                    "Ya existe una caja abierta (#{$openCashBox->id}). Debe cerrarla antes de abrir una nueva."
                );
            }

            if ($openingAmount < 0) {
                throw new Exception("El monto de apertura no puede ser negativo.");
            }

            // Create new cash box
            $cashBox = CashBox::create([
                'opened_by' => $userId,
                'opening_amount' => $openingAmount,
                'opened_at' => now(),
            ]);

            return $cashBox;
        });
    }

    /**
     * Get currently open cash box
     *
     * @return CashBox|null
     */
    public function getOpenCashBox(): ?CashBox
    {
        return CashBox::whereNull('closed_at')
            ->orderBy('opened_at', 'desc')
            ->first();
    }

    /**
     * Validate that a cash box is open (throws exception if not)
     *
     * @throws Exception
     */
    public function validateCashBoxIsOpen(): CashBox
    {
        $cashBox = $this->getOpenCashBox();
        
        if (!$cashBox) {
            throw new Exception("💰 Para realizar esta operación primero debes abrir la caja. Ve al módulo de Caja y haz clic en 'Abrir Caja'.");
        }

        return $cashBox;
    }

    /**
     * Register income (from sale or other source)
     *
     * @param int $cashBoxId
     * @param float $amount
     * @param string $description
     * @param int $userId
     * @param int|null $saleId
     * @return CashMovement
     * @throws Exception
     */
    public function registerIncome(
        int $cashBoxId,
        float $amount,
        string $description,
        int $userId,
        ?int $saleId = null
    ): CashMovement {
        return DB::transaction(function () use ($cashBoxId, $amount, $description, $userId, $saleId) {
            $cashBox = CashBox::findOrFail($cashBoxId);

            // Validate cash box is open
            if ($cashBox->isClosed()) {
                throw new Exception("No se pueden registrar movimientos en una caja cerrada.");
            }

            if ($amount <= 0) {
                throw new Exception("El monto debe ser mayor a cero.");
            }

            // Register income movement
            $movement = CashMovement::create([
                'cash_box_id' => $cashBoxId,
                'sale_id' => $saleId,
                'user_id' => $userId,
                'type' => CashMovement::TYPE_INCOME,
                'amount' => $amount,
                'description' => $description,
            ]);

            return $movement;
        });
    }

    /**
     * Register expense
     *
     * @param int $cashBoxId
     * @param float $amount
     * @param string $description
     * @param int $userId
     * @return CashMovement
     * @throws Exception
     */
    public function registerExpense(
        int $cashBoxId,
        float $amount,
        string $description,
        int $userId
    ): CashMovement {
        return DB::transaction(function () use ($cashBoxId, $amount, $description, $userId) {
            $cashBox = CashBox::findOrFail($cashBoxId);

            // Validate cash box is open
            if ($cashBox->isClosed()) {
                throw new Exception("No se pueden registrar movimientos en una caja cerrada.");
            }

            if ($amount <= 0) {
                throw new Exception("El monto debe ser mayor a cero.");
            }

            // Register expense movement
            $movement = CashMovement::create([
                'cash_box_id' => $cashBoxId,
                'user_id' => $userId,
                'type' => CashMovement::TYPE_EXPENSE,
                'amount' => $amount,
                'description' => $description,
            ]);

            return $movement;
        });
    }

    /**
     * Register reversal (from annulled sale)
     *
     * @param int $cashBoxId
     * @param float $amount
     * @param string $description
     * @param int $userId
     * @param int $saleId
     * @return CashMovement
     * @throws Exception
     */
    public function registerReversal(
        int $cashBoxId,
        float $amount,
        string $description,
        int $userId,
        int $saleId
    ): CashMovement {
        return DB::transaction(function () use ($cashBoxId, $amount, $description, $userId, $saleId) {
            $cashBox = CashBox::findOrFail($cashBoxId);

            // Validate cash box is open
            if ($cashBox->isClosed()) {
                throw new Exception("No se pueden registrar movimientos en una caja cerrada.");
            }

            if ($amount <= 0) {
                throw new Exception("El monto debe ser mayor a cero.");
            }

            // Register reversal movement
            $movement = CashMovement::create([
                'cash_box_id' => $cashBoxId,
                'sale_id' => $saleId,
                'user_id' => $userId,
                'type' => CashMovement::TYPE_REVERSAL,
                'amount' => $amount,
                'description' => $description,
            ]);

            return $movement;
        });
    }

    /**
     * Close a cash box
     *
     * @param int $cashBoxId
     * @param int $userId
     * @param float|null $closingAmount If null, uses calculated expected amount
     * @return CashBox
     * @throws Exception
     */
    public function closeCashBox(int $cashBoxId, int $userId, ?float $closingAmount = null): CashBox
    {
        return DB::transaction(function () use ($cashBoxId, $userId, $closingAmount) {
            $cashBox = CashBox::with('movements')->findOrFail($cashBoxId);

            // Validate cash box is open
            if ($cashBox->isClosed()) {
                throw new Exception("La caja ya está cerrada.");
            }

            // Use expected amount if not provided
            $finalClosingAmount = $closingAmount ?? $cashBox->calculateExpectedClosing();

            if ($finalClosingAmount < 0) {
                throw new Exception("El monto de cierre no puede ser negativo.");
            }

            // Close cash box
            $cashBox->close($userId, $finalClosingAmount);

            return $cashBox->fresh();
        });
    }

    /**
     * Get cash box summary
     *
     * @param int $cashBoxId
     * @return array
     */
    public function getCashBoxSummary(int $cashBoxId): array
    {
        $cashBox = CashBox::with(['openedBy', 'closedBy', 'movements'])->findOrFail($cashBoxId);

        $totalIncome = $cashBox->getTotalIncome();
        $totalExpenses = $cashBox->getTotalExpenses();
        $totalReversals = $cashBox->getTotalReversals();
        $expectedClosing = $cashBox->calculateExpectedClosing();
        $difference = $cashBox->calculateDifference();

        return [
            'id' => $cashBox->id,
            'status' => $cashBox->isOpen() ? 'open' : 'closed',
            'opened_by' => $cashBox->openedBy->name,
            'closed_by' => $cashBox->closedBy?->name,
            'opened_at' => $cashBox->opened_at,
            'closed_at' => $cashBox->closed_at,
            'opening_amount' => $cashBox->opening_amount,
            'closing_amount' => $cashBox->closing_amount,
            'expected_closing' => $expectedClosing,
            'difference' => $difference,
            'totals' => [
                'income' => $totalIncome,
                'expenses' => $totalExpenses,
                'reversals' => $totalReversals,
                'net' => $totalIncome - $totalExpenses - $totalReversals,
            ],
            'movements_count' => $cashBox->movements->count(),
        ];
    }

    /**
     * Get movements from a cash box
     *
     * @param int $cashBoxId
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCashBoxMovements(int $cashBoxId, array $filters = [])
    {
        $query = CashMovement::where('cash_box_id', $cashBoxId)
            ->with(['user', 'sale'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['has_sale'])) {
            if ($filters['has_sale']) {
                $query->whereNotNull('sale_id');
            } else {
                $query->whereNull('sale_id');
            }
        }

        return $query->get();
    }

    /**
     * Get all cash boxes with optional filters
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCashBoxes(array $filters = [])
    {
        $query = CashBox::with(['openedBy', 'closedBy'])
            ->orderBy('opened_at', 'desc');

        if (isset($filters['status'])) {
            if ($filters['status'] === 'open') {
                $query->whereNull('closed_at');
            } elseif ($filters['status'] === 'closed') {
                $query->whereNotNull('closed_at');
            }
        }

        if (isset($filters['opened_by'])) {
            $query->where('opened_by', $filters['opened_by']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('opened_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('opened_at', '<=', $filters['date_to']);
        }

        return $query->get();
    }

    /**
     * Get cash box statistics
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getCashBoxStats(?string $startDate = null, ?string $endDate = null): array
    {
        $query = CashBox::query();

        if ($startDate) {
            $query->whereDate('opened_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('opened_at', '<=', $endDate);
        }

        $cashBoxes = $query->get();

        $totalIncome = 0;
        $totalExpenses = 0;
        $totalReversals = 0;

        foreach ($cashBoxes as $cashBox) {
            $totalIncome += $cashBox->getTotalIncome();
            $totalExpenses += $cashBox->getTotalExpenses();
            $totalReversals += $cashBox->getTotalReversals();
        }

        return [
            'total_cash_boxes' => $cashBoxes->count(),
            'open_cash_boxes' => $cashBoxes->where('closed_at', null)->count(),
            'closed_cash_boxes' => $cashBoxes->whereNotNull('closed_at')->count(),
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_reversals' => $totalReversals,
            'net_amount' => $totalIncome - $totalExpenses - $totalReversals,
        ];
    }
}
