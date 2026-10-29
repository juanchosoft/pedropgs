<?php

declare(strict_types=1);

namespace Pgs\Ai;

use Composer\CaBundle\CaBundle;
use Pgs\Core\Config;
use RuntimeException;

final class ElevenLabsService
{
    private const BASE_URL = 'https://api.elevenlabs.io/v1';
    private const TTS_MAX_CHARS = 2500;

    private static ?string $caBundleCache = null;
    private static bool $caBundleResuelto = false;

    private static function caBundle(): ?string
    {
        if (!self::$caBundleResuelto) {
            self::$caBundleCache = CaBundle::getSystemCaRootBundlePath() ?: null;
            self::$caBundleResuelto = true;
        }
        return self::$caBundleCache;
    }

    private static function curlBase(string $apiKey): \CurlHandle
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['xi-api-key: ' . $apiKey],
        ]);
        $caBundle = self::caBundle();
        if ($caBundle) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }
        return $ch;
    }

    /**
     * Transcribes an audio file using ElevenLabs Scribe STT API.
     */
    public static function transcribir(string $rutaAudioLocal, string $lang = 'es'): string
    {
        $apiKey = (string) Config::get('elevenlabs', 'api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('La clave de API de ElevenLabs no está configurada.');
        }

        if (!file_exists($rutaAudioLocal)) {
            throw new RuntimeException("El archivo de audio no existe: {$rutaAudioLocal}");
        }

        $sttModel = (string) Config::get('elevenlabs', 'stt_model', 'scribe_v1');

        $ch = self::curlBase($apiKey);
        curl_setopt_array($ch, [
            CURLOPT_URL => self::BASE_URL . '/speech-to-text',
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => [
                'model_id' => $sttModel,
                'language_code' => $lang,
                'file' => new \CURLFile($rutaAudioLocal),
            ],
        ]);

        $respuesta = curl_exec($ch);
        $error = curl_error($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false) {
            throw new RuntimeException('Error de red al transcribir el audio: ' . $error);
        }
        if ($codigo !== 200) {
            throw new RuntimeException('ElevenLabs respondió con error al transcribir (HTTP ' . $codigo . ').');
        }

        $datos = json_decode((string) $respuesta, true);
        $texto = trim((string) ($datos['text'] ?? ''));
        if ($texto === '') {
            throw new RuntimeException('No se pudo reconocer ningún texto en el audio.');
        }

        return $texto;
    }

    /**
     * Synthesizes text into MP3 audio bytes using ElevenLabs TTS API.
     */
    public static function sintetizar(string $texto, ?string $voiceId = null): string
    {
        $apiKey = (string) Config::get('elevenlabs', 'api_key', '');
        $vid = $voiceId ?: (string) Config::get('elevenlabs', 'voice_id', '');

        if ($apiKey === '') {
            throw new RuntimeException('La clave de API de ElevenLabs no está configurada.');
        }
        if ($vid === '') {
            throw new RuntimeException('El voice_id de ElevenLabs no está configurado.');
        }

        $ttsModel = (string) Config::get('elevenlabs', 'tts_model', 'eleven_flash_v2_5');
        $textoLimpio = self::limpiarMarkdown($texto);
        $textoCortado = mb_substr($textoLimpio, 0, self::TTS_MAX_CHARS);

        $ch = self::curlBase($apiKey);
        curl_setopt_array($ch, [
            CURLOPT_URL => self::BASE_URL . '/text-to-speech/' . rawurlencode($vid) . '?output_format=mp3_44100_128',
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: audio/mpeg',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'text' => $textoCortado,
                'model_id' => $ttsModel,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $respuesta = curl_exec($ch);
        $error = curl_error($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false) {
            throw new RuntimeException('Error de red al generar el audio: ' . $error);
        }
        if ($codigo !== 200 || $respuesta === '') {
            throw new RuntimeException('ElevenLabs respondió con error al generar el audio (HTTP ' . $codigo . ').');
        }

        return (string) $respuesta;
    }

    /**
     * Removes markdown formatting so audio sounds natural when spoken.
     */
    public static function limpiarMarkdown(string $texto): string
    {
        $texto = preg_replace('/```[\s\S]*?```/', '', $texto) ?? $texto;
        $texto = preg_replace('/`([^`]+)`/', '$1', $texto) ?? $texto;
        $texto = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $texto) ?? $texto;
        $texto = preg_replace('/[*_#>]+/', '', $texto) ?? $texto;
        $texto = preg_replace('/^\s*[-•]\s+/m', '', $texto) ?? $texto;
        return trim($texto);
    }
}
