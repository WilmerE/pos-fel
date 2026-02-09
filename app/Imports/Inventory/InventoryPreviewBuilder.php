<?php

namespace App\Imports\Inventory;

use App\DTOs\ImportedProductDTO;
use App\DTOs\ImportPreviewDTO;
use App\Models\Product;

class InventoryPreviewBuilder
{
    /**
     * Build preview from validated products
     */
    public function buildPreview(array $validatedProducts, array $errors, array $warnings): ImportPreviewDTO
    {
        $existingBarcodes = Product::pluck('barcode')->toArray();
        
        $newProducts = 0;
        $existingProducts = 0;
        $totalPresentations = 0;
        $totalStockBatches = 0;
        
        $categories = [];
        $suppliers = [];
        $brands = [];
        
        foreach ($validatedProducts as $product) {
            // Count new vs existing
            if (in_array($product->barcode, $existingBarcodes)) {
                $existingProducts++;
            } else {
                $newProducts++;
            }
            
            // Count presentations
            $totalPresentations += count($product->presentations);
            
            // Count stock batches
            $totalStockBatches += count($product->stock);
            
            // Collect stats
            if ($product->categoryName) {
                $categories[$product->categoryName] = ($categories[$product->categoryName] ?? 0) + 1;
            }
            
            if ($product->supplierName) {
                $suppliers[$product->supplierName] = ($suppliers[$product->supplierName] ?? 0) + 1;
            }
            
            if ($product->brand) {
                $brands[$product->brand] = ($brands[$product->brand] ?? 0) + 1;
            }
        }
        
        $stats = [
            'categories' => $categories,
            'suppliers' => $suppliers,
            'brands' => $brands,
        ];
        
        $canImport = empty($errors);
        
        return new ImportPreviewDTO(
            totalProducts: count($validatedProducts),
            newProducts: $newProducts,
            existingProducts: $existingProducts,
            totalPresentations: $totalPresentations,
            totalStockBatches: $totalStockBatches,
            stats: $stats,
            warnings: $warnings,
            errors: $errors,
            canImport: $canImport,
            importId: $canImport ? uniqid('import_', true) : null,
        );
    }
}
