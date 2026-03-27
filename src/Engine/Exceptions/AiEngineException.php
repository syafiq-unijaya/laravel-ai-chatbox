<?php
namespace SyafiqUnijaya\AiChatbox\Engine\Exceptions;

class AiEngineException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $httpStatus = 500,
        ? \Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus() : int
    {
        return $this->getCode();
    }
}
