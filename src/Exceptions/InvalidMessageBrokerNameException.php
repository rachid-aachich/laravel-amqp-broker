<?php

namespace Aachich\MessageBroker\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class InvalidMessageBrokerNameException extends Exception
{
    public function report()
    {
        Log::stack(['msgbroker','single'])->error(
            "-class : ". get_class($this).
            "\n - Exception: " .$this->getMessage()
        );
    }
}
