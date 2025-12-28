<?php

namespace App\Exceptions;

use Exception;

class CashBoxException extends Exception
{
    protected $cashBoxId;

    public function __construct(?int $cashBoxId = null, string $message = null)
    {
        $this->cashBoxId = $cashBoxId;
        parent::__construct($message ?? "Error en la operación de caja.");
    }

    public function getCashBoxId(): ?int
    {
        return $this->cashBoxId;
    }
}
