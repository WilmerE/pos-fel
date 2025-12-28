<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Traits\ValidatesPermissions;
use Exception;
use Illuminate\Support\Facades\DB;

class SaleService
{
    use ValidatesPermissions;

    protected StockService $stockService;
    protected CashBoxService $cashBoxService;

    public function __construct(StockService $stockService, CashBoxService $cashBoxService)
    {
        $this->stockService = $stockService;
        $this->cashBoxService = $cashBoxService;
    }

    /**
     * Create a new sale
     *
     * @param int $userId User who is making the sale
     * @param int|null $cashierId Cashier (defaults to userId)
     * @param string $customerName Customer name
     * @param string|null $customerNit Customer NIT
     * @return Sale
     */
    public function createSale(
        int $userId, 
        ?int $cashierId = null,
        string $customerName = '',
        ?string $customerNit = null
    ): Sale
    {
        return Sale::create([
            'user_id' => $userId,
            'cashier_id' => $cashierId ?? $userId,
            'customer_name' => $customerName,
            'customer_nit' => $customerNit,
            'status' => Sale::STATUS_PENDING,
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
        ]);
    }

    /**
     * Add an item to a sale
     *
     * @param int $saleId
     * @param int $productId
     * @param int $presentationId
     * @param int $quantity
     * @return SaleItem
     * @throws Exception
     */
    public function addItem(
        int $saleId,
        int $productId,
        int $presentationId,
        int $quantity
    ): SaleItem {
        return DB::transaction(function () use (
            $saleId,
            $productId,
            $presentationId,
            $quantity
        ) {
            // Validate sale
            $sale = Sale::findOrFail($saleId);

            if (!$sale->isPending()) {
                throw new Exception("No se pueden agregar items a una venta que no está pendiente.");
            }

            // Validate product
            $product = Product::findOrFail($productId);

            if (!$product->active) {
                throw new Exception("El producto '{$product->name}' está inactivo.");
            }

            // Validate presentation
            $presentation = ProductPresentation::where('id', $presentationId)
                ->where('product_id', $productId)
                ->firstOrFail();

            if ($quantity <= 0) {
                throw new Exception("La cantidad debe ser mayor a cero.");
            }

            // Calculate quantity in base units
            $baseUnitsQuantity = $presentation->toBaseUnits($quantity);

            // Validate stock availability
            if (!$this->stockService->hasSufficientStock($productId, $baseUnitsQuantity)) {
                $available = $this->stockService->getAvailableStock($productId);
                
                // Format message based on available stock
                if ($available == 0) {
                    throw new Exception(
                        "⚠️ Sin existencias de '{$product->name}'. Por favor agregue stock antes de vender este producto."
                    );
                }
                
                throw new Exception(
                    "⚠️ Stock insuficiente de '{$product->name}'. Disponible: {$available} unidades, Solicitado: {$baseUnitsQuantity} unidades."
                );
            }

            // Create sale item
            $saleItem = SaleItem::create([
                'sale_id' => $saleId,
                'product_id' => $productId,
                'presentation_id' => $presentationId,
                'quantity' => $quantity,
                'unit_price' => $presentation->price,
                'total' => $quantity * $presentation->price,
            ]);

            // Recalculate sale totals
            $sale->calculateTotals();

            return $saleItem;
        });
    }

    /**
     * Update item quantity
     *
     * @param int $saleItemId
     * @param int $newQuantity
     * @return SaleItem
     * @throws Exception
     */
    public function updateItemQuantity(int $saleItemId, int $newQuantity): SaleItem
    {
        return DB::transaction(function () use ($saleItemId, $newQuantity) {
            $saleItem = SaleItem::findOrFail($saleItemId);
            $sale = $saleItem->sale;

            if (!$sale->isPending()) {
                throw new Exception("No se pueden modificar items de una venta que no está pendiente.");
            }

            if ($newQuantity <= 0) {
                throw new Exception("La cantidad debe ser mayor a cero.");
            }

            // Validate stock availability
            $baseUnitsQuantity = $saleItem->presentation->toBaseUnits($newQuantity);
            if (!$this->stockService->hasSufficientStock($saleItem->product_id, $baseUnitsQuantity)) {
                $available = $this->stockService->getAvailableStock($saleItem->product_id);
                throw new Exception(
                    "Stock insuficiente. Disponible: {$available} unidades base, Requerido: {$baseUnitsQuantity}"
                );
            }

            // Update quantity and recalculate total
            $saleItem->quantity = $newQuantity;
            $saleItem->calculateTotal();

            // Recalculate sale totals
            $sale->calculateTotals();

            return $saleItem->fresh();
        });
    }

