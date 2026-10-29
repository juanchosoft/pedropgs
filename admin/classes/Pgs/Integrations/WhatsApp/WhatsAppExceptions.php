<?php

declare(strict_types=1);

namespace Pgs\Integrations\WhatsApp;

class WhatsAppException extends \RuntimeException
{
    private ?int $errorCode;
    private ?int $errorSubcode;

    public function __construct(string $message, int $httpStatus = 0, ?int $errorCode = null, ?int $errorSubcode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus, $previous);
        $this->errorCode = $errorCode;
        $this->errorSubcode = $errorSubcode;
    }

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getErrorSubcode(): ?int
    {
        return $this->errorSubcode;
    }
}

class WhatsAppRecipientException extends WhatsAppException {}
class WhatsAppAuthException extends WhatsAppException {}
class WhatsAppTemplateException extends WhatsAppException {}
class WhatsAppRetryableException extends WhatsAppException {}
