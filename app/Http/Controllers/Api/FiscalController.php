<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FelService;
use App\Services\SaleAnnulmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FiscalController extends Controller
{
    protected FelService $felService;
    protected SaleAnnulmentService $annulmentService;

    public function __construct(
        FelService $felService,
        SaleAnnulmentService $annulmentService
    ) {
        $this->felService = $felService;
        $this->annulmentService = $annulmentService;
    }

    /**
     * Generate invoice data for a sale
     * Permission: generate_invoice
     */
    public function generateInvoiceData(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('generate_invoice')) {
            return response()->json([
                'message' => 'No tienes permiso para generar facturas.',
            ], 403);
        }

        try {
            $invoiceData = $this->felService->generateInvoiceData($saleId);

            return response()->json([
                'message' => 'Datos de factura generados exitosamente.',
                'data' => $invoiceData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al generar datos de factura.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Register fiscal document (emit invoice)
     * Permission: generate_invoice
     */
    public function registerFiscalDocument(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('generate_invoice')) {
            return response()->json([
                'message' => 'No tienes permiso para generar facturas.',
            ], 403);
        }

        try {
            $fiscalDocument = $this->felService->registerFiscalDocument(
                saleId: $saleId,
                additionalData: $request->input('additional_data')
            );

            return response()->json([
                'message' => 'Documento fiscal registrado exitosamente.',
                'data' => $fiscalDocument,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar documento fiscal.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get fiscal document details
     * Permission: view_fiscal_documents
     */
    public function show(Request $request, int $fiscalDocumentId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_fiscal_documents')) {
            return response()->json([
                'message' => 'No tienes permiso para ver documentos fiscales.',
            ], 403);
        }

        try {
            $details = $this->felService->getFiscalDocumentDetails($fiscalDocumentId);

            return response()->json([
                'message' => 'Documento fiscal obtenido exitosamente.',
                'data' => $details,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener documento fiscal.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get fiscal documents with filters
     * Permission: view_fiscal_documents
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_fiscal_documents')) {
            return response()->json([
                'message' => 'No tienes permiso para ver documentos fiscales.',
            ], 403);
        }

        try {
            $filters = [
                'status' => $request->query('status'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'uuid' => $request->query('uuid'),
            ];

            $documents = $this->felService->getFiscalDocuments(array_filter($filters));

            return response()->json([
                'message' => 'Documentos fiscales obtenidos exitosamente.',
                'data' => $documents,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener documentos fiscales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Annul a sale (with fiscal document)
     * Permission: annul_sale
     */
    public function annulSale(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('annul_sale')) {
            return response()->json([
                'message' => 'No tienes permiso para anular ventas facturadas.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $annulment = $this->annulmentService->annulSale(
                saleId: $saleId,
                userId: $request->user()->id,
                reason: $request->reason
            );

            return response()->json([
                'message' => 'Venta anulada exitosamente.',
                'data' => $annulment,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al anular la venta.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check if a sale can be annulled
     * Permission: annul_sale
     */
    public function canAnnulSale(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('annul_sale')) {
            return response()->json([
                'message' => 'No tienes permiso para verificar anulaciones.',
            ], 403);
        }

        try {
            $result = $this->annulmentService->canAnnulSale($saleId);

            return response()->json([
                'message' => 'Verificación realizada exitosamente.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al verificar.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get annulment details
     * Permission: view_fiscal_documents
     */
    public function getAnnulmentDetails(Request $request, int $annulmentId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_fiscal_documents')) {
            return response()->json([
                'message' => 'No tienes permiso para ver anulaciones.',
            ], 403);
        }

        try {
            $details = $this->annulmentService->getAnnulmentDetails($annulmentId);

            return response()->json([
                'message' => 'Anulación obtenida exitosamente.',
                'data' => $details,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener anulación.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get all annulments
     * Permission: view_fiscal_documents
     */
    public function getAnnulments(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_fiscal_documents')) {
            return response()->json([
                'message' => 'No tienes permiso para ver anulaciones.',
            ], 403);
        }

        try {
            $filters = [
                'status' => $request->query('status'),
                'user_id' => $request->query('user_id'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ];

            $annulments = $this->annulmentService->getAnnulments(array_filter($filters));

            return response()->json([
                'message' => 'Anulaciones obtenidas exitosamente.',
                'data' => $annulments,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener anulaciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
