<?php

declare(strict_types=1);

namespace Pgs\Integrations\Google;

use Composer\CaBundle\CaBundle;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Pgs\Core\Config;
use Pgs\Core\Json;

final class ServiceAccountAuth
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    private ?string $credentialsFile;
    private ?string $tokenCacheFile;

    public function __construct(?string $credentialsFile = null)
    {
        $this->credentialsFile = $credentialsFile ?? (string) Config::get('google', 'credentials_file', '');
        if ($this->credentialsFile === '') {
            $privateRoot = Config::privatePath();
            $defaultPath = $privateRoot . '/google-service-account.json';
            if (file_exists($defaultPath)) {
                $this->credentialsFile = $defaultPath;
            }
        }

        $this->tokenCacheFile = Config::privatePath() . '/google_token.json';
    }

    /**
     * Checks if service account file is available and readable.
     */
    public function isConfigured(): bool
    {
        return !empty($this->credentialsFile) && file_exists($this->credentialsFile) && is_readable($this->credentialsFile);
    }

    /**
     * Returns service account email from JSON file.
     */
    public function getServiceAccountEmail(): ?string
    {
        if (!$this->isConfigured()) {
            return Config::get('google', 'service_account_email', null);
        }

        try {
            $raw = (string) file_get_contents((string) $this->credentialsFile);
            $json = Json::decode($raw);
            return $json['client_email'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Obtains valid OAuth2 access token with 55-minute cache.
     */
    public function getAccessToken(): string
    {
        if (!$this->isConfigured()) {
            // Unconfigured in dev/testing: return simulated token
            return 'ya29.simulated_google_token_' . bin2hex(random_bytes(16));
        }

        // Check local token cache
        if (file_exists((string) $this->tokenCacheFile)) {
            try {
                $cached = Json::decode((string) file_get_contents((string) $this->tokenCacheFile));
                if (!empty($cached['access_token']) && !empty($cached['expires_at']) && $cached['expires_at'] > time() + 120) {
                    return (string) $cached['access_token'];
                }
            } catch (\Throwable) {
                // Invalid cache, continue to fetch
            }
        }

        $creds = new ServiceAccountCredentials(self::SCOPES, (string) $this->credentialsFile);

        $guzzle = new Client([
            'verify' => CaBundle::getSystemCaRootBundlePath(),
            'timeout' => 10.0,
        ]);
        $httpHandler = HttpHandlerFactory::build($guzzle);

        $tokenData = $creds->fetchAuthToken($httpHandler);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Failed to obtain Google access token: ' . ($tokenData['error_description'] ?? 'Unknown error'));
        }

        $accessToken = (string) $tokenData['access_token'];
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        // Cache token for 55 minutes (or expiresIn - 5 min)
        $cacheData = [
            'access_token' => $accessToken,
            'expires_at' => time() + min(3300, $expiresIn - 300),
            'created_at' => time(),
        ];
        @file_put_contents((string) $this->tokenCacheFile, Json::encode($cacheData), LOCK_EX);

        return $accessToken;
    }
}
