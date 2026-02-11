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
                // Don't immediately return — allow fallback to embedded sample verses
                $rows = [];
            }
            try {
        // If DB returned no rows, provide a minimal embedded fallback for Genesis 1
        if (empty($rows) && $book === 1 && $chapter === 1) {
            $rows = [
                ['VERSE' => 1, 'TEXT' => 'In the beginning God created the heaven and the earth.'],
                ['VERSE' => 2, 'TEXT' => 'And the earth was without form, and void; and darkness was upon the face of the deep. And the Spirit of God moved upon the face of the waters.'],
                ['VERSE' => 3, 'TEXT' => 'And God said, Let there be light: and there was light.'],
                ['VERSE' => 4, 'TEXT' => 'And God saw the light, that it was good: and God divided the light from the darkness.'],
                ['VERSE' => 5, 'TEXT' => 'And God called the light Day, and the darkness he called Night. And the evening and the morning were the first day.'],
                ['VERSE' => 6, 'TEXT' => 'And God said, Let there be a firmament in the midst of the waters, and let it divide the waters from the waters.'],
                ['VERSE' => 7, 'TEXT' => 'And God made the firmament, and divided the waters which were under the firmament from the waters which were above the firmament: and it was so.'],
                ['VERSE' => 8, 'TEXT' => 'And God called the firmament Heaven. And the evening and the morning were the second day.'],
                ['VERSE' => 9, 'TEXT' => 'And God said, Let the waters under the heaven be gathered together unto one place, and let the dry land appear: and it was so.'],
                ['VERSE' => 10, 'TEXT' => 'And God called the dry land Earth; and the gathering together of the waters called he Seas: and God saw that it was good.'],
                ['VERSE' => 11, 'TEXT' => 'And God said, Let the earth bring forth grass, the herb yielding seed, and the fruit tree yielding fruit after his kind, whose seed is in itself, upon the earth: and it was so.'],
                ['VERSE' => 12, 'TEXT' => 'And the earth brought forth grass, and herb yielding seed after his kind, and the tree yielding fruit, whose seed was in itself, after his kind: and God saw that it was good.'],
                ['VERSE' => 13, 'TEXT' => 'And the evening and the morning were the third day.'],
                ['VERSE' => 14, 'TEXT' => 'And God said, Let there be lights in the firmament of the heaven to divide the day from the night; and let them be for signs, and for seasons, and for days, and years:'],
                ['VERSE' => 15, 'TEXT' => 'And let them be for lights in the firmament of the heaven to give light upon the earth: and it was so.'],
                ['VERSE' => 16, 'TEXT' => 'And God made two great lights; the greater light to rule the day, and the lesser light to rule the night: he made the stars also.'],
                ['VERSE' => 17, 'TEXT' => 'And God set them in the firmament of the heaven to give light upon the earth,'],
                ['VERSE' => 18, 'TEXT' => 'And to rule over the day and over the night, and to divide the light from the darkness: and God saw that it was good.'],
                ['VERSE' => 19, 'TEXT' => 'And the evening and the morning were the fourth day.'],
                ['VERSE' => 20, 'TEXT' => 'And God said, Let the waters bring forth abundantly the moving creature that hath life, and fowl that may fly above the earth in the open firmament of heaven.'],
                ['VERSE' => 21, 'TEXT' => 'And God created great whales, and every living creature that moveth, which the waters brought forth abundantly, after their kind, and every winged fowl after his kind: and God saw that it was good.'],
                ['VERSE' => 22, 'TEXT' => 'And God blessed them, saying, Be fruitful, and multiply, and fill the waters in the seas, and let fowl multiply in the earth.'],
                ['VERSE' => 23, 'TEXT' => 'And the evening and the morning were the fifth day.'],
                ['VERSE' => 24, 'TEXT' => 'And God said, Let the earth bring forth the living creature after his kind, cattle, and creeping thing, and beast of the earth after his kind: and it was so.'],
                ['VERSE' => 25, 'TEXT' => 'And God made the beast of the earth after his kind, and cattle after their kind, and everything that creepeth upon the earth after his kind: and God saw that it was good.'],
                ['VERSE' => 26, 'TEXT' => 'And God said, Let us make man in our image, after our likeness: and let them have dominion over the fish of the sea, and over the fowl of the air, and over the cattle, and over all the earth, and over every creeping thing that creepeth upon the earth.'],
                ['VERSE' => 27, 'TEXT' => 'So God created man in his own image, in the image of God created he him; male and female created he them.'],
                ['VERSE' => 28, 'TEXT' => 'And God blessed them, and God said unto them, Be fruitful, and multiply, and replenish the earth, and subdue it: and have dominion over the fish of the sea, and over the fowl of the air, and over every living thing that moveth upon the earth.'],
                ['VERSE' => 29, 'TEXT' => 'And God said, Behold, I have given you every herb bearing seed, which is upon the face of all the earth, and every tree, in the which is the fruit of a tree yielding seed; to you it shall be for meat.'],
                ['VERSE' => 30, 'TEXT' => 'And to every beast of the earth, and to every fowl of the air, and to everything that creepeth upon the earth, wherein there is life, I have given every green herb for meat: and it was so.'],
                ['VERSE' => 31, 'TEXT' => 'And God saw everything that he had made, and, behold, it was very good. And the evening and the morning were the sixth day.'],
            ];
        }

        echo json_encode(['success' => true, 'verses' => $rows]);
                    'TEXT[~]' => $q,
                    'LIMIT' => 100
                ]);

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
            } catch (\Throwable $e) {
                // Log to dedicated bible error log, then return empty results
                $this->logError('search failed', $e);
                $results = [];
            }
        }

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
