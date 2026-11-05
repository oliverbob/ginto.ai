<?php

declare(strict_types=1);

namespace Ginto\Handlers;

use Ginto\Database;

/**
 * DocumentRagService - Handles document upload, text extraction, and RAG retrieval
 * 
 * Supports: PDF, TXT, MD, DOC, DOCX, RTF, HTML
 * Extracts text content and stores it for retrieval-augmented generation
 */
class DocumentRagService
{
    private $db;
    
    // Supported document types and their MIME types
    private const SUPPORTED_TYPES = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/markdown' => 'md',
        'text/x-markdown' => 'md',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/rtf' => 'rtf',
        'text/rtf' => 'rtf',
        'text/html' => 'html',
        'application/xhtml+xml' => 'html',
    ];
    
    // Max file size (10MB)
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    
    // Max extracted text length per document (100KB)
    private const MAX_TEXT_LENGTH = 100 * 1024;
    
    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Upload and process a document for RAG
     * 
     * @param int $userId The user ID
     * @param string $base64Data The base64-encoded file data (with data: prefix)
     * @param string $filename Original filename
     * @return array Result with success, document_id, extracted_text preview
     */
    public function uploadDocument(int $userId, string $base64Data, string $filename): array
    {
        // Parse the base64 data URL
        if (!preg_match('/^data:([^;]+);base64,(.+)$/i', $base64Data, $matches)) {
            return ['success' => false, 'error' => 'Invalid file format'];
        }
        
        $mimeType = strtolower($matches[1]);
        $base64Content = $matches[2];
        $binaryData = base64_decode($base64Content);
        
        if ($binaryData === false) {
            return ['success' => false, 'error' => 'Failed to decode file data'];
        }
        
        // Validate file size
        if (strlen($binaryData) > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File too large (max 10MB)'];
        }
        
        // Validate MIME type
        if (!isset(self::SUPPORTED_TYPES[$mimeType])) {
            // Try to detect from extension
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $extToMime = [
                'pdf' => 'application/pdf',
                'txt' => 'text/plain',
                'md' => 'text/markdown',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'rtf' => 'application/rtf',
                'html' => 'text/html',
                'htm' => 'text/html',
            ];
            
            if (isset($extToMime[$ext])) {
                $mimeType = $extToMime[$ext];
            } else {
                return ['success' => false, 'error' => 'Unsupported file type. Supported: PDF, TXT, MD, DOC, DOCX, RTF, HTML'];
            }
        }
        
        $docType = self::SUPPORTED_TYPES[$mimeType] ?? 'txt';
        
        // Create upload directory
        $uploadDir = STORAGE_PATH . '/rag_documents/' . $userId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        $uniqueFilename = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeFilename;
        $filepath = $uploadDir . $uniqueFilename;
        
        // Save the original file
        if (file_put_contents($filepath, $binaryData) === false) {
            return ['success' => false, 'error' => 'Failed to save document'];
        }
        
        // Extract text content
        $extractedText = $this->extractText($filepath, $docType);
        
        if (empty($extractedText)) {
            // Clean up the file if extraction failed
            @unlink($filepath);
            return ['success' => false, 'error' => 'Could not extract text from document. Please ensure the file contains readable text.'];
        }
        
        // Truncate if too long
        if (strlen($extractedText) > self::MAX_TEXT_LENGTH) {
            $extractedText = substr($extractedText, 0, self::MAX_TEXT_LENGTH) . "\n\n[Document truncated - showing first 100KB of text]";
        }
        
        // Store in database
        try {
            $this->db->insert('user_documents', [
                'user_id' => $userId,
                'filename' => $filename,
                'stored_filename' => $uniqueFilename,
                'file_path' => $filepath,
                'mime_type' => $mimeType,
                'doc_type' => $docType,
                'file_size' => strlen($binaryData),
                'extracted_text' => $extractedText,
                'text_length' => strlen($extractedText),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            $documentId = (int)$this->db->id();
            
            return [
                'success' => true,
                'document_id' => $documentId,
                'filename' => $filename,
                'doc_type' => $docType,
                'text_length' => strlen($extractedText),
                'preview' => substr($extractedText, 0, 500) . (strlen($extractedText) > 500 ? '...' : ''),
            ];
            
        } catch (\Throwable $e) {
            @unlink($filepath);
            error_log("[DocumentRagService] DB error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error while saving document'];
        }
    }
    
    /**
     * Extract text content from a document
     */
    private function extractText(string $filepath, string $docType): string
    {
        switch ($docType) {
            case 'pdf':
                return $this->extractFromPdf($filepath);
            case 'txt':
            case 'md':
                return $this->extractFromText($filepath);
            case 'docx':
                return $this->extractFromDocx($filepath);
            case 'doc':
                return $this->extractFromDoc($filepath);
            case 'rtf':
                return $this->extractFromRtf($filepath);
            case 'html':
                return $this->extractFromHtml($filepath);
            default:
                return $this->extractFromText($filepath);
        }
    }
    
    /**
     * Extract text from PDF using Vision-Centric RAG approach
     * 
     * Strategy:
     * 1. Try pdftotext first (fast, works for digital PDFs)
     * 2. If text is garbage/non-UTF8, use OCR on rasterized pages
     * 3. For images/diagrams within PDFs, use Vision LLM for contextual descriptions
     */
    private function extractFromPdf(string $filepath): string
    {
        // Step 1: Try pdftotext first (most reliable for digital PDFs)
        $text = $this->extractPdfTextLayer($filepath);
        
        // Check if extracted text is valid (not garbage/non-UTF8)
        if (!empty($text) && $this->isValidUtf8Text($text)) {
            error_log("[DocumentRagService] PDF text layer extraction successful");
            return $this->cleanText($text);
        }
        
        error_log("[DocumentRagService] PDF text layer failed or invalid, trying Vision-Centric approach");
        
        // Step 2: Vision-Centric RAG - Convert pages to images and use OCR
        $visionText = $this->extractPdfVisionCentric($filepath);
        if (!empty($visionText)) {
            return $visionText;
        }
        
        // Step 3: Last resort - basic stream extraction
        return $this->extractPdfStreams($filepath);
    }
    
    /**
     * Extract text layer from PDF using pdftotext
     */
    private function extractPdfTextLayer(string $filepath): string
    {
        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
        if ($pdftotext && is_executable($pdftotext)) {
            $output = shell_exec(escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($filepath) . ' - 2>/dev/null');
            if (!empty($output)) {
                return $output;
            }
        }
        
        // Fallback: Try using Smalot PDF Parser if available
        if (class_exists('\\Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filepath);
                return $pdf->getText();
            } catch (\Throwable $e) {
                error_log("[DocumentRagService] PDF Parser error: " . $e->getMessage());
            }
        }
        
        return '';
    }
    
    /**
     * Vision-Centric PDF extraction:
     * 1. Rasterize PDF pages to images (300 DPI)
     * 2. Use Tesseract OCR for text
     * 3. Optionally use Vision LLM for complex layouts/diagrams
     */
    private function extractPdfVisionCentric(string $filepath): string
    {
        // Check if required tools are available
        $gsPath = trim(shell_exec('which gs 2>/dev/null') ?? '');
        $tesseractPath = trim(shell_exec('which tesseract 2>/dev/null') ?? '');
        $convertPath = trim(shell_exec('which convert 2>/dev/null') ?? '');
        
        if (empty($gsPath) && empty($convertPath)) {
            error_log("[DocumentRagService] Neither ghostscript nor ImageMagick available for PDF rasterization");
            return '';
        }
        
        if (empty($tesseractPath)) {
            error_log("[DocumentRagService] Tesseract OCR not available");
            return '';
        }
        
        // Create temp directory for page images
        $tempDir = sys_get_temp_dir() . '/pdf_vision_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        try {
            // Get page count
            $pageCount = $this->getPdfPageCount($filepath);
            $maxPages = min($pageCount, 50); // Limit to 50 pages
            
            $allText = [];
            
            for ($page = 1; $page <= $maxPages; $page++) {
                $pageImagePath = $tempDir . "/page_{$page}.png";
                
                // Rasterize page to 300 DPI image
                if ($this->rasterizePdfPage($filepath, $page, $pageImagePath, $gsPath, $convertPath)) {
                    // Run OCR with language detection
                    $pageText = $this->runOcr($pageImagePath, $tesseractPath);
                    
                    if (!empty($pageText)) {
                        $allText[] = "--- Page $page ---\n" . $pageText;
                    }
                    
                    // Clean up page image
                    @unlink($pageImagePath);
                }
            }
            
            // Clean up temp directory
            @rmdir($tempDir);
            
            if (!empty($allText)) {
                return $this->cleanText(implode("\n\n", $allText));
            }
            
        } catch (\Throwable $e) {
            error_log("[DocumentRagService] Vision-Centric extraction error: " . $e->getMessage());
            // Clean up on error
            array_map('unlink', glob($tempDir . '/*'));
            @rmdir($tempDir);
        }
        
        return '';
    }
    
    /**
     * Get PDF page count
     */
    private function getPdfPageCount(string $filepath): int
    {
        // Try pdfinfo first
        $pdfinfo = trim(shell_exec('which pdfinfo 2>/dev/null') ?? '');
        if ($pdfinfo && is_executable($pdfinfo)) {
            $output = shell_exec(escapeshellcmd($pdfinfo) . ' ' . escapeshellarg($filepath) . ' 2>/dev/null');
            if (preg_match('/Pages:\s*(\d+)/', $output, $matches)) {
                return (int)$matches[1];
            }
        }
        
        // Fallback: count page objects in PDF
        $content = file_get_contents($filepath);
        if ($content && preg_match_all('/\/Type\s*\/Page[^s]/i', $content, $matches)) {
            return count($matches[0]);
        }
        
        return 1; // Assume at least 1 page
    }
    
    /**
     * Rasterize a PDF page to an image
     */
    private function rasterizePdfPage(string $pdfPath, int $page, string $outputPath, string $gsPath, string $convertPath): bool
    {
        // Prefer Ghostscript for better quality
        if (!empty($gsPath) && is_executable($gsPath)) {
            $cmd = sprintf(
                '%s -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r300 -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>/dev/null',
                escapeshellcmd($gsPath),
                $page,
                $page,
                escapeshellarg($outputPath),
                escapeshellarg($pdfPath)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputPath)) {
                return true;
            }
        }
        
        // Fallback to ImageMagick
        if (!empty($convertPath) && is_executable($convertPath)) {
            $pageIndex = $page - 1; // ImageMagick uses 0-based index
            $cmd = sprintf(
                '%s -density 300 %s[%d] -quality 100 %s 2>/dev/null',
                escapeshellcmd($convertPath),
                escapeshellarg($pdfPath),
                $pageIndex,
                escapeshellarg($outputPath)
            );
            exec($cmd, $output, $returnCode);
            
            return $returnCode === 0 && file_exists($outputPath);
        }
        
        return false;
    }
    
    /**
     * Run Tesseract OCR on an image
     */
    private function runOcr(string $imagePath, string $tesseractPath): string
    {
        // Use multiple languages: English, Filipino, Chinese (simplified)
        // These are installed via gintoai.sh
        $languages = 'eng+fil';
        
        // Check if chi_sim is available
        $availableLangs = shell_exec(escapeshellcmd($tesseractPath) . ' --list-langs 2>/dev/null');
        if (strpos($availableLangs, 'chi_sim') !== false) {
            $languages .= '+chi_sim';
        }
        
        $outputBase = $imagePath . '_ocr';
        $cmd = sprintf(
            '%s %s %s -l %s --psm 3 2>/dev/null',
            escapeshellcmd($tesseractPath),
            escapeshellarg($imagePath),
            escapeshellarg($outputBase),
            $languages
        );
        exec($cmd, $output, $returnCode);
        
        $outputFile = $outputBase . '.txt';
        if ($returnCode === 0 && file_exists($outputFile)) {
            $text = file_get_contents($outputFile);
            @unlink($outputFile);
            return $text;
        }
        
        return '';
    }
    
    /**
     * Check if text is valid UTF-8 (not garbage from wrong encoding)
     */
    private function isValidUtf8Text(string $text): bool
    {
        // Check if text is valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }
        
        // Check for excessive non-printable or replacement characters
        $totalChars = mb_strlen($text);
        if ($totalChars < 10) {
            return false;
        }
        
        // Count readable characters (letters, numbers, common punctuation, spaces)
        $readableCount = preg_match_all('/[\p{L}\p{N}\s\.,;:!?\-\'\"()]/u', $text);
        $readableRatio = $readableCount / $totalChars;
        
        // If less than 50% readable characters, consider it garbage
        return $readableRatio >= 0.5;
    }
    
    /**
     * Extract text from PDF streams (last resort fallback)
     */
    private function extractPdfStreams(string $filepath): string
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }
        
        // Extract text between stream markers (basic PDF text extraction)
        $text = '';
        if (preg_match_all('/stream\s*(.+?)\s*endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Try to decompress if FlateDecode
                $decompressed = @gzuncompress($stream);
                if ($decompressed !== false) {
                    $stream = $decompressed;
                }
                // Extract text objects
                if (preg_match_all('/\((.*?)\)/s', $stream, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . "\n";
                }
            }
        }
        
        return $this->cleanText($text);
    }
    
    /**
     * Extract text from plain text or markdown files
     */
    private function extractFromText(string $filepath): string
    {
        $content = file_get_contents($filepath);
        return $content !== false ? $this->cleanText($content) : '';
    }
    
    /**
     * Extract text from DOCX (Office Open XML)
     */
    private function extractFromDocx(string $filepath): string
    {
        $text = '';
        
        $zip = new \ZipArchive();
        if ($zip->open($filepath) === true) {
            // Read the main document content
            $content = $zip->getFromName('word/document.xml');
            $zip->close();
            
            if ($content !== false) {
                // Remove XML tags and decode entities
                $text = strip_tags(str_replace('<', ' <', $content));
                $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        
        return $this->cleanText($text);
    }
    
    /**
     * Extract text from DOC (older Word format)
     */
    private function extractFromDoc(string $filepath): string
    {
        // Try antiword first
        $antiword = trim(shell_exec('which antiword 2>/dev/null') ?? '');
        if ($antiword && is_executable($antiword)) {
            $output = shell_exec(escapeshellcmd($antiword) . ' ' . escapeshellarg($filepath) . ' 2>/dev/null');
            if (!empty($output)) {
                return $this->cleanText($output);
            }
        }
        
        // Try catdoc
        $catdoc = trim(shell_exec('which catdoc 2>/dev/null') ?? '');
        if ($catdoc && is_executable($catdoc)) {
            $output = shell_exec(escapeshellcmd($catdoc) . ' ' . escapeshellarg($filepath) . ' 2>/dev/null');
            if (!empty($output)) {
                return $this->cleanText($output);
            }
        }
        
        // Basic fallback - extract readable strings
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }
        
        // Extract ASCII text sequences
        $text = '';
        if (preg_match_all('/[\x20-\x7E]{4,}/', $content, $matches)) {
            $text = implode(' ', $matches[0]);
        }
        
        return $this->cleanText($text);
    }
    
    /**
     * Extract text from RTF
     */
    private function extractFromRtf(string $filepath): string
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }
        
        // Remove RTF control words and extract text
        $text = preg_replace('/\{\\\\[^{}]+\}/', '', $content);
        $text = preg_replace('/\\\\[a-z]+\d*\s?/', ' ', $text);
        $text = preg_replace('/[{}]/', '', $text);
        
        return $this->cleanText($text);
    }
    
    /**
     * Extract text from HTML
     */
    private function extractFromHtml(string $filepath): string
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }
        
        // Remove script and style tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
        
        // Convert to text
        $text = strip_tags(str_replace(['<', '>'], [' <', '> '], $content));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $this->cleanText($text);
    }
    
    /**
     * Clean extracted text - sanitize UTF-8 and normalize whitespace
     */
    private function cleanText(string $text): string
    {
        // First, ensure valid UTF-8 encoding
        // Remove invalid UTF-8 sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Use iconv to strip invalid characters (//IGNORE discards invalid sequences)
        $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;
        
        // Remove any remaining non-printable characters that aren't whitespace
        // This regex matches valid UTF-8 and removes invalid byte sequences
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        // Remove control characters in extended ASCII range (0x80-0x9F are control chars in Latin-1)
        $text = preg_replace('/[\x{0080}-\x{009F}]/u', '', $text);
        
        // Normalize whitespace
        $text = preg_replace('/[\r\n]+/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n +/', "\n", $text);
        $text = preg_replace('/ +\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Final validation - ensure the result is valid JSON-encodable UTF-8
        // If json_encode fails, strip problematic characters more aggressively
        if (json_encode($text) === false) {
            // More aggressive cleanup - keep only basic printable ASCII + common Unicode
            $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x09\x{00A0}-\x{FFFF}]/u', '', $text);
            
            // If still failing, fall back to ASCII only
            if (json_encode($text) === false) {
                $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/', '', $text);
            }
        }
        
        return trim($text);
    }
    
    /**
     * Get all documents for a user
     */
    public function getUserDocuments(int $userId): array
    {
        try {
            return $this->db->select('user_documents', [
                'id',
                'filename',
                'doc_type',
                'file_size',
                'text_length',
                'created_at',
            ], [
                'user_id' => $userId,
                'ORDER' => ['created_at' => 'DESC'],
            ]) ?: [];
        } catch (\Throwable $e) {
            error_log("[DocumentRagService] Error fetching documents: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a specific document's extracted text
     * 
     * @param int $userId User ID
     * @param int $documentId Document ID
     * @param int $maxLength Maximum text length to return (default 30KB for model context limits)
     * @return string|null Sanitized document text or null
     */
    public function getDocumentText(int $userId, int $documentId, int $maxLength = 30000): ?string
    {
        try {
            $doc = $this->db->get('user_documents', 'extracted_text', [
                'id' => $documentId,
                'user_id' => $userId,
            ]);
            
            if (!$doc) {
                return null;
            }
            
            // Sanitize for JSON encoding
            $text = $this->sanitizeForJson($doc);
            
            // Truncate if too long for model context
            if (strlen($text) > $maxLength) {
                $text = substr($text, 0, $maxLength) . "\n\n[...Document truncated to fit model context window. Showing first " . round($maxLength/1000) . "KB of " . round(strlen($doc)/1000) . "KB total]";
            }
            
            return $text;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Get document context for RAG (all or specific documents)
     * 
     * @param int $userId User ID
     * @param array|null $documentIds Specific document IDs, or null for all
     * @param int $maxTotalLength Max total context length
     * @return string Combined document context for system prompt
     */
    public function getDocumentContext(int $userId, ?array $documentIds = null, int $maxTotalLength = 50000): string
    {
        try {
            $where = ['user_id' => $userId];
            if ($documentIds !== null) {
                $where['id'] = $documentIds;
            }
            $where['ORDER'] = ['created_at' => 'DESC'];
            
            $docs = $this->db->select('user_documents', [
                'id',
                'filename',
                'doc_type',
                'extracted_text',
            ], $where) ?: [];
            
            if (empty($docs)) {
                return '';
            }
            
            $context = "## User's Uploaded Documents (RAG Context)\n";
            $context .= "The user has uploaded the following documents. Use this information to answer their questions.\n\n";
            
            $totalLength = strlen($context);
            
            foreach ($docs as $doc) {
                // Sanitize the extracted text to ensure valid UTF-8 for JSON encoding
                $extractedText = $this->sanitizeForJson($doc['extracted_text']);
                
                $docHeader = "### Document: {$doc['filename']} ({$doc['doc_type']})\n";
                $docText = $extractedText . "\n\n---\n\n";
                
                $addLength = strlen($docHeader) + strlen($docText);
                
                if ($totalLength + $addLength > $maxTotalLength) {
                    // Truncate this document to fit
                    $available = $maxTotalLength - $totalLength - strlen($docHeader) - 50;
                    if ($available > 500) {
                        $context .= $docHeader;
                        $context .= substr($extractedText, 0, $available) . "\n[...truncated]\n\n---\n\n";
                    }
                    break;
                }
                
                $context .= $docHeader . $docText;
                $totalLength += $addLength;
            }
            
            return $context;
            
        } catch (\Throwable $e) {
            error_log("[DocumentRagService] Error getting context: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Sanitize text to ensure it's valid for JSON encoding
     */
    private function sanitizeForJson(string $text): string
    {
        // Ensure valid UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/[\x{0080}-\x{009F}]/u', '', $text);
        
        // Validate it's JSON-encodable
        if (json_encode($text) === false) {
            // Aggressive cleanup
            $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x09\x{00A0}-\x{FFFF}]/u', '', $text);
            if (json_encode($text) === false) {
                $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/', '', $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Delete a document
     */
    public function deleteDocument(int $userId, int $documentId): bool
    {
        try {
            $doc = $this->db->get('user_documents', ['file_path'], [
                'id' => $documentId,
                'user_id' => $userId,
            ]);
            
            if (!$doc) {
                return false;
            }
            
            // Delete file
            if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
                @unlink($doc['file_path']);
            }
            
            // Delete from database
            $this->db->delete('user_documents', [
                'id' => $documentId,
                'user_id' => $userId,
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            error_log("[DocumentRagService] Error deleting document: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if table exists, create if not
     */
    public static function ensureTable($db): void
    {
        try {
            $pdo = $db->pdo ?? $db->getPdo();
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    stored_filename VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    mime_type VARCHAR(100) NOT NULL,
                    doc_type VARCHAR(20) NOT NULL,
                    file_size INT NOT NULL,
                    extracted_text LONGTEXT,
                    text_length INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
        } catch (\Throwable $e) {
            error_log("[DocumentRagService] Table creation error: " . $e->getMessage());
        }
    }
}
