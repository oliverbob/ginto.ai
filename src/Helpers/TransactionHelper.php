<?php

namespace Ginto\Helpers;

/**
 * Helper class for transaction-related functions
 */
class TransactionHelper
{
    /**
     * Generate a unique alphanumeric transaction ID
     * Format: GNT-XXXXXXXX (8 random alphanumeric characters)
     * 
     * @param \Medoo\Medoo|null $db Database instance to check for uniqueness
     * @return string
     */
    public static function generateTransactionId($db = null): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Excluded I, O, 0, 1 to avoid confusion
        $maxAttempts = 10;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $id = 'GNT-';
            for ($i = 0; $i < 8; $i++) {
                $id .= $chars[random_int(0, strlen($chars) - 1)];
            }
            
            // If no database provided, just return the ID
            if ($db === null) {
                return $id;
            }
            
            // Check uniqueness
            $exists = $db->has('subscription_payments', ['transaction_id' => $id]);
            if (!$exists) {
                return $id;
            }
        }
        
        // Fallback: add timestamp suffix for uniqueness
        return 'GNT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
    
    /**
     * Capture comprehensive audit data for transaction tracking
     * Standard practice for fraud prevention and compliance
     * 
     * @return array Audit data to be saved with transaction
     */
    public static function captureAuditData(): array
    {
        $ip = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $sessionId = session_id() ?: null;
        
        // Parse user agent for device info
        $deviceInfo = self::parseUserAgent($userAgent);
        
        // Add request metadata
        $deviceInfo['referrer'] = $_SERVER['HTTP_REFERER'] ?? null;
        $deviceInfo['request_time'] = date('c');
        $deviceInfo['request_id'] = uniqid('req_', true);
        
        // Get geolocation from IP (async would be better for production)
        $geoData = self::getGeoFromIp($ip);
        
        return [
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'device_info' => json_encode($deviceInfo),
            'geo_country' => $geoData['country'] ?? null,
            'geo_city' => $geoData['city'] ?? null,
            'session_id' => $sessionId
        ];
    }
    
    /**
     * Get client IP address, handling proxies (Caddy, Nginx, Cloudflare)
     */
    public static function getClientIp(): ?string
    {
        // Priority order: most reliable proxy headers first
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx/Caddy proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header (can have multiple IPs)
            'HTTP_CLIENT_IP',            // Some proxies
            'HTTP_FORWARDED',            // RFC 7239
            'REMOTE_ADDR'                // Direct connection (fallback)
        ];
        
        // Debug: Log all IP-related headers when saving payments
        $debugHeaders = [];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $debugHeaders[$h] = $_SERVER[$h];
            }
        }
        if (!empty($debugHeaders)) {
            error_log('[TransactionHelper::getClientIp] Headers: ' . json_encode($debugHeaders));
        }
        
        $publicIp = null;
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $value = $_SERVER[$header];
                
                // Handle RFC 7239 Forwarded header format: for=192.0.2.60;proto=http
                if ($header === 'HTTP_FORWARDED') {
                    if (preg_match('/for=([^;,\s]+)/i', $value, $matches)) {
                        $value = trim($matches[1], '"[]');
                    } else {
                        continue;
                    }
                }
                
                // X-Forwarded-For can contain multiple IPs: client, proxy1, proxy2
                // The first one is the original client IP
                $ips = array_map('trim', explode(',', $value));
                
                foreach ($ips as $ip) {
                    // Clean up the IP (remove port if present)
                    $ip = preg_replace('/:\d+$/', '', $ip);
                    
                    // Validate IP format
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                        continue;
                    }
                    
                    // If it's a public IP, return it immediately
                    if (!self::isPrivateIp($ip)) {
                        return $ip;
                    }
                    
                    // Store first private IP as fallback
                    if ($publicIp === null) {
                        $publicIp = $ip;
                    }
                }
            }
        }
        
        // Return the fallback IP (private) or null
        return $publicIp;
    }
    
    /**
     * Check if an IP is private/localhost
     */
    public static function isPrivateIp(?string $ip): bool
    {
        if (!$ip) return true;
        
        // Check for localhost
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return true;
        }
        
        // Check for private IP ranges
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Get display-friendly IP (shows "Local Development" for localhost IPs)
     */
    public static function getDisplayIp(?string $ip = null): string
    {
        $ip = $ip ?? self::getClientIp();
        
        if (!$ip || self::isPrivateIp($ip)) {
            return 'Local Development';
        }
        
        return $ip;
    }
    
    /**
     * Parse user agent to extract browser, OS, device info
     */
    public static function parseUserAgent(?string $ua): array
    {
        if (!$ua) {
            return ['raw' => null];
        }
        
        $info = ['raw' => substr($ua, 0, 200)];
        
        // Detect browser
        if (preg_match('/Chrome\/(\d+)/i', $ua, $m)) {
            $info['browser'] = 'Chrome';
            $info['browser_version'] = $m[1];
        } elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m)) {
            $info['browser'] = 'Firefox';
            $info['browser_version'] = $m[1];
        } elseif (preg_match('/Safari\/(\d+)/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $info['browser'] = 'Safari';
            preg_match('/Version\/(\d+)/i', $ua, $m);
            $info['browser_version'] = $m[1] ?? null;
        } elseif (preg_match('/Edge\/(\d+)/i', $ua, $m) || preg_match('/Edg\/(\d+)/i', $ua, $m)) {
            $info['browser'] = 'Edge';
            $info['browser_version'] = $m[1];
        }
        
        // Detect OS
        if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $m)) {
            $info['os'] = 'Windows';
            $winVersions = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            $info['os_version'] = $winVersions[$m[1]] ?? $m[1];
        } elseif (preg_match('/Mac OS X (\d+[._]\d+)/i', $ua, $m)) {
            $info['os'] = 'macOS';
            $info['os_version'] = str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android (\d+)/i', $ua, $m)) {
            $info['os'] = 'Android';
            $info['os_version'] = $m[1];
        } elseif (preg_match('/iPhone|iPad/i', $ua)) {
            $info['os'] = 'iOS';
            preg_match('/OS (\d+)/i', $ua, $m);
            $info['os_version'] = $m[1] ?? null;
        } elseif (preg_match('/Linux/i', $ua)) {
            $info['os'] = 'Linux';
        }
        
        // Detect device type
        if (preg_match('/Mobile|Android|iPhone/i', $ua)) {
            $info['device_type'] = 'mobile';
        } elseif (preg_match('/iPad|Tablet/i', $ua)) {
            $info['device_type'] = 'tablet';
        } else {
            $info['device_type'] = 'desktop';
        }
        
        return $info;
    }
    
    /**
     * Get geolocation data from IP address
     * Uses ipapi.co (free tier: 1000 requests/day)
     */
    public static function getGeoFromIp(?string $ip): array
    {
        if (!$ip || $ip === '127.0.0.1' || $ip === '::1') {
            return ['country' => null, 'city' => 'localhost'];
        }
        
        try {
            // Use a fast timeout to not block the request
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $json = @file_get_contents("https://ipapi.co/{$ip}/json/", false, $ctx);
            
            if ($json) {
                $data = json_decode($json, true);
                return [
                    'country' => $data['country_code'] ?? null,
                    'city' => $data['city'] ?? null,
                    'region' => $data['region'] ?? null,
                    'timezone' => $data['timezone'] ?? null
                ];
            }
        } catch (\Throwable $e) {
            error_log("GeoIP lookup failed for {$ip}: " . $e->getMessage());
        }
        
        return ['country' => null, 'city' => null];
    }
}
