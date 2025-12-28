<?php

namespace App\Exceptions;

use Exception;

class FelException extends Exception
{
    protected $fiscalDocumentId;
    protected $saleId;
    protected $errorCode;

    public function __construct(
        string $message,
        ?int $saleId = null,
        ?int $fiscalDocumentId = null,
        ?string $errorCode = null
    ) {
        $this->saleId = $saleId;
        $this->fiscalDocumentId = $fiscalDocumentId;
        $this->errorCode = $errorCode;

        parent::__construct($message);
    }

    public function getSaleId(): ?int
    {
        return $this->saleId;
    }

    public function getFiscalDocumentId(): ?int
    {
        return $this->fiscalDocumentId;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
