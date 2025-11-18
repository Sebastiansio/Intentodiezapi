<?php

namespace App\Exceptions;

use Exception;

class FolioExpedienteExistenteException extends Exception
{
    protected $expediente;

    public function __construct($expediente, $message = "El folio del expediente ya existe", $code = 0, Exception $previous = null)
    {
        $this->expediente = $expediente;
        parent::__construct($message, $code, $previous);
    }

    public function getContext()
    {
        return $this->expediente;
    }
}
