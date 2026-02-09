<?php

namespace App\Imports\Inventory;

class CategoryBasedJsonTransformer
{
    /**
     * Transform category-based JSON format to standard import format
     * 
     * Expected input format:
     * {
     *   "categoria": "HIGIENE",
     *   "items": [...]
     * }
     */
    public function transform(array $data): array
    {
        $products = [];
        
        // Handle single category object
        if (isset($data['categoria']) && isset($data['items'])) {
            $categoryName = $data['categoria'];
            foreach ($data['items'] as $item) {
                $product = $this->transformItem($item, $categoryName);
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        // Handle array of category objects
        elseif (is_array($data) && !isset($data['categoria'])) {
            foreach ($data as $categoryData) {
                if (!isset($categoryData['categoria']) || !isset($categoryData['items'])) {
                    continue;
                }
                $categoryName = $categoryData['categoria'];
                foreach ($categoryData['items'] as $item) {
                    $product = $this->transformItem($item, $categoryName);
                    if ($product) {
                        $products[] = $product;
                    }
                }
            }
        }
        
        return $products;
    }
    
    /**
     * Transform a single item
     */
    private function transformItem(array $item, string $categoryName): ?array
    {
        if (empty($item['producto'])) {
            return null;
        }
        
        // Generate barcode from category and product name
        $barcode = $this->generateBarcode($categoryName, $item['producto']);
        
        // Build presentations array
        $presentations = $this->buildPresentations($item);
        
        if (empty($presentations)) {
            return null; // Skip if no valid presentations
        }
        
        // Build stock array
        $stock = $this->buildStock($item, $presentations);
        
        return [
            'barcode' => $barcode,
            'name' => $this->cleanString($item['producto']),
            'description' => null,
            'category_name' => $this->cleanString($categoryName),
            'brand' => !empty($item['marca']) ? $this->cleanString($item['marca']) : null,
            'location' => !empty($item['ubicacion']) ? $this->cleanString($item['ubicacion']) : null,
            'supplier_name' => $this->extractSupplierName($item),
            'presentations' => $presentations,
            'stock' => $stock,
        ];
    }
    
    /**
     * Build presentations array from compra/venta data
     */
    private function buildPresentations(array $item): array
    {
        $presentations = [];
        $compra = $item['compra'] ?? [];
        $venta = $item['venta'] ?? [];
        
        // Map presentation types with their factors
        $types = [
            'unidad' => ['name' => 'Unidad', 'factor' => 1],
            'blister' => ['name' => 'Blister', 'factor' => 6],
            'caja' => ['name' => 'Caja', 'factor' => 12],
        ];
        
        foreach ($types as $key => $config) {
            $purchasePrice = $compra[$key] ?? null;
            $salePrice = $venta[$key] ?? null;
            
            // Skip if sale price is not valid (required)
            if (!$this->isValidPrice($salePrice)) {
                continue;
            }
            
            // If purchase price is not valid, use 70% of sale price as default
            if (!$this->isValidPrice($purchasePrice)) {
                $purchasePrice = (float) $salePrice * 0.70;
            }
            
            $presentations[] = [
                'name' => $config['name'],
                'factor' => $config['factor'],
                'purchase_price' => (float) $purchasePrice,
                'sale_price' => (float) $salePrice,
            ];
        }
        
        return $presentations;
    }
    
    /**
     * Build stock array from existencia data
     */
    private function buildStock(array $item, array $presentations): array
    {
        $stock = [];
        $existencia = $item['existencia'] ?? [];
        $expiration = $item['vencimiento'] ?? null;
        $location = $item['ubicacion'] ?? null;
        
        // Map stock quantities to presentation names
        $types = [
            'unidad' => 'Unidad',
            'blister' => 'Blister',
            'caja' => 'Caja',
        ];
        
        foreach ($types as $key => $presentationName) {
            $quantity = $existencia[$key] ?? 0;
            
            // Only add stock if quantity > 0 and presentation exists
            if ($quantity > 0 && $this->presentationExists($presentationName, $presentations)) {
                $stock[] = [
                    'presentation_name' => $presentationName,
                    'quantity' => (int) $quantity,
                    'location' => $location,
                    'expiration_date' => $this->formatDate($expiration),
                ];
            }
        }
        
        return $stock;
    }
    
    /**
     * Check if presentation exists in presentations array
     */
    private function presentationExists(string $name, array $presentations): bool
    {
        foreach ($presentations as $presentation) {
            if ($presentation['name'] === $name) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if price is valid
     */
    private function isValidPrice($price): bool
    {
        return $price !== null && $price !== '' && is_numeric($price) && (float) $price > 0;
    }
    
    /**
     * Extract supplier name from proveedor field
     */
    private function extractSupplierName(array $item): ?string
    {
        $proveedor = $item['proveedor'] ?? null;
        
        if (empty($proveedor)) {
            return null;
        }
        
        // If proveedor is a number (like "269.00"), it might be a price, not a name
        if (is_numeric($proveedor)) {
            return null;
        }
        
        return $this->cleanString($proveedor);
    }
    
    /**
     * Generate barcode from category and product name
     */
    private function generateBarcode(string $category, string $product): string
    {
        // Create a unique identifier
        $categorySlug = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $category), 0, 3));
        $productHash = substr(md5($product), 0, 10);
        
        return $categorySlug . $productHash;
    }
    
    /**
     * Clean and normalize string
     */
    private function cleanString(string $str): string
    {
        return trim($str);
    }
    
    /**
     * Format date string
     */
    private function formatDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        try {
            $dt = new \DateTime($date);
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
