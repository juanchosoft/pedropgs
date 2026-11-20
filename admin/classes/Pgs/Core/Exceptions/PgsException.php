<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

class PgsException extends \RuntimeException
{
    /**
     * @param array<string,string> $fields
     */
    public function __construct(
        string $message,
        protected string $errorCode = 'error',
        protected int $httpStatus = 500,
        protected array $fields = []
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string,string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
