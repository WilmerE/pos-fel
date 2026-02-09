<?php

namespace App\Imports\Inventory;

use App\DTOs\ImportedProductDTO;
use App\Models\Category;
use App\Models\InventoryImport;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\StockBatch;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class InventoryImportCommit
{
    private array $createdCategories = [];
    private array $createdSuppliers = [];
    private int $productsCreated = 0;
    private int $productsUpdated = 0;
    private int $presentationsCreated = 0;
    private int $stockBatchesCreated = 0;

    /**
     * Commit the import to database
     */
    public function commit(array $validatedProducts, ?string $sourceName = null, string $sourceType = 'json', ?int $userId = null): array
    {
        return DB::transaction(function () use ($validatedProducts, $sourceName, $sourceType, $userId) {
            $totalItems = count($validatedProducts);

            foreach ($validatedProducts as $product) {
                $this->importProduct($product);
            }

            // Save import history record
            if ($userId) {
                InventoryImport::create([
                    'source_name' => $sourceName ?? 'Importación JSON - ' . now()->format('d/m/Y H:i'),
                    'source_type' => $sourceType,
                    'total_items' => $totalItems,
                    'total_products_created' => $this->productsCreated,
                    'total_products_updated' => $this->productsUpdated,
                    'total_categories_created' => count($this->createdCategories),
                    'total_suppliers_created' => count($this->createdSuppliers),
                    'total_presentations_created' => $this->presentationsCreated,
                    'total_stock_batches_created' => $this->stockBatchesCreated,
                    'imported_by' => $userId,
                    'imported_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'summary' => [
                    'products_created' => $this->productsCreated,
                    'products_updated' => $this->productsUpdated,
                    'presentations_created' => $this->presentationsCreated,
                    'stock_batches_created' => $this->stockBatchesCreated,
                    'categories_created' => count($this->createdCategories),
                    'suppliers_created' => count($this->createdSuppliers),
                ],
                'created_categories' => array_values($this->createdCategories),
                'created_suppliers' => array_values($this->createdSuppliers),
            ];
        });
    }

    /**
     * Import a single product
     */
    private function importProduct(ImportedProductDTO $productDTO): void
    {
        // Resolve or create category
        $categoryId = $this->resolveCategory($productDTO->categoryId, $productDTO->categoryName);

        // Resolve or create supplier
        $supplierId = $this->resolveSupplier($productDTO->supplierId, $productDTO->supplierName);

        // Find or create product
        $product = Product::where('barcode', $productDTO->barcode)->first();

        if ($product) {
            // Update existing product
            $product->update([
                'name' => $productDTO->name,
                'description' => $productDTO->description,
                'category_id' => $categoryId,
                'brand' => $productDTO->brand,
                'location' => $productDTO->location,
                'supplier_id' => $supplierId,
            ]);
            $this->productsUpdated++;
        } else {
            // Create new product
            $product = Product::create([
                'barcode' => $productDTO->barcode,
                'name' => $productDTO->name,
                'description' => $productDTO->description,
                'category_id' => $categoryId,
                'brand' => $productDTO->brand,
                'location' => $productDTO->location,
                'supplier_id' => $supplierId,
                'active' => true,
            ]);
            $this->productsCreated++;
        }

        // Import presentations
        foreach ($productDTO->presentations as $presentationData) {
            $this->importPresentation($product, $presentationData);
        }

        // Import stock
        foreach ($productDTO->stock as $stockData) {
            $this->importStock($product, $stockData);
        }
    }

    /**
     * Resolve or create category
     */
    private function resolveCategory(?int $categoryId, ?string $categoryName): ?int
    {
        if ($categoryId) {
            return $categoryId;
        }

        if (!$categoryName) {
            return null;
        }

        // Find existing category by name
        $category = Category::where('name', $categoryName)->first();

        if ($category) {
            return $category->id;
        }

        // Create new category
        $category = Category::create([
            'name' => $categoryName,
            'description' => "Importada automáticamente",
            'active' => true,
        ]);

        $this->createdCategories[] = $category->name;

        return $category->id;
    }

    /**
     * Resolve or create supplier
     */
    private function resolveSupplier(?int $supplierId, ?string $supplierName): ?int
    {
        if ($supplierId) {
            return $supplierId;
        }

        if (!$supplierName) {
            return null;
        }

        // Find existing supplier by name
        $supplier = Supplier::where('name', $supplierName)->first();

        if ($supplier) {
            return $supplier->id;
        }

        // Create new supplier
        $supplier = Supplier::create([
            'name' => $supplierName,
            'active' => true,
        ]);

        $this->createdSuppliers[] = $supplier->name;

        return $supplier->id;
    }

    /**
     * Import a presentation
     */
    private function importPresentation(Product $product, array $presentationData): void
    {
        // Check if presentation exists
        $presentation = ProductPresentation::where('product_id', $product->id)
            ->where('name', $presentationData['name'])
            ->first();

        if ($presentation) {
            // Update existing
            $presentation->update([
                'factor' => $presentationData['factor'],
                'purchase_price' => $presentationData['purchase_price'],
                'sale_price' => $presentationData['sale_price'],
            ]);
        } else {
            // Create new
            ProductPresentation::create([
                'product_id' => $product->id,
                'name' => $presentationData['name'],
                'factor' => $presentationData['factor'],
                'purchase_price' => $presentationData['purchase_price'],
                'sale_price' => $presentationData['sale_price'],
            ]);
            $this->presentationsCreated++;
        }
    }

    /**
     * Import stock batch
     */
    private function importStock(Product $product, array $stockData): void
    {
        // Find presentation by name
        $presentation = ProductPresentation::where('product_id', $product->id)
            ->where('name', $stockData['presentation_name'] ?? 'Unidad')
            ->first();

        if (!$presentation) {
            // Use first presentation as fallback
            $presentation = $product->presentations()->first();
        }

        if (!$presentation) {
            return; // No presentation available
        }

        // Generate unique batch number for import
        $batchNumber = 'IMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        // Create stock batch
        StockBatch::create([
            'product_id' => $product->id,
            'presentation_id' => $presentation->id,
            'batch_number' => $batchNumber,
            'quantity_initial' => $stockData['quantity'] ?? 0,
            'quantity_available' => $stockData['quantity'] ?? 0,
            'location' => $stockData['location'] ?? $product->location,
            'expiration_date' => $stockData['expiration_date'] ?? null,
        ]);

        $this->stockBatchesCreated++;
    }
}
