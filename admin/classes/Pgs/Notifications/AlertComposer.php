<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use Pgs\Core\Clock;

final class AlertComposer
{
    private const HEADINGS = [
        'new' => 'New request',
        'reminder' => 'Reminder: request not yet attended',
        'escalation' => 'Escalation: request not attended',
        'digest' => 'Pending requests',
    ];

    /**
     * @param array<string,mixed> $request
     * @return array{subject:string,html:string,text:string}
     */
    public static function email(array $request, string $kind, string $ackUrl, string $openUrl): array
    {
        $heading = self::HEADINGS[$kind] ?? self::HEADINGS['new'];
        $property = self::property($request);
        $urgent = ($request['priority'] ?? '') === 'urgent';
        $subject = ($urgent ? '[URGENT] ' : '') . ($kind === 'new' ? '' : ($kind === 'escalation' ? 'ESCALATION ' : 'Reminder ')) . $request['code'] . ' - ' . $property . ': ' . mb_substr((string) $request['summary'], 0, 80);

        $rows = [
            ['Request', (string) $request['code']],
            ['Property', $property],
            ['Priority', strtoupper((string) $request['priority'])],
            ['Summary', (string) $request['summary']],
        ];
        if (!empty($request['details'])) {
            $rows[] = ['Details', (string) $request['details']];
        }
        if (!empty($request['caller_name'])) {
            $rows[] = ['Caller', (string) $request['caller_name']];
        }
        if (!empty($request['caller_e164'])) {
            $rows[] = ['Callback', (string) $request['caller_e164']];
        }
        $rows[] = ['Received', self::localTime((string) $request['received_at'])];

        $table = '';
        $text = $heading . "\n";
        foreach ($rows as [$label, $value]) {
            $table .= '<tr><td style="padding:6px 12px 6px 0;color:#64748b;vertical-align:top;white-space:nowrap">' . htmlspecialchars($label)
                . '</td><td style="padding:6px 0;color:#0B0F19;font-weight:600">' . nl2br(htmlspecialchars($value)) . '</td></tr>';
            $text .= $label . ': ' . $value . "\n";
        }
        $text .= "\nAcknowledge: {$ackUrl}\nOpen: {$openUrl}\n";

        $accent = $urgent ? '#B30500' : '#E10600';
        $html = '<!DOCTYPE html><html><body style="margin:0;padding:20px;background:#F6F7FB;font-family:Arial,Helvetica,sans-serif">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e7eaf1;border-radius:12px;overflow:hidden">'
            . '<div style="background:#0B0F19;padding:16px 22px;border-bottom:3px solid ' . $accent . '"><span style="color:#fff;font-size:18px;font-weight:700">PGS <span style="color:' . $accent . '">CENTRUM</span></span></div>'
            . '<div style="padding:22px"><h2 style="margin:0 0 14px;font-size:18px;color:#0B0F19">' . htmlspecialchars($heading) . '</h2>'
            . '<table style="border-collapse:collapse;font-size:14px;width:100%">' . $table . '</table>'
            . '<p style="margin:22px 0 0"><a href="' . htmlspecialchars($ackUrl) . '" style="display:inline-block;background:' . $accent . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700">I have it</a>'
            . ' <a href="' . htmlspecialchars($openUrl) . '" style="display:inline-block;color:#0B0F19;text-decoration:none;padding:10px 14px;border:1px solid #e7eaf1;border-radius:8px;font-weight:700">Open in app</a></p></div>'
            . '<div style="background:#F6F7FB;padding:12px 22px;font-size:12px;color:#64748b">Automatic message from PGS Centrum.</div>'
            . '</div></body></html>';

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }

    /**
     * @param array<string,mixed> $request
     * @return list<string>
     */
    public static function whatsappParams(array $request): array
    {
        return [
            (string) $request['code'],
            self::property($request),
            mb_substr((string) $request['summary'], 0, 120),
            strtoupper((string) $request['priority']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $requests
     * @return array{subject:string,html:string,text:string}
     */
    public static function digest(array $requests, string $openUrl): array
    {
        $lines = '';
        $text = "Pending requests\n";
        foreach ($requests as $r) {
            $line = $r['code'] . ' - ' . self::property($r) . ': ' . mb_substr((string) $r['summary'], 0, 100) . ' [' . strtoupper((string) $r['priority']) . ']';
            $lines .= '<li style="margin:4px 0">' . htmlspecialchars($line) . '</li>';
            $text .= '- ' . $line . "\n";
        }
        $text .= "\nOpen: {$openUrl}\n";
        $html = '<!DOCTYPE html><html><body style="margin:0;padding:20px;background:#F6F7FB;font-family:Arial,Helvetica,sans-serif">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e7eaf1;border-radius:12px;padding:22px">'
            . '<h2 style="margin:0 0 12px;font-size:18px;color:#0B0F19">' . count($requests) . ' requests need attention</h2><ul style="padding-left:18px;font-size:14px;color:#0B0F19">' . $lines . '</ul>'
            . '<p><a href="' . htmlspecialchars($openUrl) . '" style="display:inline-block;background:#E10600;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700">Open in app</a></p></div></body></html>';

        return ['subject' => count($requests) . ' PGS requests need attention', 'html' => $html, 'text' => $text];
    }

    /** @param array<string,mixed> $request */
    private static function property(array $request): string
    {
        if (!empty($request['unit_name'])) {
            return (string) $request['unit_name'];
        }
        if (!empty($request['property_text'])) {
            return (string) $request['property_text'] . ' (unconfirmed)';
        }

        return 'Property not identified';
    }

    private static function localTime(string $utc): string
    {
        return Clock::toLocal($utc)->format('M j, Y g:i A T');
    }
}
