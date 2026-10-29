<?php

declare(strict_types=1);

namespace Pgs\Core\Web;

use Pgs\Auth\Principal;
use Pgs\Auth\PrincipalFactory;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\PgsException;
use Pgs\Core\Exceptions\PinLockedException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Exceptions\VerificationRequiredException;

/**
 * Runs an AJAX operation as the logged-in principal and answers with a single envelope that every
 * page script understands:
 *
 *   success: {ok:true, data:P, output:{valid:true, response:P}, ...P when P is an associative array}
 *   failure: {ok:false, error:"message", code:"422", fields:{}, output:{valid:false, response:{code, content, ...}}}
 */
final class PgsAjax
{
    /**
     * @param array<string,mixed> $rqst
     */
    public static function run(array $rqst, callable $handler, bool $wrapSuccess = true): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $p = PrincipalFactory::fromSession('web');
        if ($p->isAnonymous()) {
            return self::failure(401, 'Session expired or not authenticated.');
        }

        try {
            $result = $handler($p, $rqst);
            if (is_string($result)) {
                return $result;
            }
            return self::success($result);
        } catch (ForbiddenException $e) {
            return self::failure(403, $e->getMessage() ?: 'You do not have permission to perform this operation.');
        } catch (ValidationException $e) {
            return self::failure(422, $e->getMessage(), ['fields' => $e->fields()]);
        } catch (NotFoundException $e) {
            return self::failure(404, $e->getMessage());
        } catch (ConflictException $e) {
            return self::failure(409, $e->getMessage(), ['details' => $e->fields()]);
        } catch (PinLockedException | VerificationRequiredException $e) {
            return self::failure(403, $e->getMessage());
        } catch (PgsException $e) {
            return self::failure($e->httpStatus(), $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[PgsAjax Error] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return self::failure(500, 'An internal server error occurred.');
        }
    }

    private static function success(mixed $payload): string
    {
        $envelope = [
            'ok' => true,
            'data' => $payload,
            'output' => ['valid' => true, 'response' => $payload],
        ];
        if (is_array($payload) && $payload !== [] && !array_is_list($payload)) {
            $envelope = array_merge($payload, $envelope);
        }
        return json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $extra
     */
    private static function failure(int $status, string $message, array $extra = []): string
    {
        http_response_code($status);
        return json_encode(
            array_merge([
                'ok' => false,
                'error' => $message,
                'code' => (string) $status,
                'output' => [
                    'valid' => false,
                    'response' => array_merge(['code' => (string) $status, 'content' => $message], $extra),
                ],
            ], $extra),
            JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }
}
