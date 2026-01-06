<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class PostsController extends \Core\Controller
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
        $posts = [];
        try { $posts = $this->db->select('posts', ['id','title','status','created_at'], ['ORDER' => ['created_at' => 'DESC']]); } catch (\Throwable $e) {}
        $this->view('admin/posts/index', ['title' => 'Posts', 'posts' => $posts]);
    }

    public function create()
    {
        $this->view('admin/posts/new', ['title' => 'Create Post', 'csrf_token' => generateCsrfToken()]);
    }

    public function store($input = [])
    {
        if (empty($input) && !empty($_POST)) { $input = $_POST; }
        try {
            $insert = [
                'title' => $input['title'] ?? 'Untitled',
                'slug' => $input['slug'] ?? null,
                'content' => $input['content'] ?? '',
                'status' => $input['status'] ?? 'draft',
                'author_id' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('posts', $insert);
            header('Location: /admin/posts'); exit;
        } catch (\Throwable $e) {
            error_log('PostsController::store error: ' . $e->getMessage());
            $this->view('admin/posts/new', ['error' => $e->getMessage(), 'old' => $input]);
        }
    }
}
