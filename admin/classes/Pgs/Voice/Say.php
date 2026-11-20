<?php

declare(strict_types=1);

namespace Pgs\Voice;

final class Say
{
    private const TEXTS = [
        'registered' => [
            'en' => 'Logged for {property}: {summary}, {priority} priority. Team notified.',
            'es' => 'Registrado para {property}: {summary}, prioridad {priority}. Equipo avisado.',
        ],
        'duplicate' => [
            'en' => "That's already logged.",
            'es' => 'Eso ya estaba registrado.',
        ],
        'need_property' => [
            'en' => 'Which property is this about?',
            'es' => '¿De qué propiedad se trata?',
        ],
        'transfer' => [
            'en' => 'Transferring you now.',
            'es' => 'Lo transfiero ahora.',
        ],
        'nobody' => [
            'en' => 'No one is available right now. I took your message and notified the team.',
            'es' => 'No hay nadie disponible. Tomé su mensaje y ya avisé al equipo.',
        ],
        'nobody_register' => [
            'en' => 'No one is available right now. I will take your message.',
            'es' => 'No hay nadie disponible. Voy a tomar su mensaje.',
        ],
        'no_open' => [
            'en' => "I don't see open requests under this number.",
            'es' => 'No veo requerimientos abiertos con este número.',
        ],
        'limited' => [
            'en' => 'I already have your messages. The team will follow up.',
            'es' => 'Ya tengo sus mensajes. El equipo dará seguimiento.',
        ],
        'fallback' => [
            'en' => "I've noted your message.",
            'es' => 'Tomé su mensaje.',
        ],
        'priority_urgent' => ['en' => 'urgent', 'es' => 'urgente'],
        'priority_high' => ['en' => 'high', 'es' => 'alta'],
        'priority_normal' => ['en' => 'normal', 'es' => 'normal'],
        'priority_low' => ['en' => 'low', 'es' => 'baja'],
        'status_received' => ['en' => 'received', 'es' => 'recibido'],
        'status_assigned' => ['en' => 'assigned', 'es' => 'asignado'],
        'status_in_progress' => ['en' => 'in progress', 'es' => 'en proceso'],
        'status_completed' => ['en' => 'completed', 'es' => 'completado'],
        'status_cancelled' => ['en' => 'cancelled', 'es' => 'cancelado'],
    ];

    /**
     * @param array<string,string> $vars
     */
    public static function text(string $key, string $lang, array $vars = []): string
    {
        $lang = $lang === 'es' ? 'es' : 'en';
        $text = self::TEXTS[$key][$lang] ?? self::TEXTS['fallback'][$lang];
        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', $value, $text);
        }

        return $text;
    }

    public static function priority(string $priority, string $lang): string
    {
        return self::text('priority_' . $priority, $lang);
    }

    public static function status(string $status, string $lang): string
    {
        return self::text('status_' . $status, $lang);
    }

    public static function lang(?string $lang): string
    {
        return $lang === 'es' ? 'es' : 'en';
    }
}
