<?php

namespace App\DTOs;

class ImportedProductDTO
{
    public function __construct(
        public string $barcode,
        public string $name,
        public ?string $description,
        public ?int $categoryId,
        public ?string $categoryName,
        public ?string $brand,
        public ?string $location,
        public ?int $supplierId,
        public ?string $supplierName,
        public array $presentations = [],
        public array $stock = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            barcode: $data['barcode'] ?? '',
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            categoryId: $data['category_id'] ?? null,
            categoryName: $data['category_name'] ?? null,
            brand: $data['brand'] ?? null,
            location: $data['location'] ?? null,
            supplierId: $data['supplier_id'] ?? null,
            supplierName: $data['supplier_name'] ?? null,
            presentations: $data['presentations'] ?? [],
            stock: $data['stock'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'brand' => $this->brand,
            'location' => $this->location,
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'presentations' => $this->presentations,
            'stock' => $this->stock,
        ];
    }
}
