<?php
namespace App\Controllers;

use Core\Controller;
use Medoo\Medoo;
use DOMDocument;
use DOMXPath;
use Throwable;

class URLMDController extends Controller {
    private const USER_AGENT = 'Mozilla/5.0 (compatible; MetadataBot/1.0)';
    private const TIMEOUT = 10;
    
    /**
     * Extract URL and metadata from text input
     */
    public function extractUrlMetadata(string $text): ?array {
        $url = $this->findUrl($text);
        if (!$url) {
            return null; // No URL found, so we exit early.
        }
        
        $normalizedUrl = $this->normalizeUrl($url);
        if (!$normalizedUrl) {
            // This case is unlikely if findUrl succeeded, but good for safety.
            return null;
        }
        
        $metadata = $this->fetchMetadata($normalizedUrl);

        // NEW -> Perform the text replacement here, where we have all the parts.
        // We use str_ireplace for a case-insensitive replacement.
        $replacedText = str_ireplace(
            $url,           // The string that was found (e.g., "Github.com")
            $normalizedUrl, // The string to replace it with (e.g., "https://Github.com")
            $text           // The original input string
        );
        
        return [
            'original_input' => $text,
            'found_url'      => $url,
            'normalized_url' => $normalizedUrl,
            'domain'         => $this->extractDomain($normalizedUrl),
            'title'          => $metadata['title'],
            'description'    => $metadata['description'], 
            'image'          => $metadata['image'],
            'success'        => $metadata['success'],
            'replaced_text'  => $replacedText, // NEW -> Add the result to the array.
        ];
    }
    
    /**
     * Standard URL detection regex
     */
    private function findUrl(string $text): ?string {
        // Standard URL regex pattern
        $pattern = '/https?:\/\/(?:[-\w.])+(?:[:\d]+)?(?:\/(?:[\w\/_.])*(?:\?(?:[\w&%=.])*)?(?:#(?:\w)*)?)?|(?:www\.)?[-\w.]+\.(?:[a-zA-Z]{2,4})(?:\/\S*)?/i';
        
        if (preg_match($pattern, $text, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
    
    /**
     * Standard URL normalization
     */
    private function normalizeUrl(string $url): ?string {
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return $url;
    }
    
    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string {
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? '';
        
        // Remove www prefix (standard practice)
        return preg_replace('/^www\./', '', $domain);
    }
    
    /**
     * Fetch and parse metadata using standard methods
     */
    private function fetchMetadata(string $url): array {
        $metadata = [
            'title' => null,
            'description' => null,
            'image' => null,
            'success' => false
        ];
        
        try {
            $html = $this->fetchHtml($url);
            if (!$html) {
                return $metadata;
            }
            
            $parsed = $this->parseMetadata($html);
            $metadata = array_merge($metadata, $parsed);
            $metadata['success'] = true;
            
        } catch (Exception $e) {
            error_log("Metadata extraction error: " . $e->getMessage());
        }
        
        return $metadata;
    }
    
    /**
     * Standard HTTP request with proper headers
     */
    private function fetchHtml(string $url): ?string {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($html === false || $httpCode >= 400) {
            return null;
        }
        
        return $html;
    }
    
    /**
     * Parse metadata following standard priority order
     */
    private function parseMetadata(string $html): array {
        $metadata = [
            'title' => null,
            'description' => null,
            'image' => null
        ];
        
        // Use DOMDocument for reliable parsing (standard approach)
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors(false);
        
        $xpath = new DOMXPath($doc);
        
        // Standard metadata extraction with priority order
        $metadata['title'] = $this->extractTitle($xpath);
        $metadata['description'] = $this->extractDescription($xpath);
        $metadata['image'] = $this->extractImage($xpath);
        
        return $metadata;
    }
    
    /**
     * Extract title following standard priority
     */
    private function extractTitle(DOMXPath $xpath): ?string {
        $queries = [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content', 
            '//title/text()'
        ];
        
        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $title = trim($result->item(0)->nodeValue);
                if (!empty($title)) {
                    return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract description following standard priority
     */
    private function extractDescription(DOMXPath $xpath): ?string {
        $queries = [
            '//meta[@property="og:description"]/@content',
            '//meta[@name="twitter:description"]/@content',
            '//meta[@name="description"]/@content'
        ];
        
        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $description = trim($result->item(0)->nodeValue);
                if (!empty($description)) {
                    return html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract image following standard priority
     */
    private function extractImage(DOMXPath $xpath): ?string {
        $queries = [
            '//meta[@property="og:image"]/@content',
            '//meta[@name="twitter:image"]/@content'
        ];
        
        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $imageUrl = trim($result->item(0)->nodeValue);
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    return $imageUrl;
                }
            }
        }
        
        return null;
    }

    public function test() {
        // 1. Define the test inputs
        $text = "Github.com has a lot of opensource.";
        $result = $this->extractUrlMetadata($text);
        $this->view('test', ['result' => $result]);
    }
}

?>