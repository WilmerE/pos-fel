<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Search products by barcode or name
     * Permission: view_stock
     */
    public function search(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para buscar productos.',
            ], 403);
        }

        $search = $request->input('search');

        if (empty($search)) {
            return response()->json([
                'message' => 'Debe proporcionar un término de búsqueda.',
                'data' => [],
            ], 200);
        }

        // Search by barcode (exact match) or name (partial match)
        $products = Product::with(['presentations'])
            ->where('active', true)
            ->where(function ($query) use ($search) {
                $query->where('barcode', $search)
                      ->orWhere('name', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get();

        return response()->json([
            'message' => $products->isEmpty() ? 'No se encontraron productos.' : 'Productos encontrados.',
            'data' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'barcode' => $product->barcode,
                    'presentations' => $product->presentations->map(function ($presentation) {
                        return [
                            'id' => $presentation->id,
                            'name' => $presentation->name,
                            'price' => $presentation->price,
                            'units_per_presentation' => $presentation->units_per_presentation,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * Get product presentation details
     * Permission: view_stock
     */
    public function getPresentation(Request $request, int $presentationId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver presentaciones.',
            ], 403);
        }

        $presentation = \App\Models\ProductPresentation::with(['product'])
            ->findOrFail($presentationId);

        return response()->json([
            'message' => 'Presentación obtenida exitosamente.',
            'data' => [
                'id' => $presentation->id,
                'product_id' => $presentation->product_id,
                'product_name' => $presentation->product->name,
                'name' => $presentation->name,
                'price' => $presentation->price,
                'units_per_presentation' => $presentation->units_per_presentation,
            ],
        ]);
    }
}
