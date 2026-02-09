<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\Inventory\CategoryBasedJsonTransformer;
use App\Imports\Inventory\InventoryImportService;
use App\Models\InventoryImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryImportController extends Controller
{
    private InventoryImportService $importService;
    private CategoryBasedJsonTransformer $transformer;

    public function __construct(InventoryImportService $importService, CategoryBasedJsonTransformer $transformer)
    {
        $this->importService = $importService;
        $this->transformer = $transformer;
    }

    /**
     * Preview import from JSON file
     * POST /api/inventory/import/preview
     */
    public function preview(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para importar inventario.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:json|max:10240', // Max 10MB
        ], [
            'file.required' => 'Debes seleccionar un archivo JSON',
            'file.mimes' => 'El archivo debe ser formato JSON',
            'file.max' => 'El archivo no debe superar 10MB',
        ]);

        try {
            $json = file_get_contents($request->file('file')->getRealPath());
            
            $preview = $this->importService->preview($json);

            return response()->json([
                'message' => 'Preview generado exitosamente',
                'data' => $preview->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Preview import from JSON string
     * POST /api/inventory/import/preview-json
     */
    public function previewJson(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para importar inventario.',
            ], 403);
        }

        $request->validate([
            'json' => 'required|string',
        ], [
            'json.required' => 'Debes proporcionar datos JSON',
        ]);

        try {
            $preview = $this->importService->preview($request->input('json'));

            return response()->json([
                'message' => 'Preview generado exitosamente',
                'data' => $preview->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar JSON: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Commit a previously validated import
     * POST /api/inventory/import/commit
     */
    public function commit(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para importar inventario.',
            ], 403);
        }

        $request->validate([
            'import_id' => 'required|string',
            'source_name' => 'nullable|string|max:255',
            'source_type' => 'nullable|in:excel,json,manual',
        ], [
            'import_id.required' => 'ID de importación es requerido',
        ]);

        try {
            $result = $this->importService->commit(
                $request->input('import_id'),
                $request->input('source_name'),
                $request->input('source_type', 'json'),
                $request->user()->id
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'] ?? 'Error al importar',
                ], 400);
            }

            return response()->json([
                'message' => 'Importación completada exitosamente',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al importar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview import from category-based JSON (formato con categoria e items)
     * POST /api/inventory/import/preview-category
     */
    public function previewCategory(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para importar inventario.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:json|max:10240',
        ], [
            'file.required' => 'Debes seleccionar un archivo JSON',
            'file.mimes' => 'El archivo debe ser formato JSON',
            'file.max' => 'El archivo no debe superar 10MB',
        ]);

        try {
            $json = file_get_contents($request->file('file')->getRealPath());
            $categoryData = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON inválido: ' . json_last_error_msg());
            }

            // Transform category-based format to standard format
            $standardProducts = $this->transformer->transform($categoryData);
            
            // Convert back to JSON for the import service
            $standardJson = json_encode($standardProducts);
            
            // Use standard import service
            $preview = $this->importService->preview($standardJson);

            return response()->json([
                'message' => 'Preview generado exitosamente (formato por categoría)',
                'data' => $preview->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get import history
     * GET /api/inventory/import/history
     */
    public function history(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_products')) {
            return response()->json([
                'message' => 'No tienes permiso para ver el historial.',
            ], 403);
        }

        $imports = InventoryImport::with('user:id,name')
            ->orderBy('imported_at', 'desc')
            ->paginate(20);

        return response()->json($imports);
    }
}
