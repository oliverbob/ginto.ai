<?php
namespace Ginto\Controllers;

class ForbiddenController extends \Core\Controller
{
    public function forbid()
    {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}
