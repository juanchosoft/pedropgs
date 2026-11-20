<?php

declare(strict_types=1);

namespace Pgs\Integrations\WhatsApp;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Pgs\Core\Config;
use Pgs\Core\Json;

final class WhatsAppClient
{
    private ClientInterface $http;
    private string $accessToken;
    private string $phoneNumberId;
    private string $graphVersion;
    private string $baseUrl;

    public function __construct(?ClientInterface $client = null)
    {
        $this->accessToken = (string) Config::get('whatsapp', 'access_token', '');
        $this->phoneNumberId = (string) Config::get('whatsapp', 'phone_number_id', '');
        $this->graphVersion = (string) Config::get('whatsapp', 'graph_version', 'v21.0');
        $this->baseUrl = 'https://graph.facebook.com/' . $this->graphVersion . '/' . $this->phoneNumberId;

        $this->http = $client ?? new Client([
            'timeout' => 10.0,
            'connect_timeout' => 5.0,
            'verify' => CaBundle::getSystemCaRootBundlePath(),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Uploads media file to WhatsApp Cloud API and returns media_id.
     */
    public function uploadMedia(string $absPath, string $mime): string
    {
        if (!file_exists($absPath) || !is_readable($absPath)) {
            throw new \InvalidArgumentException("Media file not found or unreadable: {$absPath}");
        }

        if ($this->accessToken === '' || $this->phoneNumberId === '') {
            throw new WhatsAppAuthException("WhatsApp credentials not configured");
        }

        try {
            $response = $this->http->request('POST', $this->baseUrl . '/media', [
                'multipart' => [
                    [
                        'name' => 'messaging_product',
                        'contents' => 'whatsapp',
                    ],
                    [
                        'name' => 'file',
                        'contents' => fopen($absPath, 'rb'),
                        'filename' => basename($absPath),
                        'headers' => ['Content-Type' => $mime],
                    ],
                    [
                        'name' => 'type',
                        'contents' => $mime,
                    ],
                ],
            ]);

            $body = (string) $response->getBody();
            $data = Json::decode($body);

            if (empty($data['id'])) {
                throw new WhatsAppException("Missing media id in response: {$body}", $response->getStatusCode());
            }

            return (string) $data['id'];
        } catch (\Throwable $e) {
            $this->handleException($e);
            throw $e; // fallback if handleException doesn't rethrow
        }
    }

    /**
     * Sends a template message to a recipient E.164 phone.
     *
     * @param string $toE164 Recipient in E.164 format (+1407...)
     * @param string $template Template name (e.g. 'pgs_task_created')
     * @param string $lang Language code ('es' or 'en_US')
     * @param list<string> $bodyParams Ordered text parameters
     * @param array{id?: string, filename?: string}|null $documentHeader Document header info
     * @return string Message WAMID (e.g. 'wamid.HBg...')
     */
    public function sendTemplate(
        string $toE164,
        string $template,
        string $lang,
        array $bodyParams,
        ?array $documentHeader = null
    ): string {
        if ($this->accessToken === '' || $this->phoneNumberId === '') {
            throw new WhatsAppAuthException("WhatsApp credentials not configured");
        }

        $cleanTo = preg_replace('/\D/', '', $toE164);
        if ($cleanTo === '') {
            throw new WhatsAppRecipientException("Invalid recipient phone: {$toE164}");
        }

        $components = [];

        // Document header component
        if ($documentHeader !== null && !empty($documentHeader['id'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'document',
                        'document' => [
                            'id' => (string) $documentHeader['id'],
                            'filename' => (string) ($documentHeader['filename'] ?? 'document.pdf'),
                        ],
                    ],
                ],
            ];
        }

        // Body component with parameters
        if (!empty($bodyParams)) {
            $bodyParameters = [];
            foreach ($bodyParams as $param) {
                $bodyParameters[] = [
                    'type' => 'text',
                    'text' => (string) $param,
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        $langCode = ($lang === 'en' || $lang === 'en_US') ? 'en_US' : 'es';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanTo,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $langCode],
                'components' => $components,
            ],
        ];

        try {
            $response = $this->http->request('POST', $this->baseUrl . '/messages', [
                'json' => $payload,
            ]);

            $body = (string) $response->getBody();
            $data = Json::decode($body);

            $wamid = $data['messages'][0]['id'] ?? null;
            if (!$wamid) {
                throw new WhatsAppException("Missing message id in Meta response: {$body}", $response->getStatusCode());
            }

            return (string) $wamid;
        } catch (\Throwable $e) {
            $this->handleException($e);
            throw $e;
        }
    }

    /**
     * Maps Meta Graph API error codes to typed WhatsAppExceptions.
     */
    private function handleException(\Throwable $e): void
    {
        if ($e instanceof ConnectException) {
            throw new WhatsAppRetryableException("Connection timeout to Meta WhatsApp API: " . $e->getMessage(), 0, null, null, $e);
        }

        if ($e instanceof RequestException && $e->hasResponse()) {
            $res = $e->getResponse();
            $status = $res ? $res->getStatusCode() : 500;
            $rawBody = $res ? (string) $res->getBody() : '';
            $errData = json_decode($rawBody, true);
            $metaError = $errData['error'] ?? [];

            $code = isset($metaError['code']) ? (int) $metaError['code'] : null;
            $subcode = isset($metaError['error_subcode']) ? (int) $metaError['error_subcode'] : null;
            $msg = (string) ($metaError['message'] ?? $e->getMessage());

            // 131026: Phone number not on WhatsApp, 131047: Message failed outside 24h window
            if ($code === 131026 || $code === 131047) {
                throw new WhatsAppRecipientException("WhatsApp recipient error: {$msg}", $status, $code, $subcode, $e);
            }

            // 190: Invalid OAuth / expired token
            if ($code === 190) {
                throw new WhatsAppAuthException("WhatsApp token authentication failure: {$msg}", $status, $code, $subcode, $e);
            }

            // 132000, 132001: Template does not exist or parameter count mismatch
            if ($code === 132000 || $code === 132001) {
                throw new WhatsAppTemplateException("WhatsApp template structure error: {$msg}", $status, $code, $subcode, $e);
            }

            // 429 Rate limited, 5xx server error
            if ($status === 429 || $status >= 500) {
                throw new WhatsAppRetryableException("Meta server error ({$status}): {$msg}", $status, $code, $subcode, $e);
            }

            throw new WhatsAppException("Meta WhatsApp API error ({$status}): {$msg}", $status, $code, $subcode, $e);
        }

        throw new WhatsAppException("WhatsApp error: " . $e->getMessage(), 0, null, null, $e);
    }
}
