<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Add stock to a product
     * Permission: manage_stock
     */
    public function addStock(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para agregar stock.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'batch_number' => 'required|string|max:50',
            'expiration_date' => 'nullable|date',
            'quantity' => 'required|integer|min:1',
            'location' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $stockBatch = $this->stockService->addStock(
                productId: $request->product_id,
                batchNumber: $request->batch_number,
                expirationDate: $request->expiration_date,
                quantity: $request->quantity,
                userId: $request->user()->id,
                location: $request->location
            );

            return response()->json([
                'message' => 'Stock agregado exitosamente.',
                'data' => $stockBatch,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al agregar stock.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Adjust stock manually
     * Permission: adjust_stock
     */
    public function adjustStock(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('adjust_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ajustar stock.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'stock_batch_id' => 'required|exists:stock_batches,id',
            'quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $movement = $this->stockService->adjustStock(
                productId: $request->product_id,
                stockBatchId: $request->stock_batch_id,
                quantity: $request->quantity,
                userId: $request->user()->id,
                reason: $request->reason
            );

            return response()->json([
                'message' => 'Ajuste de stock registrado exitosamente.',
                'data' => $movement,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al ajustar stock.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get available stock for a product
     * Permission: view_stock
     */
    public function getAvailableStock(Request $request, int $productId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver stock.',
            ], 403);
        }

        try {
            $availableStock = $this->stockService->getAvailableStock($productId);

            return response()->json([
                'message' => 'Stock disponible obtenido exitosamente.',
                'data' => [
                    'product_id' => $productId,
                    'available_stock' => $availableStock,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener stock disponible.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get stock batches for a product (FIFO order)
     * Permission: view_stock
     */
    public function getStockBatches(Request $request, int $productId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver stock.',
            ], 403);
        }

        try {
            $batches = $this->stockService->getStockBatchesFIFO($productId);

            return response()->json([
                'message' => 'Lotes obtenidos exitosamente.',
                'data' => $batches,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener lotes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if product has sufficient stock
     * Permission: view_stock
     */
    public function checkStock(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para consultar stock.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $hasSufficient = $this->stockService->hasSufficientStock(
                $request->product_id,
                $request->quantity
            );

            $availableStock = $this->stockService->getAvailableStock($request->product_id);

            return response()->json([
                'message' => 'Consulta de stock realizada exitosamente.',
                'data' => [
                    'product_id' => $request->product_id,
                    'requested_quantity' => $request->quantity,
                    'available_stock' => $availableStock,
                    'has_sufficient_stock' => $hasSufficient,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al consultar stock.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
