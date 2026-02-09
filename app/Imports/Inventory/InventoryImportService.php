<?php

namespace App\Imports\Inventory;

use App\DTOs\ImportPreviewDTO;
use Illuminate\Support\Facades\Cache;

class InventoryImportService
{
    private InventoryImportValidator $validator;
    private InventoryPreviewBuilder $previewBuilder;
    private InventoryImportCommit $committer;

    public function __construct()
    {
        $this->validator = new InventoryImportValidator();
        $this->previewBuilder = new InventoryPreviewBuilder();
        $this->committer = new InventoryImportCommit();
    }

    /**
     * Validate and preview JSON import
     */
    public function preview(string $json): ImportPreviewDTO
    {
        // Validate JSON
        $validation = $this->validator->validateJson($json);

        // Build preview
        $preview = $this->previewBuilder->buildPreview(
            $validation['data'] ?? [],
            $validation['errors'],
            $validation['warnings']
        );

        // Cache validated data for later commit
        if ($preview->canImport && $preview->importId) {
            Cache::put(
                'import_data_' . $preview->importId,
                $validation['data'],
                now()->addHours(2)
            );
        }

        return $preview;
    }

    /**
     * Commit a previously validated import
     */
    public function commit(string $importId, ?string $sourceName = null, string $sourceType = 'json', ?int $userId = null): array
    {
        // Retrieve cached validated data
        $validatedProducts = Cache::get('import_data_' . $importId);

        if (!$validatedProducts) {
            return [
                'success' => false,
                'message' => 'Datos de importación no encontrados o expirados. Por favor, vuelve a cargar el archivo.',
            ];
        }

        // Commit to database
        $result = $this->committer->commit($validatedProducts, $sourceName, $sourceType, $userId);

        // Clear cache
        Cache::forget('import_data_' . $importId);

        return $result;
    }

    /**
     * Direct import without preview (for testing or CLI)
     */
    public function directImport(string $json, ?string $sourceName = null, string $sourceType = 'json', ?int $userId = null): array
    {
        $validation = $this->validator->validateJson($json);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        return $this->committer->commit($validation['data'], $sourceName, $sourceType, $userId);
    }
}
