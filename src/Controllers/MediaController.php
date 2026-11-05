<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class MediaController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        if (!$user || !in_array($user['role_id'], [1, 2])) { http_response_code(403); echo '<h1>403 Forbidden</h1>'; exit; }
    }

    public function index()
    {
        $media = [];
        try { $media = $this->db->select('media', ['id','filename','mime','size','created_at'], ['ORDER' => ['created_at' => 'DESC']]); } catch (\Throwable $e) {}
        $this->view('admin/media/index', ['title' => 'Media', 'media' => $media]);
    }
}
