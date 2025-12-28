<?php

namespace App\Services;

use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Traits\ValidatesPermissions;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FelService
{
    use ValidatesPermissions;
    /**
     * Generate invoice data from a sale
     * Prepares all data needed for FEL certification
     *
     * @param int $saleId
     * @return array
     * @throws Exception
     */
    public function generateInvoiceData(int $saleId): array
    {
        $sale = Sale::with([
            'items.product',
            'items.presentation',
            'user',
            'cashier'
        ])->findOrFail($saleId);

        // Validate sale can be invoiced
        if (!$sale->isCompleted()) {
            throw new Exception("Solo se pueden facturar ventas completadas.");
        }

        if ($sale->hasFiscalDocument()) {
            throw new Exception("La venta ya tiene un documento fiscal asociado.");
        }

        if ($sale->items->isEmpty()) {
            throw new Exception("La venta no tiene items para facturar.");
        }

        // Build invoice data structure
        $invoiceData = [
            // Seller information (emisor)
            'seller' => $this->getSellerData(),
            
            // Buyer information (receptor)
            'buyer' => $this->getBuyerData(),
            
            // Invoice details
            'invoice' => [
                'type' => 'FACT', // Factura
                'currency' => 'GTQ',
                'date' => now()->format('Y-m-d'),
                'time' => now()->format('H:i:s'),
            ],
            
            // Items (productos/servicios)
            'items' => $this->buildInvoiceItems($sale),
            
            // Totals
            'totals' => [
                'subtotal' => (float) $sale->subtotal,
                'tax' => (float) $sale->tax,
                'total' => (float) $sale->total,
            ],
            
            // Sale reference
            'sale_id' => $sale->id,
            'sale_reference' => "SALE-{$sale->id}",
        ];

        return $invoiceData;
    }

    /**
     * Register a fiscal document in the system
     * In production, this would send data to FEL certifier first
     *
     * @param int $saleId
     * @param array|null $additionalData Additional data from FEL certifier
     * @return FiscalDocument
     * @throws Exception
     */
    public function registerFiscalDocument(int $saleId, ?array $additionalData = null): FiscalDocument
    {
        return DB::transaction(function () use ($saleId, $additionalData) {
            $sale = Sale::findOrFail($saleId);

            // Validate sale
            if (!$sale->isCompleted()) {
                throw new Exception("Solo se pueden facturar ventas completadas.");
            }

            if ($sale->hasFiscalDocument()) {
                throw new Exception("La venta ya tiene un documento fiscal asociado.");
            }

            // Generate invoice data
            $invoiceData = $this->generateInvoiceData($saleId);

            // TODO: In production, send $invoiceData to FEL certifier API
            // For now, we simulate the response
            $certifierResponse = $this->simulateCertifierResponse($invoiceData);

            // Merge additional data if provided
            if ($additionalData) {
                $certifierResponse = array_merge($certifierResponse, $additionalData);
            }

            // Create fiscal document record
            $fiscalDocument = FiscalDocument::create([
                'sale_id' => $saleId,
                'uuid' => $certifierResponse['uuid'],
                'serie' => $certifierResponse['serie'],
                'number' => $certifierResponse['number'],
                'status' => FiscalDocument::STATUS_AUTHORIZED,
                'xml' => $certifierResponse['xml'],
                'pdf_path' => $certifierResponse['pdf_path'] ?? null,
            ]);

            return $fiscalDocument;
        });
    }

    /**
     * Mark a fiscal document as rejected
     *
     * @param int $fiscalDocumentId
     * @param string|null $reason
     * @return FiscalDocument
     * @throws Exception
     */
    public function markAsRejected(int $fiscalDocumentId, ?string $reason = null): FiscalDocument
    {
        return DB::transaction(function () use ($fiscalDocumentId, $reason) {
            $fiscalDocument = FiscalDocument::findOrFail($fiscalDocumentId);

            if ($fiscalDocument->isAnnulled()) {
                throw new Exception("No se puede rechazar un documento anulado.");
            }

            if ($fiscalDocument->status === FiscalDocument::STATUS_REJECTED) {
                throw new Exception("El documento fiscal ya está rechazado.");
            }

            $fiscalDocument->markAsRejected();

            // Log rejection reason if provided
            if ($reason) {
                // TODO: Store rejection reason in a log or notes field
                Log::warning("Fiscal document #{$fiscalDocumentId} rejected: {$reason}");
            }

            return $fiscalDocument->fresh();
        });
    }

    /**
     * Mark a fiscal document as annulled
     * Note: This should typically be called from SaleAnnulmentService
     *
     * @param int $fiscalDocumentId
     * @return FiscalDocument
     * @throws Exception
     */
    public function markAsAnnulled(int $fiscalDocumentId): FiscalDocument
    {
        return DB::transaction(function () use ($fiscalDocumentId) {
            $fiscalDocument = FiscalDocument::findOrFail($fiscalDocumentId);

            if (!$fiscalDocument->isAuthorized()) {
                throw new Exception("Solo se pueden anular documentos autorizados.");
            }

            if ($fiscalDocument->isAnnulled()) {
                throw new Exception("El documento fiscal ya está anulado.");
            }

            $fiscalDocument->markAsAnnulled();

            return $fiscalDocument->fresh();
        });
    }

    /**
     * Get fiscal document by sale ID
     *
     * @param int $saleId
     * @return FiscalDocument|null
     */
    public function getFiscalDocumentBySale(int $saleId): ?FiscalDocument
    {
        return FiscalDocument::where('sale_id', $saleId)->first();
    }

    /**
     * Get fiscal document details
     *
     * @param int $fiscalDocumentId
     * @return array
     */
    public function getFiscalDocumentDetails(int $fiscalDocumentId): array
    {
        $fiscalDocument = FiscalDocument::with(['sale.items.product', 'sale.user'])
            ->findOrFail($fiscalDocumentId);

        return [
            'id' => $fiscalDocument->id,
            'uuid' => $fiscalDocument->uuid,
            'serie' => $fiscalDocument->serie,
            'number' => $fiscalDocument->number,
            'status' => $fiscalDocument->status,
            'created_at' => $fiscalDocument->created_at,
            'sale' => [
                'id' => $fiscalDocument->sale->id,
                'total' => $fiscalDocument->sale->total,
                'user' => $fiscalDocument->sale->user->name,
                'items_count' => $fiscalDocument->sale->items->count(),
            ],
            'has_pdf' => !empty($fiscalDocument->pdf_path),
            'has_xml' => !empty($fiscalDocument->xml),
        ];
    }

    /**
     * Get seller data (company/business information)
     * TODO: Move to configuration or database
     *
     * @return array
     */
    protected function getSellerData(): array
    {
        return [
            'nit' => config('fel.seller_nit', '12345678'),
            'name' => config('fel.seller_name', 'Mi Empresa S.A.'),
            'trade_name' => config('fel.seller_trade_name', 'Mi Empresa'),
            'address' => config('fel.seller_address', 'Ciudad de Guatemala'),
            'postal_code' => config('fel.seller_postal_code', '01001'),
            'department' => config('fel.seller_department', 'Guatemala'),
            'municipality' => config('fel.seller_municipality', 'Guatemala'),
            'email' => config('fel.seller_email', 'facturacion@miempresa.com'),
        ];
    }

    /**
     * Get buyer data (client information)
     * TODO: In future, get from customer record
     *
     * @return array
     */
    protected function getBuyerData(): array
    {
        // For now, return generic consumer data (Consumidor Final)
        return [
            'nit' => 'CF',
            'name' => 'Consumidor Final',
            'address' => 'Ciudad',
            'email' => null,
        ];
    }

    /**
     * Build invoice items array from sale items
     *
     * @param Sale $sale
     * @return array
     */
    protected function buildInvoiceItems(Sale $sale): array
    {
        $items = [];

        foreach ($sale->items as $index => $item) {
            $items[] = [
                'line_number' => $index + 1,
                'type' => 'B', // Bien (B) o Servicio (S)
                'quantity' => $item->quantity,
                'unit' => 'UND',
                'description' => "{$item->product->name} - {$item->presentation->name}",
                'unit_price' => (float) $item->unit_price,
                'discount' => 0.00,
                'total' => (float) $item->total,
            ];
        }

        return $items;
    }

    /**
     * Simulate FEL certifier response
     * TODO: Replace with real API call in production
     *
     * @param array $invoiceData
     * @return array
     */
    protected function simulateCertifierResponse(array $invoiceData): array
    {
        // Generate mock UUID (in production, this comes from SAT)
        $uuid = Str::uuid()->toString();
        
        // Generate mock serie and number
        $serie = 'FACT';
        $number = str_pad(rand(1, 999999), 8, '0', STR_PAD_LEFT);

        // Generate mock XML (in production, this is returned by certifier)
        $xml = $this->generateMockXml($invoiceData, $uuid, $serie, $number);

        return [
            'uuid' => $uuid,
            'serie' => $serie,
            'number' => $number,
            'xml' => $xml,
            'pdf_path' => null, // PDF generation can be added later
            'authorization_date' => now()->toIso8601String(),
            'status' => 'authorized',
        ];
    }

    /**
     * Generate mock XML for testing
     * TODO: Replace with real FEL XML structure in production
     *
     * @param array $invoiceData
     * @param string $uuid
     * @param string $serie
     * @param string $number
     * @return string
     */
    protected function generateMockXml(array $invoiceData, string $uuid, string $serie, string $number): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<dte:GTDocumento xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0">';
        $xml .= '<dte:SAT>';
        $xml .= '<dte:DTE>';
        $xml .= "<dte:NumeroAutorizacion>{$uuid}</dte:NumeroAutorizacion>";
        $xml .= "<dte:Serie>{$serie}</dte:Serie>";
        $xml .= "<dte:Numero>{$number}</dte:Numero>";
        $xml .= "<dte:FechaHoraEmision>" . now()->toIso8601String() . "</dte:FechaHoraEmision>";
        $xml .= "<dte:Emisor>{$invoiceData['seller']['name']}</dte:Emisor>";
        $xml .= "<dte:Receptor>{$invoiceData['buyer']['name']}</dte:Receptor>";
        $xml .= "<dte:Total>{$invoiceData['totals']['total']}</dte:Total>";
        $xml .= '</dte:DTE>';
        $xml .= '</dte:SAT>';
        $xml .= '</dte:GTDocumento>';
        
        return $xml;
    }

    /**
     * Validate invoice data structure
     *
     * @param array $invoiceData
     * @return bool
     * @throws Exception
     */
    public function validateInvoiceData(array $invoiceData): bool
    {
        $requiredFields = ['seller', 'buyer', 'invoice', 'items', 'totals'];

        foreach ($requiredFields as $field) {
            if (!isset($invoiceData[$field])) {
                throw new Exception("Falta el campo requerido: {$field}");
            }
        }

        if (empty($invoiceData['items'])) {
            throw new Exception("La factura debe tener al menos un item.");
        }

        if ($invoiceData['totals']['total'] <= 0) {
            throw new Exception("El total de la factura debe ser mayor a cero.");
        }

        return true;
    }

    /**
     * Get fiscal documents with filters
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFiscalDocuments(array $filters = [])
    {
        $query = FiscalDocument::with(['sale'])->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['uuid'])) {
            $query->where('uuid', 'like', "%{$filters['uuid']}%");
        }

        return $query->get();
    }

    /**
     * Get fiscal document statistics
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getFiscalDocumentStats(?string $startDate = null, ?string $endDate = null): array
    {
        $query = FiscalDocument::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $authorized = (clone $query)->where('status', FiscalDocument::STATUS_AUTHORIZED)->count();
        $annulled = (clone $query)->where('status', FiscalDocument::STATUS_ANNULLED)->count();
        $rejected = (clone $query)->where('status', FiscalDocument::STATUS_REJECTED)->count();

        return [
            'total' => $total,
            'authorized' => $authorized,
            'annulled' => $annulled,
            'rejected' => $rejected,
            'success_rate' => $total > 0 ? round(($authorized / $total) * 100, 2) : 0,
        ];
    }
}
