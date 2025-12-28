<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SaleService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    protected SaleService $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * Create a new sale
     * Permission: sell
     */
    public function create(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('sell')) {
            return response()->json([
                'message' => 'No tienes permiso para crear ventas.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_nit' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $sale = $this->saleService->createSale(
                userId: $request->user()->id,
                cashierId: $request->input('cashier_id', $request->user()->id),
                customerName: $request->customer_name,
                customerNit: $request->customer_nit
            );

            return response()->json([
                'message' => 'Venta creada exitosamente.',
                'data' => $sale,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al crear la venta.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add item to sale
     * Permission: sell
     */
    public function addItem(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('sell')) {
            return response()->json([
                'message' => 'No tienes permiso para agregar items.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'presentation_id' => 'required|exists:product_presentations,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $item = $this->saleService->addItem(
                saleId: $saleId,
                productId: $request->product_id,
                presentationId: $request->presentation_id,
                quantity: $request->quantity
            );

            return response()->json([
                'message' => 'Item agregado exitosamente.',
                'data' => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al agregar item.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update item quantity
     * Permission: sell
     */
    public function updateItem(Request $request, int $saleItemId): JsonResponse
    {
        if (!$request->user()->hasPermission('sell')) {
            return response()->json([
                'message' => 'No tienes permiso para modificar items.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $item = $this->saleService->updateItemQuantity(
                saleItemId: $saleItemId,
                newQuantity: $request->quantity
            );

            return response()->json([
                'message' => 'Item actualizado exitosamente.',
                'data' => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar item.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove item from sale
     * Permission: sell
     */
    public function removeItem(Request $request, int $saleItemId): JsonResponse
    {
        if (!$request->user()->hasPermission('sell')) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar items.',
            ], 403);
        }

        try {
            $this->saleService->removeItem($saleItemId);

            return response()->json([
                'message' => 'Item eliminado exitosamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar item.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Confirm sale
     * Permission: sell
     */
    public function confirm(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('sell')) {
            return response()->json([
                'message' => 'No tienes permiso para confirmar ventas.',
            ], 403);
        }

        try {
            $sale = $this->saleService->confirmSale($saleId);

            return response()->json([
                'message' => 'Venta confirmada exitosamente.',
                'data' => $sale,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel sale (only if not invoiced)
     * Permission: cancel_sale
     */
    public function cancel(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('cancel_sale')) {
            return response()->json([
                'message' => 'No tienes permiso para cancelar ventas.',
            ], 403);
        }

        try {
            $sale = $this->saleService->cancelSale($saleId);

            return response()->json([
                'message' => 'Venta cancelada exitosamente.',
                'data' => $sale,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al cancelar la venta.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get sale summary
     * Permission: view_sales
     */
    public function show(Request $request, int $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_sales')) {
            return response()->json([
                'message' => 'No tienes permiso para ver ventas.',
            ], 403);
        }

        try {
            $summary = $this->saleService->getSaleSummary($saleId);

            return response()->json([
                'message' => 'Venta obtenida exitosamente.',
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la venta.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get pending sales
     * Permission: view_sales
     */
    public function pending(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_sales')) {
            return response()->json([
                'message' => 'No tienes permiso para ver ventas.',
            ], 403);
        }

        try {
            $sales = $this->saleService->getPendingSales();

            return response()->json([
                'message' => 'Ventas pendientes obtenidas exitosamente.',
                'data' => $sales,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener ventas pendientes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
