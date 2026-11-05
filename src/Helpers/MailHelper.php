<?php
namespace Ginto\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * MailHelper - Email sending utility for Ginto.
 *
 * Uses PHPMailer over SMTP (port 587/465) when SMTP_HOST is configured.
 * Falls back to PHP mail() otherwise.
 *
 * Live detection is based on CADDY_DOMAIN being set to a non-localhost value,
 * since APP_URL is typically http://localhost (Caddy proxies the real domain).
 *
 * .env keys used:
 *   CADDY_DOMAIN  – public domain (set by installer or /live settings)
 *   MAIL_FROM     – sender address (e.g. no-reply@yourdomain.com)
 *   SMTP_HOST     – SMTP server hostname (e.g. smtp.gmail.com)
 *   SMTP_PORT     – SMTP port (587 for STARTTLS, 465 for SSL) default 587
 *   SMTP_USER     – SMTP username
 *   SMTP_PASS     – SMTP password / app-password
 *   SMTP_SECURE   – 'tls' (STARTTLS, default) or 'ssl'
 */
class MailHelper
{
    /**
     * Returns the public domain for this install (from CADDY_DOMAIN).
     * Returns empty string for local/dev installs.
     */
    public static function siteDomain(): string
    {
        // CADDY_DOMAIN is the real public domain set during installation.
        $caddyDomain = trim($_ENV['CADDY_DOMAIN'] ?? getenv('CADDY_DOMAIN') ?? '');
        if ($caddyDomain && $caddyDomain !== 'localhost') {
            return $caddyDomain;
        }
        // Fallback: derive from APP_URL if it happens to be an https:// address.
        $appUrl = trim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? '');
        if ($appUrl && strpos($appUrl, 'https://') === 0) {
            $domain = preg_replace('#^https?://([^/]+).*$#', '$1', $appUrl);
            if ($domain && $domain !== 'localhost') {
                return $domain;
            }
        }
        return '';
    }

    /**
     * Returns true when a real public domain is configured (live install).
     */
    public static function isLive(): bool
    {
        return self::siteDomain() !== '';
    }

    /**
     * Return the configured "from" address, defaulting to no-reply@{domain}.
     */
    public static function fromAddress(): string
    {
        $configured = trim($_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?? '');
        if ($configured && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }
        $domain = self::siteDomain();
        return $domain ? 'no-reply@' . $domain : '';
    }

    /**
     * Send an email via SMTP (PHPMailer) if SMTP_HOST is set,
     * otherwise falls back to PHP mail().
     * Returns false silently when not a live install.
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (!self::isLive()) {
            error_log('[MailHelper] Not a live install, skipping email: ' . $subject . ' -> ' . $to);
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('[MailHelper] Invalid to address: ' . $to);
            return false;
        }

        if (empty($textBody)) {
            $textBody = strip_tags($htmlBody);
        }

        $smtpHost = trim($_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? '');

        if ($smtpHost) {
            return self::sendViaSMTP($to, $subject, $htmlBody, $textBody, $smtpHost);
        }

        return self::sendViaMail($to, $subject, $htmlBody, $textBody);
    }

    // -------------------------------------------------------------------------
    // Private: PHPMailer SMTP
    // -------------------------------------------------------------------------

    private static function sendViaSMTP(string $to, string $subject, string $htmlBody, string $textBody, string $smtpHost): bool
    {
        $from       = self::fromAddress();
        $smtpPort   = (int) ($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? 587);
        $smtpUser   = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?? '';
        $smtpPass   = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?? '';
        $smtpSecure = strtolower(trim($_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?? 'tls'));

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->Port       = $smtpPort;
            $mail->SMTPAuth   = ($smtpUser !== '');
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = ($smtpSecure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom($from, 'Ginto');
            $mail->addAddress($to);
            $mail->Subject  = $subject;
            $mail->CharSet  = 'UTF-8';
            $mail->isHTML(true);
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $textBody;

            $mail->send();
            error_log('[MailHelper] SMTP sent: ' . $subject . ' -> ' . $to);
            return true;
        } catch (PHPMailerException $e) {
            error_log('[MailHelper] SMTP error: ' . $e->getMessage() . ' | ' . $subject . ' -> ' . $to);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Private: native mail() fallback
    // -------------------------------------------------------------------------

    private static function sendViaMail(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        $from   = self::fromAddress();
        $domain = self::siteDomain();

        $boundary = 'ginto_' . bin2hex(random_bytes(8));
        $headers  = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: Ginto <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: GintoAI',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        ]);

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $textBody . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlBody . "\r\n";
        $body .= "--{$boundary}--";

        $result = @mail($to, $subject, $body, $headers, '-f' . $from);
        if ($result) {
            error_log('[MailHelper] mail() sent: ' . $subject . ' -> ' . $to);
        } else {
            error_log('[MailHelper] mail() failed: ' . $subject . ' -> ' . $to);
        }
        return (bool) $result;
    }
}
