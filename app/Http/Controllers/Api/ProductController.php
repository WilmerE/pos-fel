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
        $products = Product::with(['presentations', 'stockBatches'])
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
                // Calculate total stock
                $totalStock = $product->stockBatches->sum('quantity_available');
                
                // Check for expired batches
                $expiredBatches = $product->stockBatches
                    ->filter(function ($batch) {
                        return $batch->expiration_date && 
                               $batch->expiration_date < now() && 
                               $batch->quantity_available > 0;
                    })
                    ->count();
                
                // Check for expiring soon (within 7 days)
                $expiringSoonBatches = $product->stockBatches
                    ->filter(function ($batch) {
                        return $batch->expiration_date && 
                               $batch->expiration_date >= now() && 
                               $batch->expiration_date <= now()->addDays(7) && 
                               $batch->quantity_available > 0;
                    })
                    ->count();
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'barcode' => $product->barcode,
                    'brand' => $product->brand,
                    'location' => $product->location,
                    'category_id' => $product->category_id,
                    'supplier_id' => $product->supplier_id,
                    'total_stock' => $totalStock,
                    'has_expired_batches' => $expiredBatches > 0,
                    'has_expiring_soon_batches' => $expiringSoonBatches > 0,
                    'stock_warning' => $totalStock <= 20,
                    'presentations' => $product->presentations->map(function ($presentation) {
                        return [
                            'id' => $presentation->id,
                            'name' => $presentation->name,
                            'purchase_price' => $presentation->purchase_price,
                            'sale_price' => $presentation->sale_price,
                            'price_with_iva' => round($presentation->sale_price * 1.12, 2),
                            'factor' => $presentation->factor,
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
                'purchase_price' => $presentation->purchase_price,
                'sale_price' => $presentation->sale_price,
                'factor' => $presentation->factor,
            ],
        ]);
    }

    /**
     * Get product catalog with pagination and filters
     * Permission: view_stock
     */
    public function catalog(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver el catálogo.',
            ], 403);
        }

        $perPage = $request->input('per_page', 12);
        $search = $request->input('search');
        $categoryId = $request->input('category_id');

        $query = Product::with(['presentations', 'stockBatches', 'category'])
            ->where('active', true);

        // Apply search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Apply category filter
        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'message' => 'Catálogo obtenido exitosamente.',
            'data' => $products->map(function ($product) {
                $totalStock = $product->stockBatches->sum('quantity_available');
                $locations = $product->stockBatches->pluck('location')->filter()->unique()->values();
                
                // Get base price from first presentation
                $firstPresentation = $product->presentations->first();
                $basePrice = $firstPresentation->sale_price ?? 0;
                $basePresentationName = $firstPresentation->name ?? 'Unidad';
                $priceWithIva = $basePrice * 1.12;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'barcode' => $product->barcode,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category ? $product->category->name : 'Sin categoría',
                    'brand' => $product->brand,
                    'location' => $product->location,
                    'base_price' => round($basePrice, 2),
                    'base_presentation_name' => $basePresentationName,
                    'price_with_iva' => round($priceWithIva, 2),
                    'total_stock' => $totalStock,
                    'locations' => $locations,
                    'presentations' => $product->presentations->map(function ($presentation) {
                        return [
                            'id' => $presentation->id,
                            'name' => $presentation->name,
                            'purchase_price' => $presentation->purchase_price,
                            'sale_price' => $presentation->sale_price,
                            'price_with_iva' => round($presentation->sale_price * 1.12, 2),
                            'factor' => $presentation->factor,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Get all product categories
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = \App\Models\Category::where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                ];
            });

        return response()->json([
            'message' => 'Categorías obtenidas exitosamente.',
            'data' => $categories,
        ]);
    }

    /**
     * Create a new product
     * Permission: manage_products
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para crear productos.',
            ], 403);
        }

        $validated = $request->validate([
            'barcode' => 'required|string|unique:products,barcode',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ], [
            'barcode.required' => 'El código de barras es obligatorio.',
            'barcode.unique' => 'Este código de barras ya existe.',
            'name.required' => 'El nombre del producto es obligatorio.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Producto creado exitosamente.',
            'data' => $product,
        ], 201);
    }

    /**
     * Get product details by ID
     * Permission: view_stock
     */
    public function show(Request $request, int $productId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver productos.',
            ], 403);
        }

        $product = Product::with(['presentations', 'category', 'supplier'])
            ->findOrFail($productId);

        return response()->json([
            'message' => 'Producto obtenido exitosamente.',
            'data' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'category_name' => $product->category ? $product->category->name : null,
                'brand' => $product->brand,
                'location' => $product->location,
                'supplier_id' => $product->supplier_id,
                'supplier_name' => $product->supplier ? $product->supplier->name : null,
                'active' => $product->active,
                'presentations' => $product->presentations->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'purchase_price' => $p->purchase_price,
                        'sale_price' => $p->sale_price,
                        'factor' => $p->factor,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update a product
     * Permission: manage_products
     */
    public function update(Request $request, int $productId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para actualizar productos.',
            ], 403);
        }

        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'barcode' => 'sometimes|required|string|unique:products,barcode,' . $productId,
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'active' => 'sometimes|boolean',
        ], [
            'barcode.unique' => 'Este código de barras ya existe.',
            'name.required' => 'El nombre del producto es obligatorio.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Producto actualizado exitosamente.',
            'data' => $product->fresh(['category']),
        ]);
    }

    /**
     * Add presentation to a product
     * Permission: manage_products
     */
    public function addPresentation(Request $request, int $productId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para agregar presentaciones.',
            ], 403);
        }

        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'factor' => 'required|integer|min:1',
        ], [
            'name.required' => 'El nombre de la presentación es obligatorio.',
            'purchase_price.required' => 'El precio de compra es obligatorio.',
            'purchase_price.min' => 'El precio de compra debe ser mayor o igual a 0.',
            'sale_price.required' => 'El precio de venta es obligatorio.',
            'sale_price.min' => 'El precio de venta debe ser mayor o igual a 0.',
            'factor.required' => 'Las unidades por presentación son obligatorias.',
            'factor.min' => 'Debe haber al menos 1 unidad por presentación.',
        ]);

        $presentation = $product->presentations()->create($validated);

        return response()->json([
            'message' => 'Presentación agregada exitosamente.',
            'data' => $presentation,
        ], 201);
    }

    /**
     * Update a product presentation
     * Permission: manage_products
     */
    public function updatePresentation(Request $request, int $presentationId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para actualizar presentaciones.',
            ], 403);
        }

        $presentation = \App\Models\ProductPresentation::findOrFail($presentationId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'sometimes|required|numeric|min:0',
            'factor' => 'sometimes|required|integer|min:1',
        ], [
            'name.required' => 'El nombre de la presentación es obligatorio.',
            'purchase_price.min' => 'El precio de compra debe ser mayor o igual a 0.',
            'sale_price.min' => 'El precio de venta debe ser mayor o igual a 0.',
            'factor.min' => 'Debe haber al menos 1 unidad por presentación.',
        ]);

        $presentation->update($validated);

        return response()->json([
            'message' => 'Presentación actualizada exitosamente.',
            'data' => $presentation->fresh(),
        ]);
    }

    /**
     * Delete a product presentation
     * Permission: manage_products
     */
    public function deletePresentation(Request $request, int $presentationId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar presentaciones.',
            ], 403);
        }

        $presentation = \App\Models\ProductPresentation::findOrFail($presentationId);
        
        // Check if it's the last presentation
        $productPresentationsCount = \App\Models\ProductPresentation::where('product_id', $presentation->product_id)->count();
        
        if ($productPresentationsCount <= 1) {
            return response()->json([
                'message' => 'No se puede eliminar la única presentación del producto.',
            ], 400);
        }

        $presentation->delete();

        return response()->json([
            'message' => 'Presentación eliminada exitosamente.',
        ]);
    }

    /**
     * Get expiration notifications
     * Returns products that are expired or expiring soon (within 30 days)
     * Permission: view_stock
     */
    public function expirationNotifications(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver notificaciones.',
            ], 403);
        }

        $today = now();
        $oneMonthFromNow = now()->addDays(30);

        // Get stock batches that are expired or expiring soon
        $batches = \App\Models\StockBatch::with(['product'])
            ->whereNotNull('expiration_date')
            ->where('quantity_available', '>', 0)
            ->where(function ($query) use ($today, $oneMonthFromNow) {
                $query->where('expiration_date', '<', $today) // Already expired
                      ->orWhereBetween('expiration_date', [$today, $oneMonthFromNow]); // Expiring soon
            })
            ->orderBy('expiration_date', 'asc')
            ->get();

        $notifications = $batches->map(function ($batch) use ($today) {
            $expirationDate = \Carbon\Carbon::parse($batch->expiration_date);
            $daysUntilExpiration = (int) $today->diffInDays($expirationDate, false);
            
            $isExpired = $daysUntilExpiration < 0;
            $urgency = $isExpired ? 'expired' : ($daysUntilExpiration <= 7 ? 'critical' : 'warning');

            return [
                'id' => $batch->id,
                'product_id' => $batch->product_id,
                'product_name' => $batch->product->name,
                'product_barcode' => $batch->product->barcode,
                'batch_number' => $batch->batch_number,
                'location' => $batch->location,
                'quantity_available' => $batch->quantity_available,
                'expiration_date' => $expirationDate->format('Y-m-d'),
                'expiration_date_formatted' => $expirationDate->format('d/m/Y'),
                'days_until_expiration' => $daysUntilExpiration,
                'is_expired' => $isExpired,
                'urgency' => $urgency,
                'message' => $isExpired 
                    ? "Venció hace " . abs($daysUntilExpiration) . " días"
                    : "Vence en " . $daysUntilExpiration . " días",
            ];
        });

        return response()->json([
            'message' => 'Notificaciones obtenidas exitosamente.',
            'data' => $notifications,
            'summary' => [
                'total' => $notifications->count(),
                'expired' => $notifications->where('is_expired', true)->count(),
                'expiring_soon' => $notifications->where('is_expired', false)->count(),
            ],
        ]);
    }
}
