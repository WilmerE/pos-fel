<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Get all active suppliers
     * Permission: view_stock
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver proveedores.',
            ], 403);
        }

        $suppliers = Supplier::withCount('products')
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'contact_name' => $supplier->contact_name,
                    'phone' => $supplier->phone,
                    'email' => $supplier->email,
                    'address' => $supplier->address,
                    'products_count' => $supplier->products_count,
                ];
            });

        return response()->json([
            'message' => 'Proveedores obtenidos exitosamente.',
            'data' => $suppliers,
        ]);
    }

    /**
     * Get a single supplier by ID
     * Permission: view_stock
     */
    public function show(Request $request, int $supplierId): JsonResponse
    {
        if (!$request->user()->hasPermission('view_stock')) {
            return response()->json([
                'message' => 'No tienes permiso para ver proveedores.',
            ], 403);
        }

        $supplier = Supplier::with(['products'])->findOrFail($supplierId);

        return response()->json([
            'message' => 'Proveedor obtenido exitosamente.',
            'data' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'active' => $supplier->active,
                'products_count' => $supplier->products->count(),
            ],
        ]);
    }

    /**
     * Create a new supplier
     * Permission: manage_products
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para crear proveedores.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ], [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Proveedor creado exitosamente.',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Update an existing supplier
     * Permission: manage_products
     */
    public function update(Request $request, int $supplierId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para actualizar proveedores.',
            ], 403);
        }

        $supplier = Supplier::findOrFail($supplierId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'active' => 'sometimes|boolean',
        ], [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Proveedor actualizado exitosamente.',
            'data' => $supplier->fresh(),
        ]);
    }

    /**
     * Delete (deactivate) a supplier
     * Permission: manage_products
     */
    public function destroy(Request $request, int $supplierId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar proveedores.',
            ], 403);
        }

        $supplier = Supplier::findOrFail($supplierId);

        // Check if supplier has products
        $productsCount = $supplier->products()->count();
        
        if ($productsCount > 0) {
            return response()->json([
                'message' => "No se puede eliminar el proveedor porque tiene {$productsCount} producto(s) asociados.",
            ], 400);
        }

        // If no products, can safely delete
        $supplier->delete();

        return response()->json([
            'message' => 'Proveedor eliminado exitosamente.',
        ]);
    }
}
