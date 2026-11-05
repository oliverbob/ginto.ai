<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;

class AdminKycController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        parent::__construct();
        $this->db = $db ?? Database::getInstance();
    }

    private function ensureAdmin()
    {
        if (!defined('IS_ADMIN') || !IS_ADMIN) {
            header('Location: /login'); exit;
        }
    }

    public function index()
    {
        $this->ensureAdmin();
        $kycs = $this->db->select('kyc_profiles', ['id','user_id','status','submitted_at','reviewed_at','reviewer_id','documents','review_notes'], ['ORDER' => ['submitted_at' => 'DESC']]);
        // enrich with user data
        foreach ($kycs as &$k) {
            $u = $this->db->get('users', ['id','fullname','email'], ['id' => $k['user_id']]);
            $k['_user'] = $u ?: null;
        }
        unset($k);
        $csrf = generateCsrfToken();
        return $this->view('admin/kyc_list', ['kycs' => $kycs, 'csrf_token' => $csrf]);
    }

    public function review($id = null)
    {
        $this->ensureAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $id = (int)($id ?? ($_POST['id'] ?? 0));
        $status = $_POST['status'] ?? 'review';
        $notes = $_POST['review_notes'] ?? null;

        $this->db->update('kyc_profiles', [
            'status' => $status,
            'review_notes' => $notes,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewer_id' => $_SESSION['user_id'] ?? null
        ], ['id' => $id]);

        header('Location: /admin/kyc'); exit;
    }
}
