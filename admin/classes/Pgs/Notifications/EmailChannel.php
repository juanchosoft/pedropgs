<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Pgs\Core\Config;

final class EmailChannel
{
    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed>|null $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? Config::section('smtp_notifications');
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']) && (string) ($this->config['host'] ?? '') !== '';
    }

    /**
     * Sends one email, or returns a simulated id when SMTP is disabled.
     *
     * @return string delivery id
     */
    public function send(string $to, string $subject, string $html, string $text): string
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid recipient email');
        }
        if (!$this->isEnabled()) {
            return 'sim_' . bin2hex(random_bytes(8));
        }

        $mailer = new PHPMailer(true);
        try {
            $port = (int) ($this->config['port'] ?? 587);
            $mailer->isSMTP();
            $mailer->Host = (string) $this->config['host'];
            $mailer->Port = $port;
            $mailer->SMTPAuth = (string) ($this->config['username'] ?? '') !== '';
            $mailer->Username = (string) ($this->config['username'] ?? '');
            $mailer->Password = (string) ($this->config['password'] ?? '');
            $mailer->Timeout = 15;
            $encryption = strtolower((string) ($this->config['encryption'] ?? ''));
            if ($encryption === 'ssl' || $port === 465) {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls' || $port === 587) {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom((string) ($this->config['from_address'] ?? 'notifications@pgscentrum.com'), (string) ($this->config['from_name'] ?? 'PGS Centrum'));
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $html;
            $mailer->AltBody = $text;
            $mailer->send();

            return 'mail_' . bin2hex(random_bytes(8));
        } catch (MailerException $e) {
            throw new \RuntimeException('Email delivery failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
