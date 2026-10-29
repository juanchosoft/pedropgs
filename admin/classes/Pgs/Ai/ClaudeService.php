<?php

declare(strict_types=1);

namespace Pgs\Ai;

use Anthropic\Client;
use Anthropic\RequestOptions;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Core\Exceptions\APIException;
use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client as GuzzleClient;
use Pgs\Core\Config;
use RuntimeException;

final class ClaudeService
{
    private Client $client;
    private string $model;
    private int $maxTokens;
    private int $maxTokensInforme;
    private string $effort;
    private string $effortInforme;

    public function __construct(?Client $client = null)
    {
        $apiKey = (string) Config::get('anthropic', 'api_key', '');
        $this->model = (string) Config::get('anthropic', 'model', 'claude-sonnet-5');
        $this->maxTokens = (int) Config::get('anthropic', 'max_tokens', 16000);
        $this->maxTokensInforme = (int) Config::get('anthropic', 'max_tokens_informe', 32000);
        $this->effort = (string) Config::get('anthropic', 'effort', 'medium');
        $this->effortInforme = (string) Config::get('anthropic', 'effort_informe', 'high');
        $timeout = (float) Config::get('anthropic', 'timeout_seconds', 120.0);

        if ($client !== null) {
            $this->client = $client;
        } else {
            $caBundle = CaBundle::getSystemCaRootBundlePath();
            $guzzle = new GuzzleClient([
                'verify' => $caBundle ?: true,
                'timeout' => $timeout,
            ]);

            $this->client = new Client(
                apiKey: $apiKey,
                requestOptions: RequestOptions::with(timeout: $timeout, transporter: $guzzle),
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $system
     * @param array<int, array<string, mixed>>|null $tools
     * @return mixed Anthropic Message object
     */
    public function crearMensaje(
        array $messages,
        array $system,
        ?array $tools = null,
        bool $paraInforme = false,
        ?string $container = null
    ): mixed {
        try {
            $params = [
                'maxTokens' => $paraInforme ? $this->maxTokensInforme : $this->maxTokens,
                'messages' => $messages,
                'model' => $this->model,
                'system' => $system,
                'thinking' => ['type' => 'adaptive'],
                'tools' => $tools,
                'outputConfig' => ['effort' => $paraInforme ? $this->effortInforme : $this->effort],
            ];

            if ($container !== null) {
                $params['container'] = $container;
            }

            return $this->client->messages->create(...$params);
        } catch (RateLimitException $e) {
            throw new RuntimeException('Se alcanzó el límite de solicitudes a la API de Anthropic. Intenta de nuevo en unos minutos.');
        } catch (APIException $e) {
            $mensaje = $e->getMessage();
            if (stripos($mensaje, 'api_key') !== false || stripos($mensaje, 'authentic') !== false) {
                throw new RuntimeException('La clave de API de Anthropic no es válida o no está configurada.');
            }
            if (stripos($mensaje, 'timeout') !== false) {
                throw new RuntimeException('La API de Anthropic tardó demasiado en responder. Intenta de nuevo.');
            }
            throw new RuntimeException('Error al comunicarse con la API de Anthropic: ' . $mensaje);
        } catch (\Throwable $e) {
            throw new RuntimeException('Error inesperado al generar la respuesta: ' . $e->getMessage());
        }
    }

    /**
     * Converts an SDK block object to array.
     */
    public static function bloqueAContenido(mixed $bloque): array
    {
        return json_decode(json_encode($bloque), true) ?? [];
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
