<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Traits\ValidatesPermissions;
use Exception;
use Illuminate\Support\Facades\DB;

class StockService
{
    use ValidatesPermissions;
    /**
     * Add stock to a new or existing batch
     *
     * @param int $productId
     * @param string $batchNumber
     * @param string|null $expirationDate
     * @param int $quantity
     * @param int $userId
     * @param string|null $location
     * @return StockBatch
     * @throws Exception
     */
    public function addStock(
        int $productId,
        string $batchNumber,
        ?string $expirationDate,
        int $quantity,
        int $userId,
        ?string $location = null
    ): StockBatch {
        return DB::transaction(function () use (
            $productId,
            $batchNumber,
            $expirationDate,
            $quantity,
            $userId,
            $location
        ) {
            // Validate product exists
            $product = Product::findOrFail($productId);

            if (!$product->active) {
                throw new Exception("El producto está inactivo y no puede recibir stock.");
            }

            if ($quantity <= 0) {
                throw new Exception("La cantidad debe ser mayor a cero.");
            }

            // Create or update stock batch
            $stockBatch = StockBatch::firstOrNew([
                'product_id' => $productId,
                'batch_number' => $batchNumber,
            ]);

            if ($stockBatch->exists) {
                // Update existing batch
                $stockBatch->quantity_available += $quantity;
                $stockBatch->save();
            } else {
                // Create new batch
                $stockBatch->fill([
                    'expiration_date' => $expirationDate,
                    'quantity_initial' => $quantity,
                    'quantity_available' => $quantity,
                    'location' => $location,
                ]);
                $stockBatch->save();
            }

            // Register stock movement
            StockMovement::create([
                'product_id' => $productId,
                'stock_batch_id' => $stockBatch->id,
                'user_id' => $userId,
                'type' => StockMovement::TYPE_IN,
                'quantity' => $quantity,
                'reference_type' => 'stock_entry',
                'reference_id' => $stockBatch->id,
                'notes' => "Ingreso de stock - Lote: {$batchNumber}",
            ]);

            return $stockBatch->fresh();
        });
    }

    /**
     * Consume stock using FIFO (First to Expire, First Out)
     *
     * @param int $productId
     * @param int $quantity Quantity in base units
     * @param int $userId
     * @param string $referenceType
     * @param int $referenceId
     * @return array Array of consumed batches with quantities
     * @throws Exception
     */
    public function consumeStockFIFO(
        int $productId,
        int $quantity,
        int $userId,
        string $referenceType,
        int $referenceId
    ): array {
        return DB::transaction(function () use (
            $productId,
            $quantity,
            $userId,
            $referenceType,
            $referenceId
        ) {
            // Validate product exists
            $product = Product::findOrFail($productId);

            if ($quantity <= 0) {
                throw new Exception("La cantidad a descontar debe ser mayor a cero.");
            }

            // Get available batches ordered by expiration date (FIFO)
            $batches = StockBatch::where('product_id', $productId)
                ->where('quantity_available', '>', 0)
                ->orderBy('expiration_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            // Check if there's enough stock
            $totalAvailable = $batches->sum('quantity_available');
            if ($totalAvailable < $quantity) {
                throw new Exception(
                    "Stock insuficiente para el producto. Disponible: {$totalAvailable}, Requerido: {$quantity}"
                );
            }

            $remainingQuantity = $quantity;
            $consumedBatches = [];

            foreach ($batches as $batch) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                // Determine how much to take from this batch
                $quantityToConsume = min($remainingQuantity, $batch->quantity_available);

                // Decrease batch stock
                $batch->decreaseStock($quantityToConsume);

                // Register stock movement
                StockMovement::create([
                    'product_id' => $productId,
                    'stock_batch_id' => $batch->id,
                    'user_id' => $userId,
                    'type' => StockMovement::TYPE_OUT,
                    'quantity' => $quantityToConsume,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => "Consumo FIFO - {$referenceType} #{$referenceId}",
                ]);

                $consumedBatches[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $quantityToConsume,
                    'expiration_date' => $batch->expiration_date,
                ];

                $remainingQuantity -= $quantityToConsume;
            }

            return $consumedBatches;
        });
    }

