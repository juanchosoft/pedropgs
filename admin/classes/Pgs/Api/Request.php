<?php

declare(strict_types=1);

namespace Pgs\Api;

use Pgs\Core\Config;

final class Request
{
    private string $method;
    private string $path;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private ?array $json = null;
    private string $rawBody;
    private string $ip;
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        string $method,
        string $path,
        array $headers = [],
        array $query = [],
        string $rawBody = '',
        string $ip = '127.0.0.1',
        array $attributes = []
    ) {
        $this->method = strtoupper($method);
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $normPath = '/' . ltrim((string) ($parsedPath ?: $path), '/');
        if (preg_match('#^/api(?:/v1)?(/.*)?$#i', $normPath, $m)) {
            $normPath = $m[1] ?? '/';
        }
        $this->path = ($normPath === '' ? '/' : $normPath);

        $queryStr = parse_url($path, PHP_URL_QUERY);
        if ($queryStr) {
            parse_str($queryStr, $parsedQuery);
            $query = array_merge($parsedQuery, $query);
        }

        $this->headers = [];
        foreach ($headers as $k => $v) {
            $this->headers[strtolower((string) $k)] = (string) $v;
        }

        $this->query = $query;
        $this->rawBody = $rawBody;
        $this->ip = $ip;
        $this->attributes = $attributes;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Extract normalized path relative to /api or /api/v1
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $uriPath = (string) parse_url($requestUri, PHP_URL_PATH);

        // Strip project prefix and /api(/v1) base
        $path = $uriPath;
        if (preg_match('#/api(?:/v1)?(/.*)?$#i', $uriPath, $matches)) {
            $path = $matches[1] ?? '/';
        }

        // Headers
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$headerName] = (string) $v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $k));
                $headers[$headerName] = (string) $v;
            }
        }

        // Special handling for Authorization header (Apache fastcgi/fpm drop)
        if (!isset($headers['authorization'])) {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = (string) $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (function_exists('apache_request_headers')) {
                $apacheHeaders = (array) apache_request_headers();
                foreach ($apacheHeaders as $ak => $av) {
                    if (strcasecmp((string) $ak, 'Authorization') === 0) {
                        $headers['authorization'] = (string) $av;
                        break;
                    }
                }
            }
        }

        $rawBody = (string) file_get_contents('php://input');
        $query = (array) $_GET;

        // IP resolution
        $trustProxy = (bool) Config::get('api', 'trust_proxy', false);
        $ip = '127.0.0.1';
        if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }

        return new self($method, $path, $headers, $query, $rawBody, $ip);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }

    public function query(string $k, mixed $d = null): mixed
    {
        return $this->query[$k] ?? $d;
    }

    public function json(): array
    {
        if ($this->json === null) {
            if ($this->rawBody === '' || $this->rawBody === '0') {
                $this->json = [];
            } else {
                $decoded = json_decode($this->rawBody, true);
                $this->json = is_array($decoded) ? $decoded : [];
            }
        }
        return $this->json;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function attr(string $k, mixed $d = null): mixed
    {
        return $this->attributes[$k] ?? $d;
    }

    public function withAttr(string $k, mixed $v): self
    {
        $clone = clone $this;
        $clone->attributes[$k] = $v;
        return $clone;
    }
}
