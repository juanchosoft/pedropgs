<?php

declare(strict_types=1);

namespace Pgs\Notifications\Channels;

use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\SignedLinks;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class EmailChannel
{
    private array $smtpConfig;
    private \PDO $pdo;

    public function __construct(?array $smtpConfig = null, ?\PDO $pdo = null)
    {
        $this->smtpConfig = $smtpConfig ?? Config::section('smtp_notifications');
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Checks whether recipient email is authorized.
     */
    public function isRecipientAuthorized(string $email): bool
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return false;
        }

        // Check tec_usuarios (active users with notify_email matching)
        $userStmt = $this->pdo->prepare(
            'SELECT id FROM tec_usuarios 
             WHERE habilitado IN (\'yes\', \'si\')
               AND (LOWER(TRIM(notify_email)) = :email OR LOWER(TRIM(nickname)) = :email)
             LIMIT 1'
        );
        $userStmt->execute([':email' => $email]);
        if ($userStmt->fetchColumn()) {
            return true;
        }

        // Check tec_unit_contacts with receives_reports=1 or receives_notifications=1
        $contactStmt = $this->pdo->prepare(
            'SELECT id FROM tec_unit_contacts 
             WHERE is_active = 1 
               AND LOWER(TRIM(email)) = :email 
               AND (receives_reports = 1 OR receives_notifications = 1) 
             LIMIT 1'
        );
        $contactStmt->execute([':email' => $email]);
        if ($contactStmt->fetchColumn()) {
            return true;
        }

        return false;
    }

    /**
     * Sends an email notification.
     *
     * @param string $recipientEmail
     * @param string $subject
     * @param string $htmlBody
     * @param string|null $attachmentPath Absolute path in private storage
     * @param string|null $attachmentFilename Custom filename for attachment
     * @return string Delivery tracking ID (mail_...)
     */
    public function send(
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath = null,
        ?string $attachmentFilename = null
    ): string {
        $cleanEmail = trim($recipientEmail);
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email: {$recipientEmail}");
        }

        // Enforce recipient authorization rule (§2)
        if (!$this->isRecipientAuthorized($cleanEmail)) {
            throw new \InvalidArgumentException("Recipient {$cleanEmail} is not authorized to receive system notifications");
        }

        $mailer = new PHPMailer(true);

        try {
            $host = (string) ($this->smtpConfig['host'] ?? '');
            $port = (int) ($this->smtpConfig['port'] ?? 587);
            $user = (string) ($this->smtpConfig['username'] ?? '');
            $pass = (string) ($this->smtpConfig['password'] ?? '');
            $encryption = strtolower((string) ($this->smtpConfig['encryption'] ?? 'tls'));
            $fromAddr = (string) ($this->smtpConfig['from_address'] ?? 'notificaciones@pgscentrum.com');
            $fromName = (string) ($this->smtpConfig['from_name'] ?? 'PGS Centrum');

            if ($host !== '' && $user !== '') {
                $mailer->isSMTP();
                $mailer->Host = $host;
                $mailer->Port = $port;
                $mailer->SMTPAuth = true;
                $mailer->Username = $user;
                $mailer->Password = $pass;

                if ($encryption === 'ssl' || $port === 465) {
                    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls' || $port === 587) {
                    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
            }

            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($fromAddr, $fromName);
            $mailer->addAddress($cleanEmail);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);

            // Handle attachment: if PDF <= 10MB, attach file; if > 10MB, generate signed link (72h, 5 uses)
            $linkNotice = '';
            if ($attachmentPath !== null && file_exists($attachmentPath)) {
                $fileSize = filesize($attachmentPath);
                $maxBytes = 10 * 1024 * 1024; // 10MB

                $filename = $attachmentFilename ?? basename($attachmentPath);
                if ($fileSize <= $maxBytes) {
                    $mailer->addAttachment($attachmentPath, $filename);
                } else {
                    $token = SignedLinks::issue(
                        type: 'report_pdf',
                        ref: $attachmentPath,
                        filename: $filename,
                        userId: null,
                        channel: 'email',
                        ttlSeconds: 72 * 3600,
                        maxUses: 5
                    );
                    $linkUrl = Config::get('app', 'base_url', 'http://localhost/pgscentrum') . '/api/v1/files/' . $token;
                    $linkNotice = "<div style='margin-top:20px;padding:12px;background:#f6f7fb;border-left:4px solid #e10600;font-size:13px;'>
                        <strong>Archivo adjunto disponible:</strong><br/>
                        El documento supera 10MB. Puede descargarlo de forma segura en el siguiente enlace (válido por 72 horas):<br/>
                        <a href='{$linkUrl}' style='color:#e10600;font-weight:bold;' target='_blank'>Descargar archivo ({$filename})</a>
                    </div>";
                }
            }

            // Styled HTML template wrapper with PGS Centrum palette
            $fullHtml = $this->wrapTemplate($subject, $htmlBody . $linkNotice);
            $mailer->Body = $fullHtml;
            $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], ["\n", "\n", "\n\n"], $htmlBody));

            // If SMTP is enabled, send; otherwise return simulated id
            $smtpEnabled = (bool) Config::get('smtp_notifications', 'enabled', false);
            if ($smtpEnabled && $host !== '') {
                $mailer->send();
            }

            return 'mail_' . bin2hex(random_bytes(8));
        } catch (MailerException $e) {
            throw new \RuntimeException("PHPMailer error: " . $e->getMessage(), 0, $e);
        }
    }

    private function wrapTemplate(string $subject, string $bodyContent): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f6f7fb; margin: 0; padding: 20px; color: #0b0f19; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .header { background-color: #0b0f19; padding: 24px; text-align: center; border-bottom: 3px solid #e10600; }
    .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px; }
    .header .accent { color: #e10600; }
    .body { padding: 32px 24px; line-height: 1.6; font-size: 15px; color: #222b45; }
    .footer { background: #f6f7fb; padding: 18px 24px; text-align: center; font-size: 12px; color: #8f9bb3; border-top: 1px solid #edf1f7; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>PGS <span class="accent">CENTRUM</span></h1>
    </div>
    <div class="body">
      {$bodyContent}
    </div>
    <div class="footer">
      Este es un mensaje automático generado por PGS Centrum.<br/>
      Por favor no responda directamente a este correo.
    </div>
  </div>
</body>
</html>
HTML;
    }
}
