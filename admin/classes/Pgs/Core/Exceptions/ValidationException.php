<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class ValidationException extends PgsException
{
    /**
     * @param array<string,mixed>|string $fieldsOrMessage
     */
    public function __construct(array|string $fieldsOrMessage, string $message = 'Validation failed')
    {
        if (is_string($fieldsOrMessage)) {
            $fields = ['error' => $fieldsOrMessage];
            $msg = $fieldsOrMessage;
        } else {
            $fields = $fieldsOrMessage;
            $msg = $message !== 'Validation failed' ? $message : (reset($fields) ?: $message);
        }
        parent::__construct($msg, 'validation_failed', 422, $fields);
    }
}