    /**
     * Revert stock movements by reference (used for sale annulments)
     *
     * @param string $referenceType
     * @param int $referenceId
     * @param int $userId
     * @return int Number of movements reversed
     * @throws Exception
     */
    public function revertStockByReference(
        string $referenceType,
        int $referenceId,
        int $userId
    ): int {
        return DB::transaction(function () use ($referenceType, $referenceId, $userId) {
            // Get all OUT movements for this reference
            $movements = StockMovement::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('type', StockMovement::TYPE_OUT)
                ->get();

            if ($movements->isEmpty()) {
                throw new Exception(
                    "No se encontraron movimientos de stock para revertir: {$referenceType} #{$referenceId}"
                );
            }

            $reversedCount = 0;

            foreach ($movements as $movement) {
                // Return stock to the original batch
                $batch = StockBatch::findOrFail($movement->stock_batch_id);
                $batch->increaseStock($movement->quantity);

                // Create reversal movement
                StockMovement::create([
                    'product_id' => $movement->product_id,
                    'stock_batch_id' => $movement->stock_batch_id,
                    'user_id' => $userId,
                    'type' => StockMovement::TYPE_REVERSAL,
                    'quantity' => $movement->quantity,
                    'reference_type' => 'annulment',
                    'reference_id' => $referenceId,
                    'notes' => "Reversión de {$referenceType} #{$referenceId} - Movimiento original #{$movement->id}",
                ]);

                $reversedCount++;
            }

            return $reversedCount;
        });
    }

    /**
     * Adjust stock manually (for inventory corrections)
     *
     * @param int $productId
     * @param int $stockBatchId
     * @param int $quantity Positive or negative adjustment
     * @param int $userId
     * @param string $reason
     * @return StockMovement
     * @throws Exception
     */
    public function adjustStock(
        int $productId,
        int $stockBatchId,
        int $quantity,
        int $userId,
        string $reason
    ): StockMovement {
        return DB::transaction(function () use (
            $productId,
            $stockBatchId,
            $quantity,
            $userId,
            $reason
        ) {
            $batch = StockBatch::where('id', $stockBatchId)
                ->where('product_id', $productId)
                ->firstOrFail();

            if ($quantity == 0) {
                throw new Exception("La cantidad de ajuste no puede ser cero.");
            }

            // Apply adjustment
            if ($quantity > 0) {
                $batch->increaseStock($quantity);
            } else {
                $quantityToDecrease = abs($quantity);
                if ($batch->quantity_available < $quantityToDecrease) {
                    throw new Exception("No hay suficiente stock disponible para este ajuste negativo.");
                }
                $batch->decreaseStock($quantityToDecrease);
            }

            // Register adjustment movement
            return StockMovement::create([
                'product_id' => $productId,
                'stock_batch_id' => $stockBatchId,
                'user_id' => $userId,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => abs($quantity),
                'reference_type' => 'adjustment',
                'reference_id' => null,
                'notes' => "Ajuste de inventario: {$reason}",
            ]);
        });
    }

    /**
     * Get available stock for a product
     *
     * @param int $productId
     * @return int Total available quantity
     */
    public function getAvailableStock(int $productId): int
    {
        return StockBatch::where('product_id', $productId)
            ->sum('quantity_available');
    }

    /**
     * Get stock batches for a product ordered by FIFO
     *
     * @param int $productId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStockBatchesFIFO(int $productId)
    {
        return StockBatch::where('product_id', $productId)
            ->where('quantity_available', '>', 0)
            ->orderBy('expiration_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Check if product has sufficient stock
     *
     * @param int $productId
     * @param int $quantity
     * @return bool
     */
    public function hasSufficientStock(int $productId, int $quantity): bool
    {
        return $this->getAvailableStock($productId) >= $quantity;
    }
}
