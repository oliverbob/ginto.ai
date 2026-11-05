<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

class BibleController
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            try {
                $db = \Ginto\Core\Database::getInstance();
            } catch (\Throwable $_) {
                $db = null;
            }
        }
        $this->db = $db;

        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    }

    // Render the Bible index view, providing $Book data to the view
    public function index(): void
    {
        // Log access to /bible
        $this->logEvent(sprintf("/bible index accessed by session=%s user_id=%s ip=%s",
            session_id() ?: '-',
            $_SESSION['user_id'] ?? ($_SESSION['public_id'] ?? '-'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ));
        // Protect view include from accidentally handling AJAX verse requests
        $backupGet = $_GET;
        unset($_GET['verse'], $_GET['q']);

        // Include the data portion of the legacy view to populate $Book
        $bookFile = __DIR__ . '/../Views/bible/verse.php';
        try {
            // Ensure the legacy view knows it's being included from the app
            if (!defined('PATHSPAGE')) {
                define('PATHSPAGE', true);
            }
            if (file_exists($bookFile)) {
                // Make the controller's DB available to the included legacy view
                $db = $this->db;
                $GLOBALS['db'] = $this->db;
                // include in local scope so it defines $Book
                include $bookFile;
            }

            // Ensure $Book variable exists
            $bookData = $Book ?? [];
            $this->logEvent('/bible about to render view');
            View::view('bible/index', ['title' => 'Bible', 'Book' => $bookData, 'db' => $this->db]);
            $this->logEvent('/bible render completed');
        } catch (\Throwable $e) {
            // Log to a dedicated bible error log then rethrow to preserve existing error handling
            $this->logError('index render failed', $e);
            throw $e;
        } finally {
            // restore GET
            $_GET = $backupGet;
        }
    }

    // JSON search endpoint used by AJAX
    public function search(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? $_GET['verse'] ?? ''));
        $this->logEvent(sprintf("/bible search q=%s session=%s user_id=%s ip=%s",
            $q === '' ? '<empty>' : str_replace("\n", ' ', substr($q,0,200)),
            session_id() ?: '-',
            $_SESSION['user_id'] ?? ($_SESSION['public_id'] ?? '-'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ));

        if ($q === '') { echo json_encode([]); exit; }

        $results = [];

        // Ensure Book mapping is available for friendly book names
        if (empty($GLOBALS['Book'])) {
            $bookFile = __DIR__ . '/../Views/bible/verse.php';
            if (file_exists($bookFile)) {
                if (!defined('PATHSPAGE')) define('PATHSPAGE', true);
                $db = $this->db;
                $GLOBALS['db'] = $this->db;
                include_once $bookFile;
            }
        }

        $rows = [];
        try {
            if ($this->db) {
                // Parameterized search to avoid SQL injection
                $sql = "SELECT BOOK, CHAPTER, VERSE, TEXT FROM fgbibledb_kjv WHERE TEXT LIKE :t LIMIT 100";
                $params = [':t' => '%' . $q . '%'];
                $stmt = null;
                // Support PDO-style or Medoo-like wrappers
                if (method_exists($this->db, 'query')) {
                    $stmt = $this->db->query($sql, $params);
                    if ($stmt) $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } elseif (method_exists($this->db, 'select')) {
                    // Medoo-like select fallback
                    $rows = $this->db->select('fgbibledb_kjv', ['BOOK','CHAPTER','VERSE','TEXT'], [
                        'TEXT[~]' => $q,
                        'LIMIT' => 100
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logError('search failed', $e);
            $rows = [];
        }

        // Fallback embedded sample if no rows found
        if (empty($rows)) {
            $rows = [
                ['BOOK' => 1, 'CHAPTER' => 1, 'VERSE' => 1, 'TEXT' => 'In the beginning God created the heaven and the earth.'],
                ['BOOK' => 1, 'CHAPTER' => 1, 'VERSE' => 2, 'TEXT' => 'And the earth was without form, and void; and darkness was upon the face of the deep. And the Spirit of God moved upon the face of the waters.'],
                ['BOOK' => 1, 'CHAPTER' => 1, 'VERSE' => 3, 'TEXT' => 'And God said, Let there be light: and there was light.']
            ];
        }

        foreach ($rows as $r) {
            $bookName = ($GLOBALS['Book']['All'][$r['BOOK']] ?? $r['BOOK']);
            $results[] = [
                'verse' => '[' . $bookName . ' ' . $r['CHAPTER'] . ':' . $r['VERSE'] . ']: ' . $r['TEXT'],
                'passage' => $r['CHAPTER'] . ':' . $r['VERSE'] . ']: ' . $r['TEXT']
            ];
        }

        $this->logEvent(sprintf("/bible search results=%d q=%s session=%s user_id=%s",
            count($rows),
            str_replace("\n", ' ', substr($q,0,200)),
            session_id() ?: '-',
            $_SESSION['user_id'] ?? ($_SESSION['public_id'] ?? '-')
        ));

        echo json_encode($results);
        exit;
    }

    // Return verses for a specific book and chapter as JSON
    public function verses(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $book = isset($_GET['book']) ? (int)$_GET['book'] : null;
        $chapter = isset($_GET['chapter']) ? (int)$_GET['chapter'] : null;
        if ($book === null || $chapter === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing book or chapter']);
            return;
        }

        $rows = [];
        if ($this->db) {
            try {
                $rows = $this->db->select('fgbibledb_kjv', ['VERSE', 'TEXT'], [
                    'BOOK' => $book,
                    'CHAPTER' => $chapter,
                    'ORDER' => ['VERSE ASC']
                ]);
            } catch (\Throwable $e) {
                $this->logError('verses failed', $e);
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'DB query failed']);
                return;
            }
        }

        echo json_encode(['success' => true, 'verses' => $rows]);
        return;
    }
    /**
     * Append a message to the ginto log file in storage/logs/ginto.log if available.
     */
    protected function logEvent(string $message): void
    {
        try {
            $logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : dirname(__DIR__, 3) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/ginto.log';
            $ts = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[$ts] " . $message . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $_) {
            // best-effort only
        }
    }

    /**
     * Write errors specific to bible features to a separate log file.
     */
    protected function logError(string $message, ?\Throwable $e = null): void
    {
        try {
            $logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : dirname(__DIR__, 3) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/bible.error.log';
            $ts = date('Y-m-d H:i:s');
            $entry = "[$ts] " . $message;
            if ($e) {
                $entry .= ' | exception: ' . get_class($e) . ' ' . $e->getMessage();
                $entry .= ' in ' . $e->getFile() . ':' . $e->getLine();
                $entry .= "\n" . $e->getTraceAsString();
            }
            $entry .= "\n";
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $_) {
            // best-effort only
        }
    }

}
