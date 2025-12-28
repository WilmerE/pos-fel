<?php

namespace App\Exceptions;

use Exception;

class SaleException extends Exception
{
    protected $saleId;

    public function __construct(int $saleId = null, string $message = null)
    {
        $this->saleId = $saleId;
        parent::__construct($message ?? "Error en el proceso de venta.");
    }

    public function getSaleId(): ?int
    {
        return $this->saleId;
    }
}
