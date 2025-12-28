<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected $productId;
    protected $requiredQuantity;
    protected $availableQuantity;

    public function __construct(
        int $productId,
        int $requiredQuantity,
        int $availableQuantity,
        string $message = null
    ) {
        $this->productId = $productId;
        $this->requiredQuantity = $requiredQuantity;
        $this->availableQuantity = $availableQuantity;

        $message = $message ?? "Stock insuficiente. Disponible: {$availableQuantity}, Requerido: {$requiredQuantity}";

        parent::__construct($message);
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getRequiredQuantity(): int
    {
        return $this->requiredQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->availableQuantity;
    }
}
