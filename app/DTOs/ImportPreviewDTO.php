<?php

namespace App\DTOs;

class ImportPreviewDTO
{
    public function __construct(
        public int $totalProducts,
        public int $newProducts,
        public int $existingProducts,
        public int $totalPresentations,
        public int $totalStockBatches,
        public array $stats,
        public array $warnings,
        public array $errors,
        public bool $canImport,
        public ?string $importId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'total_products' => $this->totalProducts,
            'new_products' => $this->newProducts,
            'existing_products' => $this->existingProducts,
            'total_presentations' => $this->totalPresentations,
            'total_stock_batches' => $this->totalStockBatches,
            'stats' => $this->stats,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'can_import' => $this->canImport,
            'import_id' => $this->importId,
        ];
    }
}
