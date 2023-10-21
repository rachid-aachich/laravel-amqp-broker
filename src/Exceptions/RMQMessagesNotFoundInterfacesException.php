<?php

namespace MaroEco\MessageBroker\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class RMQMessagesNotFoundInterfacesException extends Exception
{
    public function __construct($classInterface)
    {
        $message = "Class interface '$classInterface' not found.";
        parent::__construct($message);
    }
    public function report()
    {
        Log::stack(['rabbitmq','single'])->error(
            "-class : ". get_class($this).
            "\n - Exception: " .$this->getMessage()
        );
    }

}
