<?php

namespace App\Services;

use App\Models\Annulment;
use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Traits\ValidatesPermissions;
use Exception;
use Illuminate\Support\Facades\DB;

class SaleAnnulmentService
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
     * Annul a sale that has been invoiced with FEL
     *
     * @param int $saleId
     * @param int $userId User requesting the annulment
     * @param string $reason Reason for annulment
     * @return Annulment
     * @throws Exception
     */
    public function annulSale(int $saleId, int $userId, string $reason): Annulment
    {
        return DB::transaction(function () use ($saleId, $userId, $reason) {
            // Validate sale exists
            $sale = Sale::with('fiscalDocument')->findOrFail($saleId);

            // Validate sale status
            if ($sale->isAnnulled()) {
                throw new Exception("La venta ya está anulada.");
            }

            if (!$sale->isCompleted()) {
                throw new Exception("Solo se pueden anular ventas completadas. Use cancelSale() para ventas pendientes.");
            }

            // Validate fiscal document exists
            if (!$sale->hasFiscalDocument()) {
                throw new Exception(
                    "La venta no tiene documento fiscal. Use el método cancelSale() del SaleService."
                );
            }

            $fiscalDocument = $sale->fiscalDocument;

            // Validate fiscal document can be annulled
            if (!$fiscalDocument->canBeAnnulled()) {
                if ($fiscalDocument->isAnnulled()) {
                    throw new Exception("El documento fiscal ya está anulado.");
                }
                throw new Exception("El documento fiscal no puede ser anulado en su estado actual.");
            }

            // Validate reason
            if (empty(trim($reason))) {
                throw new Exception("Debe proporcionar un motivo para la anulación.");
            }

            // Create annulment request
            $annulment = Annulment::create([
                'fiscal_document_id' => $fiscalDocument->id,
                'user_id' => $userId,
                'reason' => $reason,
                'status' => Annulment::STATUS_PENDING,
            ]);

            // Process annulment (in real scenario, this would communicate with FEL certifier)
            $this->processAnnulment($annulment, $sale, $userId);

            return $annulment->fresh();
        });
    }

    /**
     * Process the annulment (internal method)
     * In production, this would communicate with the FEL certifier
     *
     * @param Annulment $annulment
     * @param Sale $sale
     * @param int $userId
     * @return void
     * @throws Exception
     */
    protected function processAnnulment(Annulment $annulment, Sale $sale, int $userId): void
    {
        // TODO: In production, send annulment request to FEL certifier
        // For now, we automatically approve it

        try {
            // Mark annulment as approved
            $annulment->markAsApproved();

            // Revert stock using StockService
            $reversedCount = $this->stockService->revertStockByReference(
                referenceType: 'sale',
                referenceId: $sale->id,
                userId: $userId
            );

            if ($reversedCount === 0) {
                throw new Exception("No se pudieron revertir los movimientos de stock.");
            }

            // Mark fiscal document as annulled
            $sale->fiscalDocument->markAsAnnulled();

            // Mark sale as annulled
            $sale->markAsAnnulled();

            // Register reversal in cash box (if there's an open cash box)
            $openCashBox = $this->cashBoxService->getOpenCashBox();
            if ($openCashBox) {
                $this->cashBoxService->registerReversal(
                    cashBoxId: $openCashBox->id,
                    amount: (float) $sale->total,
                    description: "Anulación de venta #{$sale->id}",
                    userId: $userId,
                    saleId: $sale->id
                );
            }

        } catch (Exception $e) {
            // If something fails, mark annulment as rejected
            $annulment->markAsRejected();
            throw new Exception("Error al procesar la anulación: " . $e->getMessage());
        }
    }

    /**
     * Get annulment details
     *
     * @param int $annulmentId
     * @return array
     */
    public function getAnnulmentDetails(int $annulmentId): array
    {
        $annulment = Annulment::with([
            'fiscalDocument.sale.items.product',
            'fiscalDocument.sale.items.presentation',
            'user'
        ])->findOrFail($annulmentId);

        $sale = $annulment->fiscalDocument->sale;

        return [
            'annulment_id' => $annulment->id,
            'status' => $annulment->status,
            'reason' => $annulment->reason,
            'requested_by' => $annulment->user->name,
            'requested_at' => $annulment->created_at,
            'sale' => [
                'id' => $sale->id,
                'status' => $sale->status,
                'total' => $sale->total,
                'items_count' => $sale->items->count(),
            ],
            'fiscal_document' => [
                'id' => $annulment->fiscalDocument->id,
                'uuid' => $annulment->fiscalDocument->uuid,
                'serie' => $annulment->fiscalDocument->serie,
                'number' => $annulment->fiscalDocument->number,
                'status' => $annulment->fiscalDocument->status,
            ],
        ];
    }

    /**
     * Get all annulments with optional filters
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAnnulments(array $filters = [])
    {
        $query = Annulment::with(['fiscalDocument.sale', 'user'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->get();
    }

    /**
     * Get pending annulments (for approval workflow if needed)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingAnnulments()
    {
        return Annulment::where('status', Annulment::STATUS_PENDING)
            ->with(['fiscalDocument.sale', 'user'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Validate if a sale can be annulled
     *
     * @param int $saleId
     * @return array ['can_annul' => bool, 'reason' => string]
     */
    public function canAnnulSale(int $saleId): array
    {
        try {
            $sale = Sale::with('fiscalDocument')->findOrFail($saleId);

            if ($sale->isAnnulled()) {
                return [
                    'can_annul' => false,
                    'reason' => 'La venta ya está anulada.',
                ];
            }

            if (!$sale->isCompleted()) {
                return [
                    'can_annul' => false,
                    'reason' => 'Solo se pueden anular ventas completadas.',
                ];
            }

            if (!$sale->hasFiscalDocument()) {
                return [
                    'can_annul' => false,
                    'reason' => 'La venta no tiene documento fiscal.',
                ];
            }

            if (!$sale->fiscalDocument->canBeAnnulled()) {
                return [
                    'can_annul' => false,
                    'reason' => 'El documento fiscal no puede ser anulado en su estado actual.',
                ];
            }

            return [
                'can_annul' => true,
                'reason' => 'La venta puede ser anulada.',
            ];

        } catch (Exception $e) {
            return [
                'can_annul' => false,
                'reason' => 'Error al validar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get annulment statistics
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getAnnulmentStats(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Annulment::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $approved = (clone $query)->where('status', Annulment::STATUS_APPROVED)->count();
        $rejected = (clone $query)->where('status', Annulment::STATUS_REJECTED)->count();
        $pending = (clone $query)->where('status', Annulment::STATUS_PENDING)->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
        ];
    }
}