    /**
     * Remove an item from a sale
     *
     * @param int $saleItemId
     * @return bool
     * @throws Exception
     */
    public function removeItem(int $saleItemId): bool
    {
        return DB::transaction(function () use ($saleItemId) {
            $saleItem = SaleItem::findOrFail($saleItemId);
            $sale = $saleItem->sale;

            if (!$sale->isPending()) {
                throw new Exception("No se pueden eliminar items de una venta que no está pendiente.");
            }

            $saleItem->delete();

            // Recalculate sale totals
            $sale->calculateTotals();

            return true;
        });
    }

    /**
     * Confirm sale and consume stock
     *
     * @param int $saleId
     * @return Sale
     * @throws Exception
     */
    public function confirmSale(int $saleId): Sale
    {
        return DB::transaction(function () use ($saleId) {
            $sale = Sale::with(['items.presentation', 'items.product'])->findOrFail($saleId);

            // Validate cash box is open
            $cashBox = $this->cashBoxService->validateCashBoxIsOpen();

            if (!$sale->isPending()) {
                throw new Exception("Solo se pueden confirmar ventas pendientes.");
            }

            if ($sale->items->isEmpty()) {
                throw new Exception("No se puede confirmar una venta sin items.");
            }

            // Consume stock for each item using FIFO
            foreach ($sale->items as $item) {
                $baseUnitsQuantity = $item->getBaseUnitsQuantity();

                // Consume stock using FIFO
                $this->stockService->consumeStockFIFO(
                    productId: $item->product_id,
                    quantity: $baseUnitsQuantity,
                    userId: $sale->user_id,
                    referenceType: 'sale',
                    referenceId: $sale->id
                );
            }

            // Mark sale as completed
            $sale->markAsCompleted();

            // Register income in cash box
            $this->cashBoxService->registerIncome(
                cashBoxId: $cashBox->id,
                amount: (float) $sale->total,
                description: "Venta #{$sale->id}",
                userId: $sale->user_id,
                saleId: $sale->id
            );

            return $sale->fresh();
        });
    }

    /**
     * Cancel a sale (only if not invoiced)
     *
     * @param int $saleId
     * @return Sale
     * @throws Exception
     */
    public function cancelSale(int $saleId): Sale
    {
        return DB::transaction(function () use ($saleId) {
            $sale = Sale::findOrFail($saleId);

            if ($sale->isAnnulled()) {
                throw new Exception("La venta ya está anulada.");
            }

            // Cannot cancel if fiscal document exists
            if ($sale->hasFiscalDocument()) {
                throw new Exception(
                    "No se puede cancelar una venta facturada. Use el proceso de anulación FEL."
                );
            }

            // If sale was completed, revert stock
            if ($sale->isCompleted()) {
                $this->stockService->revertStockByReference(
                    referenceType: 'sale',
                    referenceId: $sale->id,
                    userId: $sale->user_id
                );
            }

            // Mark sale as annulled
            $sale->markAsAnnulled();

            return $sale->fresh();
        });
    }

    /**
     * Get sale summary
     *
     * @param int $saleId
     * @return array
     */
    public function getSaleSummary(int $saleId): array
    {
        $sale = Sale::with(['items.product', 'items.presentation', 'user', 'cashier'])
            ->findOrFail($saleId);

        return [
            'id' => $sale->id,
            'status' => $sale->status,
            'customer_name' => $sale->customer_name,
            'customer_nit' => $sale->customer_nit,
            'user' => $sale->user->name,
            'cashier' => $sale->cashier->name,
            'items_count' => $sale->items->count(),
            'items' => $sale->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'presentation_id' => $item->presentation_id,
                    'product' => $item->product->name,
                    'presentation' => $item->presentation->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->total,
                ];
            }),
            'subtotal' => $sale->subtotal,
            'tax' => $sale->tax,
            'total' => $sale->total,
            'created_at' => $sale->created_at,
        ];
    }

    /**
     * Get all pending sales
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingSales()
    {
        return Sale::where('status', Sale::STATUS_PENDING)
            ->with(['user', 'cashier', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Calculate sale totals with custom tax rate
     *
     * @param int $saleId
     * @param float $taxRate
     * @return Sale
     */
    public function recalculateTotals(int $saleId, float $taxRate = 0.12): Sale
    {
        $sale = Sale::findOrFail($saleId);
        $sale->calculateTotals($taxRate);
        return $sale->fresh();
    }
}
