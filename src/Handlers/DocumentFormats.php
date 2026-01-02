<?php

declare(strict_types=1);

namespace App\Handlers;

/**
 * Document Formats Configuration
 * 
 * Contains document format definitions and markdown-to-HTML conversion.
 * Separated from SandboxMcp for better readability and maintainability.
 */
final class DocumentFormats
{
    /**
     * Get available document formats and their configurations
     */
    public static function getFormats(): array
    {
        return [
            'pdf' => [
                'name' => 'PDF Document',
                'extension' => '.pdf',
                'mime' => 'application/pdf',
                'description' => 'Portable Document Format - professional documents, printable',
                'converter' => 'weasyprint',
                'native' => false,
            ],
            'docx' => [
                'name' => 'Word Document',
                'extension' => '.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'description' => 'Microsoft Word format - editable in Word, Google Docs, LibreOffice',
                'converter' => 'pandoc',
                'native' => false,
            ],
            'html' => [
                'name' => 'HTML Document',
                'extension' => '.html',
                'mime' => 'text/html',
                'description' => 'Web page format - viewable in browser, supports rich formatting',
                'converter' => null,
                'native' => true,
            ],
            'md' => [
                'name' => 'Markdown',
                'extension' => '.md',
                'mime' => 'text/markdown',
                'description' => 'Plain text with formatting - best for technical docs, notes, README files',
                'converter' => null,
                'native' => true,
            ],
            'txt' => [
                'name' => 'Plain Text',
                'extension' => '.txt',
                'mime' => 'text/plain',
                'description' => 'Simple text file - maximum compatibility',
                'converter' => null,
                'native' => true,
            ],
            'odt' => [
                'name' => 'OpenDocument Text',
                'extension' => '.odt',
                'mime' => 'application/vnd.oasis.opendocument.text',
                'description' => 'LibreOffice/OpenOffice format - open standard',
                'converter' => 'pandoc',
                'native' => false,
            ],
            'rtf' => [
                'name' => 'Rich Text Format',
                'extension' => '.rtf',
                'mime' => 'application/rtf',
                'description' => 'Formatted text document - compatible with Word, LibreOffice',
                'converter' => 'pandoc',
                'native' => false,
            ],
        ];
    }

    /**
     * Get format configuration by key
     */
    public static function getFormat(string $format): ?array
    {
        $formats = self::getFormats();
        return $formats[strtolower($format)] ?? null;
    }

    /**
     * Get all format keys
     */
    public static function getFormatKeys(): array
    {
        return array_keys(self::getFormats());
    }

    /**
     * Get helpful hint for opening different document types
     */
    public static function getOpenHint(string $format): string
    {
        return match(strtolower($format)) {
            'pdf' => 'Opens in any PDF viewer, browser, or Adobe Acrobat',
            'docx' => 'Opens in Microsoft Word, Google Docs, LibreOffice Writer',
            'odt' => 'Opens in LibreOffice Writer, OpenOffice, Google Docs',
            'html' => 'Opens in web browser - viewable and printable',
            'rtf' => 'Opens in Microsoft Word, LibreOffice Writer, Google Docs',
            'md' => 'Opens in any text editor - can be converted to other formats',
            'txt' => 'Opens in any text editor',
            default => 'Download to view'
        };
    }

    /**
     * Convert markdown content to HTML with proper styling
     */
    public static function markdownToHtml(string $markdown, string $title = 'Document'): string
    {
        $html = $markdown;
        
        // Tables (must be before other processing)
        $html = preg_replace_callback(
            '/^\|(.+)\|\s*\n\|([\-:\| ]+)\|\s*\n((?:\|.+\|\s*\n?)+)/m',
            function($matches) {
                $headerRow = $matches[1];
                $bodyRows = trim($matches[3]);
                
                $headers = array_map('trim', explode('|', $headerRow));
                $headerHtml = '<tr>' . implode('', array_map(fn($h) => "<th>{$h}</th>", $headers)) . '</tr>';
                
                $rowsHtml = '';
                foreach (explode("\n", $bodyRows) as $row) {
                    $cells = array_map('trim', explode('|', trim($row, '|')));
                    $rowsHtml .= '<tr>' . implode('', array_map(fn($c) => "<td>{$c}</td>", $cells)) . '</tr>';
                }
                
                return "<table><thead>{$headerHtml}</thead><tbody>{$rowsHtml}</tbody></table>";
            },
            $html
        );
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);
        $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);
        
        // Code blocks
        $html = preg_replace('/```(\w+)?\n([\s\S]*?)```/', '<pre><code>$2</code></pre>', $html);
        $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
        
        // Links
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);
        
        // Lists
        $html = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);
        
        // Wrap consecutive <li> elements in <ul>
        $html = preg_replace('/(<li>.*?<\/li>\s*)+/s', '<ul>$0</ul>', $html);
        
        // Paragraphs (lines that don't start with HTML tags)
        $lines = explode("\n", $html);
        $processedLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && !preg_match('/^</', $trimmed)) {
                $processedLines[] = '<p>' . $trimmed . '</p>';
            } else {
                $processedLines[] = $line;
            }
        }
        $html = implode("\n", $processedLines);
        
        // Horizontal rules
        $html = preg_replace('/^---+$/m', '<hr>', $html);
        
        // Wrap in full HTML document using template
        return ProjectTemplates::getDocumentHtml($title, $html);
    }

    /**
     * Get format list for display (used by sandbox_list_document_formats)
     */
    public static function getFormatList(): array
    {
        $formats = self::getFormats();
        $list = [];
        
        foreach ($formats as $key => $format) {
            $list[] = [
                'format' => $key,
                'name' => $format['name'],
                'extension' => $format['extension'],
                'description' => $format['description']
            ];
        }
        
        return $list;
    }
}
