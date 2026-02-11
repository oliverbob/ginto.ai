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
        if (file_exists($bookFile)) {
            // include in local scope so it defines $Book
            include $bookFile;
        }

        // restore GET
        $_GET = $backupGet;

        // Ensure $Book variable exists
        $bookData = $Book ?? [];
        View::view('bible/index', ['title' => 'Bible', 'Book' => $bookData]);
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
        if ($this->db) {
            try {
                $sql = "SELECT BOOK, CHAPTER, VERSE, TEXT FROM fgbibledb_kjv WHERE TEXT LIKE :t LIMIT 100";
                $stmt = $this->db->query($sql, [':t' => '%' . $q . '%']);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $bookName = ($GLOBALS['Book']['All'][$r['BOOK']] ?? $r['BOOK']);
                    $results[] = [
                        'verse' => '[' . $bookName . ' ' . $r['CHAPTER'] . ':' . $r['VERSE'] . ']: ' . $r['TEXT'],
                        'passage' => $r['CHAPTER'] . ':' . $r['VERSE'] . ']: ' . $r['TEXT']
                    ];
                }
                // Log number of matches
                $this->logEvent(sprintf("/bible search results=%d q=%s session=%s user_id=%s",
                    count($rows),
                    str_replace("\n", ' ', substr($q,0,200)),
                    session_id() ?: '-',
                    $_SESSION['user_id'] ?? ($_SESSION['public_id'] ?? '-')
                ));
            } catch (\Throwable $_) {
                // ignore and return empty
                $results = [];
            }
        }

        echo json_encode($results);
        exit;
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

}
