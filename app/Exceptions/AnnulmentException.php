<?php

namespace App\Exceptions;

use Exception;

class AnnulmentException extends Exception
{
    protected $saleId;
    protected $fiscalDocumentId;

    public function __construct(
        ?int $saleId = null,
        ?int $fiscalDocumentId = null,
        string $message = null
    ) {
        $this->saleId = $saleId;
        $this->fiscalDocumentId = $fiscalDocumentId;
        parent::__construct($message ?? "Error en el proceso de anulación.");
    }

    public function getSaleId(): ?int
    {
        return $this->saleId;
    }

    public function getFiscalDocumentId(): ?int
    {
        return $this->fiscalDocumentId;
    }
}
