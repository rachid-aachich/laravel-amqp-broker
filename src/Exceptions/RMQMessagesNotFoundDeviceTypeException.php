<?php

namespace Aachich\MessageBroker\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class RMQMessagesNotFoundDeviceTypeException extends Exception
{

    public function report()
    {
        Log::stack(['rabbitmq','single'])->error(
            "-class : ". get_class($this).
            "\n - Exception: " .$this->getMessage()
        );
    }

}
