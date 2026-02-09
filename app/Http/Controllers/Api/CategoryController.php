<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'active' => $category->active,
                    'products_count' => $category->products()->count(),
                ];
            });

        return response()->json([
            'message' => 'Categorías obtenidas exitosamente.',
            'data' => $categories,
        ]);
    }

    /**
     * Create a new category
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para crear categorías.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:500',
        ], [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.unique' => 'Ya existe una categoría con este nombre.',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Categoría creada exitosamente.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update a category
     */
    public function update(Request $request, int $categoryId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para actualizar categorías.',
            ], 403);
        }

        $category = Category::findOrFail($categoryId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $categoryId,
            'description' => 'nullable|string|max:500',
            'active' => 'sometimes|boolean',
        ], [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.unique' => 'Ya existe una categoría con este nombre.',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Categoría actualizada exitosamente.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Delete a category
     */
    public function destroy(Request $request, int $categoryId): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar categorías.',
            ], 403);
        }

        $category = Category::findOrFail($categoryId);

        // Check if category has products
        $productsCount = $category->products()->count();
        
        if ($productsCount > 0) {
            return response()->json([
                'message' => "No se puede eliminar la categoría '{$category->name}' porque tiene {$productsCount} productos asociados.",
            ], 400);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente.',
        ]);
    }
}
