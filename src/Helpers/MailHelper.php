<?php
namespace Ginto\Helpers;

/**
 * MailHelper - Simple email sending utility for Ginto.
 *
 * Sends email only when the installation is "live" (APP_URL starts with https://).
 * The "from" address is built from MAIL_FROM in .env, or falls back to
 * no-reply@<domain derived from APP_URL>.
 *
 * Uses PHP's native mail() which relies on the server's MTA (postfix/sendmail),
 * which is always present on a properly configured VPS/live install.
 */
class MailHelper
{
    /**
     * Returns true when the app is running in a live (HTTPS) environment.
     */
    public static function isLive(): bool
    {
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? '';
        return strpos($appUrl, 'https://') === 0;
    }

    /**
     * Derive the site domain from APP_URL or CADDY_DOMAIN.
     */
    public static function siteDomain(): string
    {
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? '';
        if ($appUrl && strpos($appUrl, 'https://') === 0) {
            $domain = preg_replace('#^https?://([^/]+).*$#', '$1', $appUrl);
            if ($domain) {
                return $domain;
            }
        }
        $caddyDomain = $_ENV['CADDY_DOMAIN'] ?? getenv('CADDY_DOMAIN') ?? '';
        if ($caddyDomain && $caddyDomain !== 'localhost') {
            return $caddyDomain;
        }
        return 'ginto.ai';
    }

    /**
     * Return the configured "from" address, defaulting to no-reply@{domain}.
     */
    public static function fromAddress(): string
    {
        $configured = $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?? '';
        if ($configured && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }
        return 'no-reply@' . self::siteDomain();
    }

    /**
     * Send an email. Returns true on success, false if not live or send fails.
     *
     * @param string $to      Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML message body
     * @param string $textBody Plain-text fallback (optional)
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (!self::isLive()) {
            error_log('[MailHelper] Email not sent (not a live install): ' . $subject . ' -> ' . $to);
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('[MailHelper] Invalid to address: ' . $to);
            return false;
        }

        $from = self::fromAddress();
        $domain = self::siteDomain();

        if (empty($textBody)) {
            $textBody = strip_tags($htmlBody);
        }

        $boundary = 'ginto_' . bin2hex(random_bytes(8));

        $headers = implode("\r\n", [
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
            error_log('[MailHelper] Sent: ' . $subject . ' -> ' . $to);
        } else {
            error_log('[MailHelper] Failed to send: ' . $subject . ' -> ' . $to);
        }

        return (bool) $result;
    }
}
