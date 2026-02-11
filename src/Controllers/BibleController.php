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
            } catch (\Throwable $_) {
                // ignore and return empty
                $results = [];
            }
        }

        echo json_encode($results);
        exit;
    }

    // Check presence of legacy `Bible_Kjv` table and return SQL error JSON if missing
    public function checkTable(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (!$this->db) throw new \Exception('Database connection not available');
            // Try a simple select to reproduce SQLSTATE table-not-found when absent
            $this->db->query('SELECT 1 FROM Bible_Kjv LIMIT 1')->fetchAll();
            echo json_encode(['success' => true, 'message' => 'Table exists']);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}
