<?php
namespace Ginto\Helpers;

/**
 * MailHelper - Simple email sending utility for Ginto.
 *
 * Ginto runs on localhost and is proxied by Caddy to a real domain.
 * APP_URL is therefore usually "http://localhost", so CADDY_DOMAIN is used
 * as the authoritative public-facing domain and to determine whether this
 * is a live (internet-facing) install.
 *
 * The "from" address is built from MAIL_FROM in .env, or falls back to
 * no-reply@<CADDY_DOMAIN>.
 *
 * Uses PHP's native mail() which relies on the server's MTA (postfix/sendmail).
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
