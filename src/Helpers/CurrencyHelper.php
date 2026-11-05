<?php
namespace Ginto\Helpers;

class CurrencyHelper
{
    // Minimal mapping of country alpha-2 to currency code
    public static function countryToCurrency(string $alpha2): string
    {
        $map = [
            'PH' => 'PHP', 'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'AU' => 'AUD',
            'EU' => 'EUR', 'FR' => 'EUR', 'DE' => 'EUR', 'ES' => 'EUR', 'IT' => 'EUR',
            'JP' => 'JPY', 'CN' => 'CNY', 'SG' => 'SGD', 'IN' => 'INR', 'MY' => 'MYR',
        ];
        $alpha2 = strtoupper($alpha2);
        return $map[$alpha2] ?? 'USD';
    }

    // Try to detect country from user session, Accept-Language, or fallback
    public static function detectCurrency(): string
    {
        // 1) If session has user country (common in this app), try to use it
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['user_country'])) {
            return self::countryToCurrency($_SESSION['user_country']);
        }

        // 2) Try Accept-Language header (format: en-US,en;q=0.9)
        $al = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($al) {
            $parts = explode(',', $al);
            if (isset($parts[0])) {
                // region often after hyphen
                $sub = explode('-', $parts[0]);
                if (isset($sub[1])) {
                    return self::countryToCurrency($sub[1]);
                }
            }
        }

        // 3) Try GeoIP via ipapi.co (used in register/dashboard client-side)
        // Use the client's REMOTE_ADDR when available; skip localhost addresses
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip && !in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'])) {
            try {
                $url = 'https://ipapi.co/' . $ip . '/country_code/';
                $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                $code = @file_get_contents($url, false, $ctx);
                if ($code) {
                    $code = trim(strtoupper($code));
                    if ($code) return self::countryToCurrency($code);
                }
            } catch (\Throwable $_) {
                // ignore failures
            }
        }

        // 3) Fallback to site default defined in env `APP_DEFAULT_CURRENCY`
        $env = getenv('APP_DEFAULT_CURRENCY');
        if ($env) return strtoupper($env);

        // Final fallback
        return 'PHP';
    }

    public static function formatAmount($amount, $currency = 'USD')
    {
        // Use PHP Intl if available
        if (class_exists('\NumberFormatter')) {
            try {
                $fmt = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
                $fmt->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
                return $fmt->formatCurrency((float)$amount, $currency);
            } catch (\Throwable $e) {
                // ignore and fallback
            }
        }
        // Fallback simple formatting
        return ($currency ? $currency . ' ' : '') . number_format((float)$amount, 2);
    }
}
