<?php

declare(strict_types=1);

namespace Pgs\Api;

use Pgs\Core\Json;

final class Response
{
    private int $httpCode;
    private string $body;
    /** @var array<string, string> */
    private array $headers = [];
    private ?string $requestId = null;
    private bool $isJson = true;
    private mixed $payloadData = null;
    /** @var array{code: string, message: string, details: array}|null */
    private ?array $errorData = null;

    public function __construct(int $httpCode, string $body, array $headers = [], bool $isJson = true)
    {
        $this->httpCode = $httpCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->isJson = $isJson;

        if (!isset($this->headers['Cache-Control'])) {
            $this->headers['Cache-Control'] = 'no-store';
        }
        if ($isJson && !isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        }
    }

    public static function ok(mixed $data, int $code = 200): self
    {
        $resp = new self($code, '', [], true);
        $resp->payloadData = $data;
        return $resp;
    }

    public static function error(int $http, string $code, string $message, array $details = []): self
    {
        $resp = new self($http, '', [], true);
        $resp->errorData = [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ];
        return $resp;
    }

    public static function raw(int $http, string $body, string $contentType): self
    {
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store',
        ];
        return new self($http, $body, $headers, false);
    }

    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;
        $this->headers['X-Request-Id'] = $requestId;
        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function header(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function getHeader(string $k): ?string
    {
        foreach ($this->headers as $name => $val) {
            if (strcasecmp($name, $k) === 0) {
                return $val;
            }
        }
        return null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function status(): int
    {
        return $this->httpCode;
    }

    public function body(): mixed
    {
        return Json::decode($this->getBody());
    }

    public function getBody(): string
    {
        if ($this->isJson) {
            $envelope = [];
            if ($this->errorData !== null) {
                $envelope = [
                    'ok' => false,
                    'error' => [
                        'code' => $this->errorData['code'],
                        'message' => $this->errorData['message'],
                        'details' => empty($this->errorData['details']) ? (object) [] : $this->errorData['details'],
                    ],
                    'request_id' => $this->requestId ?? '',
                ];
            } else {
                $envelope = [
                    'ok' => true,
                    'data' => $this->payloadData,
                    'request_id' => $this->requestId ?? '',
                ];
            }
            return Json::encode($envelope);
        }

        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->httpCode);
            foreach ($this->headers as $k => $v) {
                header("{$k}: {$v}");
            }
        }

        echo $this->getBody();
    }
}
