<?php

declare(strict_types=1);

namespace Pgs\Admin;

use Pgs\Core\AppLog;
use Pgs\Core\Csrf;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\PgsException;
use Pgs\Core\Exceptions\ValidationException;

final class AdminAjax
{
    /**
     * Runs an administration operation: POST only, CSRF protected, JSON in the legacy envelope.
     *
     * @param callable(AdminContext,array<string,mixed>):mixed $handler
     * @param array<string,mixed> $rqst
     */
    public static function run(array $rqst, callable $handler): string
    {
        if (!headers_sent()) {
            header_remove('Access-Control-Allow-Origin');
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new ForbiddenException('POST required');
            }
            Csrf::verify(isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : null);

            return self::ok($handler(AdminContext::fromSession(), $rqst));
        } catch (ValidationException $e) {
            return self::fail(422, $e->getMessage(), $e->fields());
        } catch (ForbiddenException $e) {
            return self::fail(403, $e->getMessage());
        } catch (NotFoundException $e) {
            return self::fail(404, $e->getMessage());
        } catch (PgsException $e) {
            return self::fail($e->httpStatus(), $e->getMessage());
        } catch (\Throwable $e) {
            AppLog::error('admin-ajax', $e, ['op' => (string) ($rqst['op'] ?? '')]);

            return self::fail(500, 'Unexpected error');
        }
    }

    private static function ok(mixed $data): string
    {
        return (string) json_encode(['output' => ['valid' => true, 'response' => $data]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param array<string,mixed> $fields
     */
    private static function fail(int $status, string $message, array $fields = []): string
    {
        if (!headers_sent()) {
            http_response_code($status);
        }

        return (string) json_encode(['output' => ['valid' => false, 'response' => ['content' => $message, 'fields' => $fields]]], JSON_UNESCAPED_UNICODE);
    }
}
