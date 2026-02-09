<?php

namespace App\Imports\Inventory;

use App\DTOs\ImportedProductDTO;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;

class InventoryImportValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $categoryMap = [];
    private array $supplierMap = [];
    private array $existingBarcodes = [];

    public function __construct()
    {
        $this->loadReferences();
    }

    /**
     * Load existing categories, suppliers, and products for reference
     */
    private function loadReferences(): void
    {
        // Load categories by name
        Category::all()->each(function ($category) {
            $this->categoryMap[strtolower($category->name)] = $category->id;
        });

        // Load suppliers by name
        Supplier::all()->each(function ($supplier) {
            $this->supplierMap[strtolower($supplier->name)] = $supplier->id;
        });

        // Load existing product barcodes
        $this->existingBarcodes = Product::pluck('barcode')->toArray();
    }

    /**
     * Validate JSON structure
     */
    public function validateJson(string $json): array
    {
        $this->errors = [];
        $this->warnings = [];

        // Try to decode JSON
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'JSON inválido: ' . json_last_error_msg();
            return [
                'valid' => false,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'data' => null,
            ];
        }

        // Validate root structure
        if (!is_array($data)) {
            $this->errors[] = 'El JSON debe contener un array de productos';
            return [
                'valid' => false,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'data' => null,
            ];
        }

        // Validate each product
        $validatedProducts = [];
        foreach ($data as $index => $productData) {
            $product = $this->validateProduct($productData, $index);
            if ($product) {
                $validatedProducts[] = $product;
            }
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'data' => $validatedProducts,
        ];
    }

    /**
     * Validate a single product
     */
    private function validateProduct(array $data, int $index): ?ImportedProductDTO
    {
        $productLabel = "Producto #" . ($index + 1);
        $hasErrors = false;

        // Validate required fields
        if (empty($data['barcode'])) {
            $this->errors[] = "{$productLabel}: Código de barras es requerido";
            $hasErrors = true;
        }

        if (empty($data['name'])) {
            $this->errors[] = "{$productLabel}: Nombre es requerido";
            $hasErrors = true;
        }

        // Check for duplicate barcode in import
        if (!empty($data['barcode'])) {
            if (in_array($data['barcode'], $this->existingBarcodes)) {
                $this->warnings[] = "{$productLabel} ({$data['barcode']}): Ya existe en el sistema";
            }
        }

        // Resolve category
        $categoryId = null;
        if (!empty($data['category_name'])) {
            $categoryKey = strtolower($data['category_name']);
            if (isset($this->categoryMap[$categoryKey])) {
                $categoryId = $this->categoryMap[$categoryKey];
            } else {
                $this->warnings[] = "{$productLabel}: Categoría '{$data['category_name']}' no encontrada. Se creará automáticamente.";
            }
        }

        // Resolve supplier
        $supplierId = null;
        if (!empty($data['supplier_name'])) {
            $supplierKey = strtolower($data['supplier_name']);
            if (isset($this->supplierMap[$supplierKey])) {
                $supplierId = $this->supplierMap[$supplierKey];
            } else {
                $this->warnings[] = "{$productLabel}: Proveedor '{$data['supplier_name']}' no encontrado. Se creará automáticamente.";
            }
        }

        // Validate presentations
        if (empty($data['presentations']) || !is_array($data['presentations'])) {
            $this->warnings[] = "{$productLabel}: No tiene presentaciones definidas";
        } else {
            foreach ($data['presentations'] as $presIndex => $presentation) {
                if (empty($presentation['name'])) {
                    $this->errors[] = "{$productLabel}, Presentación #" . ($presIndex + 1) . ": Nombre es requerido";
                    $hasErrors = true;
                }
                if (!isset($presentation['factor']) || $presentation['factor'] < 1) {
                    $this->errors[] = "{$productLabel}, Presentación #" . ($presIndex + 1) . ": Factor debe ser mayor o igual a 1";
                    $hasErrors = true;
                }
                if (!isset($presentation['purchase_price']) || $presentation['purchase_price'] < 0) {
                    $this->errors[] = "{$productLabel}, Presentación #" . ($presIndex + 1) . ": Precio de compra inválido";
                    $hasErrors = true;
                }
                if (!isset($presentation['sale_price']) || $presentation['sale_price'] < 0) {
                    $this->errors[] = "{$productLabel}, Presentación #" . ($presIndex + 1) . ": Precio de venta inválido";
                    $hasErrors = true;
                }
            }
        }

        if ($hasErrors) {
            return null;
        }

        return ImportedProductDTO::fromArray([
            'barcode' => $data['barcode'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $categoryId,
            'category_name' => $data['category_name'] ?? null,
            'brand' => $data['brand'] ?? null,
            'location' => $data['location'] ?? null,
            'supplier_id' => $supplierId,
            'supplier_name' => $data['supplier_name'] ?? null,
            'presentations' => $data['presentations'] ?? [],
            'stock' => $data['stock'] ?? [],
        ]);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
