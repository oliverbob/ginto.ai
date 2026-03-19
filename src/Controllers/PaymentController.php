<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Helpers\TransactionHelper;

/**
 * Payment Controller
 * Handles payment registration routes (bank, gcash, crypto)
 */
class PaymentController
{
    protected $db;
    protected $countries;

    public function __construct($db = null, array $countries = [])
    {
        if ($db === null) {
            $db = Database::getInstance();
        }
        $this->db = $db;
        $this->countries = $countries;
    }

    /**
     * Restrict standalone test flows (e.g. /gintopay) to authenticated admins.
     */
    private function requireAdminForStandaloneGintoPay(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit;
        }

        if (!defined('IS_ADMIN') || !IS_ADMIN) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Admin access required.']);
            exit;
        }
    }

    /**
     * Common validation for payment registration
     */
    protected function validateAjaxRequest(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        
        return true;
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
            exit;
        }
        return true;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired(array $fields): bool
    {
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
                exit;
            }
        }
        return true;
    }

    /**
     * Validate file upload
     */
    protected function validateFileUpload(string $fieldName, string $errorMessage): array
    {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        }
        
        $file = $_FILES[$fieldName];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image (JPG, PNG, GIF, WebP) or PDF.']);
            exit;
        }
        
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
            exit;
        }
        
        return ['file' => $file, 'mimeType' => $mimeType];
    }

    /**
     * Check for existing user
     */
    protected function checkExistingUser(): void
    {
        if ($this->db->get('users', 'id', ['email' => $_POST['email']])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User with this email already exists.']);
            exit;
        }
        
        if ($this->db->get('users', 'id', ['username' => $_POST['username']])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username already taken.']);
            exit;
        }
        
        if ($this->db->get('users', 'id', ['phone' => $_POST['phone']])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
            exit;
        }
    }

    /**
     * Resolve referrer ID from sponsor_id or session
     */
    protected function resolveReferrerId(): int
    {
        $referrerId = 2; // Default sponsor
        $refSource = $_POST['sponsor_id'] ?? ($_SESSION['referral_code'] ?? null);
        
        if (!empty($refSource)) {
            if (is_numeric($refSource)) {
                $referrerId = (int)$refSource;
            } else {
                $resolvedId = $this->db->get('users', 'id', ['username' => $refSource]);
                if (!$resolvedId) {
                    $resolvedId = $this->db->get('users', 'id', ['public_id' => $refSource]);
                }
                if ($resolvedId) {
                    $referrerId = (int)$resolvedId;
                }
            }
        }
        
        return $referrerId;
    }

    /**
     * Get full name from POST data
     */
    protected function getFullName(): string
    {
        $first = $_POST['firstname'] ?? $_POST['firstName'] ?? '';
        $middle = $_POST['middlename'] ?? $_POST['middleName'] ?? '';
        $last = $_POST['lastname'] ?? $_POST['lastName'] ?? '';
        
        if ($first || $middle || $last) {
            return trim(implode(' ', array_filter([$first, $middle, $last])));
        }
        return $_POST['fullname'] ?? '';
    }

    /**
     * Get plan ID from package name
     */
    protected function getPlanId(): int
    {
        $packageName = strtolower($_POST['package'] ?? 'go');
        $planIdMap = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4];
        return $planIdMap[$packageName] ?? 2;
    }

    /**
     * Create user and payment record
     */
    protected function createUserAndPayment(string $paymentMethod, string $reference, string $filepath, string $filename, string $mimeType, int $fileSize, string $originalFilename): array
    {
        $referrerId = $this->resolveReferrerId();
        $fullname = $this->getFullName();
        $planId = $this->getPlanId();
        
        // Create user
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $publicId = substr(md5(uniqid(mt_rand(), true)), 0, 12);
        
        $this->db->insert('users', [
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'password_hash' => $passwordHash,
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'public_id' => $publicId,
            'payment_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $userId = $this->db->id();
        
        if (!$userId) {
            @unlink($filepath);
            error_log("Failed to create user account for $paymentMethod payment");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
            exit;
        }
        
        // Store registration metadata
        $paymentNotes = json_encode([
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize
        ]);
        
        // Insert payment record
        $transactionId = TransactionHelper::generateTransactionId($this->db);
        $auditData = TransactionHelper::captureAuditData();
        
        $paymentAmount = !empty($_POST['package_amount']) ? floatval($_POST['package_amount']) : 0;
        
        // Log payment record data before insert
        error_log("Creating subscription_payment: user_id={$userId}, plan_id={$planId}, amount={$paymentAmount}, " .
                  "method={$paymentMethod}, package=" . ($_POST['package'] ?? 'N/A'));
        
        $this->db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => null,
            'plan_id' => $planId,
            'type' => 'registration',
            'amount' => $paymentAmount,
            'currency' => $_POST['package_currency'] ?? 'PHP',
            'payment_method' => $paymentMethod,
            'payment_reference' => $reference,
            'status' => 'pending',
            'notes' => $paymentNotes,
            'receipt_filename' => $filename,
            'receipt_path' => $filepath,
            'transaction_id' => $transactionId
        ], $auditData));
        
        $paymentId = $this->db->id();
        
        if (!$paymentId) {
            $this->db->delete('users', ['id' => $userId]);
            @unlink($filepath);
            error_log("Failed to insert subscription_payment record for $paymentMethod - DB Error: " . json_encode($this->db->error()));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save registration. Please try again.']);
            exit;
        }
        
        error_log("Successfully created subscription_payment id={$paymentId} for user_id={$userId}");
        
        // Log in the user
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['email'] = $_POST['email'];
        $_SESSION['payment_status'] = 'pending';
        
        return ['userId' => $userId, 'paymentId' => $paymentId];
    }

    /**
     * Bank Transfer Payment Registration
     */
    public function bankPayments(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        $this->validateAjaxRequest();
        header('Content-Type: application/json');
        
        try {
            $this->validateCsrf();
            $this->validateRequired(['username', 'email', 'password', 'country', 'phone', 'bank_reference']);
            
            $fileData = $this->validateFileUpload('bank_receipt', 'Bank receipt upload is required.');
            $file = $fileData['file'];
            $mimeType = $fileData['mimeType'];
            
            $this->checkExistingUser();
            
            // Check for pending bank payment
            $pendingPayment = $this->db->get('subscription_payments', 'id', [
                'payment_method' => 'bank_transfer',
                'status' => 'pending',
                'notes[~]' => '%"email":"' . $_POST['email'] . '"%'
            ]);
            if ($pendingPayment) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'A pending registration with this email already exists.']);
                exit;
            }
            
            // Setup upload directory
            $projectRoot = dirname(dirname(__DIR__));
            $uploadDir = dirname($projectRoot) . '/storage/payments/bank-transfer/receipts/';
            
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                error_log('Failed to create upload directory: ' . $uploadDir);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
                exit;
            }
            
            // Save file
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                error_log('Failed to move uploaded file to: ' . $filepath);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save receipt. Please try again.']);
                exit;
            }
            
            // Create user and payment
            $result = $this->createUserAndPayment('bank_transfer', $_POST['bank_reference'], $filepath, $filename, $mimeType, $file['size'], $file['name']);
            
            error_log("Bank payment registration: User ID={$result['userId']}, Payment ID={$result['paymentId']}, Email={$_POST['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created! Your premium status will be activated once we verify your payment.',
                'payment_id' => $result['paymentId'],
                'user_id' => $result['userId'],
                'redirect' => '/chat'
            ]);
            exit;
            
        } catch (\Exception $e) {
            error_log('Bank payment registration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
            exit;
        }
    }

    /**
     * GCash Payment Registration
     */
    public function gcashPayments(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        $this->validateAjaxRequest();
        header('Content-Type: application/json');
        
        try {
            // Log incoming request data for debugging
            error_log("GCash payment request: package=" . ($_POST['package'] ?? 'N/A') . 
                      ", amount=" . ($_POST['package_amount'] ?? 'N/A') . 
                      ", email=" . ($_POST['email'] ?? 'N/A'));
            
            $this->validateCsrf();
            $this->validateRequired(['username', 'email', 'password', 'country', 'phone', 'gcash_reference']);
            
            $fileData = $this->validateFileUpload('gcash_receipt', 'GCash receipt upload is required.');
            $file = $fileData['file'];
            $mimeType = $fileData['mimeType'];
            
            $this->checkExistingUser();
            
            // Check for pending GCash payment
            $pendingPayment = $this->db->get('subscription_payments', 'id', [
                'payment_method' => 'gcash',
                'status' => 'pending',
                'notes[~]' => '%"email":"' . $_POST['email'] . '"%'
            ]);
            if ($pendingPayment) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'A pending registration with this email already exists.']);
                exit;
            }
            
            // Setup upload directory
            $projectRoot = dirname(dirname(__DIR__));
            $uploadDir = dirname($projectRoot) . '/storage/payments/gcash/receipts/';
            
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                error_log('Failed to create upload directory: ' . $uploadDir);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
                exit;
            }
            
            // Save file
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                error_log('Failed to move uploaded file to: ' . $filepath);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save receipt. Please try again.']);
                exit;
            }
            
            // Create user and payment
            $result = $this->createUserAndPayment('gcash', $_POST['gcash_reference'], $filepath, $filename, $mimeType, $file['size'], $file['name']);
            
            error_log("GCash payment registration: User ID={$result['userId']}, Payment ID={$result['paymentId']}, Email={$_POST['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created! Your premium status will be activated once we verify your GCash payment.',
                'payment_id' => $result['paymentId'],
                'user_id' => $result['userId'],
                'redirect' => '/chat'
            ]);
            exit;
            
        } catch (\Exception $e) {
            error_log('GCash payment registration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
            exit;
        }
    }

    /**
     * Crypto Payment Info API - serves USDT BEP20 wallet info
     */
    public function cryptoInfo(): void
    {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Direct access not allowed']);
            exit;
        }
        
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        
        $cryptoConfig = require dirname(__DIR__) . '/Views/payments/address.php';
        $walletAddress = $cryptoConfig['usdt_bep20']['address'] ?? null;
        
        if (!$walletAddress) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Crypto wallet not configured']);
            exit;
        }
        
        $qrPath = dirname(__DIR__) . '/Views/payments/usdt_qr.png';
        if (!file_exists($qrPath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment QR not configured']);
            exit;
        }
        
        $imageData = file_get_contents($qrPath);
        $image = imagecreatefromstring($imageData);
        
        if ($image) {
            $width = imagesx($image);
            $height = imagesy($image);
            $timestamp = time();
            $seed = $timestamp % 1000;
            
            for ($i = 0; $i < 3; $i++) {
                $x = ($seed + $i * 17) % max(1, $width);
                $y = ($seed + $i * 23) % max(1, $height);
                $color = imagecolorat($image, $x, $y);
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                $newColor = imagecolorallocate($image, $r, $g, min(255, $b + 1));
                imagesetpixel($image, $x, $y, $newColor);
            }
            
            ob_start();
            imagepng($image);
            $modifiedImageData = ob_get_clean();
            imagedestroy($image);
            
            $base64Image = base64_encode($modifiedImageData);
        } else {
            $base64Image = base64_encode($imageData);
        }
        
        echo json_encode([
            'success' => true,
            'network' => 'BNB Smart Chain (BEP20)',
            'token' => 'USDT',
            'address' => $walletAddress,
            'qr_image' => 'data:image/png;base64,' . $base64Image,
            'warning' => 'Only send USDT via BNB Smart Chain (BEP20). Other networks will result in permanent loss.',
            'verification_api' => 'https://bscscan.com/address/' . $walletAddress
        ]);
        exit;
    }

    /**
     * Serve receipt images securely
     */
    public function receiptImage($filename): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            exit('Unauthorized');
        }
        
        $userId = $_SESSION['user_id'];
        
        // Check if user is admin
        $isAdmin = false;
        $user = $this->db->get('users', ['role_id'], ['id' => $userId]);
        if ($user && in_array($user['role_id'], [1, 2])) {
            $isAdmin = true;
        }
        
        // Sanitize filename
        $filename = basename($filename);
        
        // Check if user owns this receipt
        if ($isAdmin) {
            $payment = $this->db->get('subscription_payments', ['id', 'payment_method'], ['receipt_filename' => $filename]);
        } else {
            $payment = $this->db->get('subscription_payments', ['id', 'payment_method'], [
                'user_id' => $userId,
                'receipt_filename' => $filename
            ]);
        }
        
        if (!$payment) {
            http_response_code(404);
            exit('Not found');
        }
        
        // Get receipt path
        $projectRoot = dirname(dirname(__DIR__));
        $receiptDirs = [
            'bank_transfer' => dirname($projectRoot) . '/storage/payments/bank-transfer/receipts/',
            'gcash' => dirname($projectRoot) . '/storage/payments/gcash/receipts/',
            'crypto' => dirname($projectRoot) . '/storage/payments/crypto/receipts/',
        ];
        
        $method = $payment['payment_method'];
        $dir = $receiptDirs[$method] ?? null;
        
        if (!$dir) {
            http_response_code(404);
            exit('Receipt directory not found');
        }
        
        $receiptPath = $dir . $filename;
        
        if (!file_exists($receiptPath)) {
            http_response_code(404);
            exit('Receipt file not found');
        }
        
        // Determine content type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ];
        
        $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($receiptPath));
        header('Cache-Control: private, max-age=3600');
        readfile($receiptPath);
        exit;
    }

    /**
     * Crypto Payment Registration (USDT BEP20)
     */
    public function cryptoPayments(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        $this->validateAjaxRequest();
        header('Content-Type: application/json');
        
        try {
            $this->validateCsrf();
            $this->validateRequired(['username', 'email', 'password', 'country', 'phone', 'crypto_txhash']);
            
            // Validate transaction hash format
            $txHash = trim($_POST['crypto_txhash']);
            if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid transaction hash format.']);
                exit;
            }
            
            // Handle optional file upload
            $receiptFilename = null;
            $receiptPath = null;
            $mimeType = null;
            $file = null;
            
            if (isset($_FILES['crypto_receipt']) && $_FILES['crypto_receipt']['error'] === UPLOAD_ERR_OK) {
                $fileData = $this->validateFileUpload('crypto_receipt', 'Crypto receipt upload failed.');
                $file = $fileData['file'];
                $mimeType = $fileData['mimeType'];
                
                $projectRoot = dirname(dirname(__DIR__));
                $uploadDir = dirname($projectRoot) . '/storage/payments/crypto/transfer/receipts/';
                
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    error_log('Failed to create crypto upload directory: ' . $uploadDir);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Server error.']);
                    exit;
                }
                
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $receiptFilename = bin2hex(random_bytes(16)) . '.' . $extension;
                $receiptPath = $uploadDir . $receiptFilename;
                
                if (!move_uploaded_file($file['tmp_name'], $receiptPath)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to save receipt.']);
                    exit;
                }
            }
            
            // Check for existing users
            if ($this->db->get('users', 'id', ['email' => $_POST['email']])) {
                if ($receiptPath) @unlink($receiptPath);
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'User with this email already exists.']);
                exit;
            }
            if ($this->db->get('users', 'id', ['username' => $_POST['username']])) {
                if ($receiptPath) @unlink($receiptPath);
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Username already taken.']);
                exit;
            }
            if ($this->db->get('users', 'id', ['phone' => $_POST['phone']])) {
                if ($receiptPath) @unlink($receiptPath);
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
                exit;
            }
            
            // Check for duplicate tx hash
            if ($this->db->get('subscription_payments', 'id', ['payment_method' => 'crypto_usdt_bep20', 'payment_reference' => $txHash])) {
                if ($receiptPath) @unlink($receiptPath);
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'This transaction has already been submitted.']);
                exit;
            }
            
            // Create user and payment
            $referrerId = $this->resolveReferrerId();
            $fullname = $this->getFullName();
            $planId = $this->getPlanId();
            
            $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $publicId = substr(md5(uniqid(mt_rand(), true)), 0, 12);
            
            $this->db->insert('users', [
                'email' => $_POST['email'],
                'username' => $_POST['username'],
                'password_hash' => $passwordHash,
                'fullname' => $fullname,
                'phone' => $_POST['phone'],
                'country' => $_POST['country'],
                'referrer_id' => $referrerId,
                'public_id' => $publicId,
                'payment_status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $userId = $this->db->id();
            
            if (!$userId) {
                if ($receiptPath) @unlink($receiptPath);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create account.']);
                exit;
            }
            
            $cryptoConfig = require dirname(__DIR__) . '/Views/payments/address.php';
            $walletAddress = $cryptoConfig['usdt_bep20']['address'] ?? '';
            
            $paymentNotes = json_encode([
                'email' => $_POST['email'],
                'username' => $_POST['username'],
                'fullname' => $fullname,
                'phone' => $_POST['phone'],
                'country' => $_POST['country'],
                'referrer_id' => $referrerId,
                'network' => 'BNB Smart Chain (BEP20)',
                'token' => 'USDT',
                'wallet_address' => $walletAddress,
                'bscscan_url' => 'https://bscscan.com/tx/' . $txHash
            ]);
            
            $transactionId = TransactionHelper::generateTransactionId($this->db);
            $auditData = TransactionHelper::captureAuditData();
            
            $this->db->insert('subscription_payments', array_merge([
                'user_id' => $userId,
                'subscription_id' => null,
                'plan_id' => $planId,
                'type' => 'registration',
                'amount' => !empty($_POST['package_amount']) ? floatval($_POST['package_amount']) : 0,
                'currency' => 'USDT',
                'payment_method' => 'crypto_usdt_bep20',
                'payment_reference' => $txHash,
                'status' => 'pending',
                'notes' => $paymentNotes,
                'receipt_filename' => $receiptFilename,
                'receipt_path' => $receiptPath,
                'transaction_id' => $transactionId
            ], $auditData));
            
            $paymentId = $this->db->id();
            
            if (!$paymentId) {
                $this->db->delete('users', ['id' => $userId]);
                if ($receiptPath) @unlink($receiptPath);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save registration.']);
                exit;
            }
            
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $_POST['username'];
            $_SESSION['email'] = $_POST['email'];
            $_SESSION['payment_status'] = 'pending';
            
            error_log("Crypto payment registration: User ID=$userId, Payment ID=$paymentId, TxHash=$txHash");
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created! Your premium status will be activated once we verify your USDT payment.',
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'verification_url' => 'https://bscscan.com/tx/' . $txHash,
                'redirect' => '/chat'
            ]);
            exit;
            
        } catch (\Exception $e) {
            error_log('Crypto payment registration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
            exit;
        }
    }

    /**
     * Get user's pending payment details
     */
    public function paymentDetails(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        $payment = $this->db->get('subscription_payments', [
            'id', 'transaction_id', 'plan_id', 'type', 'amount', 'currency',
            'payment_method', 'payment_reference', 'status', 'receipt_filename',
            'admin_review_requested', 'admin_review_requested_at', 'created_at',
            'ip_address', 'user_agent', 'device_info', 'geo_country', 'geo_city', 'session_id'
        ], [
            'user_id' => $userId,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => 1
        ]);
        
        if ($payment) {
            $totalPendingReviews = $this->db->count('subscription_payments', [
                'status' => 'pending',
                'admin_review_requested' => 1
            ]);
            
            $queuePosition = null;
            if ($payment['admin_review_requested']) {
                $queuePosition = $this->db->count('subscription_payments', [
                    'status' => 'pending',
                    'admin_review_requested' => 1,
                    'admin_review_requested_at[<=]' => $payment['admin_review_requested_at']
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'payment' => $payment,
                'pending_reviews_count' => $totalPendingReviews,
                'queue_position' => $queuePosition
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No payment record found']);
        }
        exit;
    }

    /**
     * Check/Sync Payment Status (for PayPal, checks API; for others, returns DB status)
     */
    public function checkStatus($paymentId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        
        $payment = $this->db->get('subscription_payments', [
            'id', 'user_id', 'payment_method', 'payment_reference', 'status', 'admin_review_requested'
        ], ['id' => $paymentId]);
        
        if (!$payment || $payment['user_id'] != $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
            exit;
        }
        
        $currentStatus = $payment['status'];
        $newStatus = $currentStatus;
        $message = '';
        $syncedFromPaypal = false;
        
        // For PayPal payments, check the API
        if (in_array($payment['payment_method'], ['paypal', 'credit_card']) && $currentStatus === 'pending') {
            $orderId = $payment['paypal_order_id'] ?? $payment['payment_reference'];
            
            if ($orderId) {
                try {
                    $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
                    $clientId = $paypalEnv === 'sandbox' 
                        ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX'))
                        : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID'));
                    $clientSecret = $paypalEnv === 'sandbox'
                        ? ($_ENV['PAYPAL_SECRET_SANDBOX'] ?? getenv('PAYPAL_SECRET_SANDBOX'))
                        : ($_ENV['PAYPAL_SECRET'] ?? getenv('PAYPAL_SECRET'));
                    
                    $baseUrl = $paypalEnv === 'sandbox' 
                        ? 'https://api-m.sandbox.paypal.com'
                        : 'https://api-m.paypal.com';
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $tokenResponse = curl_exec($ch);
                    $tokenData = json_decode($tokenResponse, true);
                    curl_close($ch);
                    
                    if (isset($tokenData['access_token'])) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders/' . $orderId);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $tokenData['access_token']
                        ]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        
                        $orderResponse = curl_exec($ch);
                        $order = json_decode($orderResponse, true);
                        curl_close($ch);
                        
                        if (isset($order['status'])) {
                            $paypalStatus = $order['status'];
                            $syncedFromPaypal = true;
                            
                            switch ($paypalStatus) {
                                case 'COMPLETED':
                                    $newStatus = 'completed';
                                    $message = 'PayPal payment has been completed!';
                                    break;
                                case 'APPROVED':
                                case 'PAYER_ACTION_REQUIRED':
                                    $newStatus = 'pending';
                                    $message = 'Payment requires additional action from PayPal.';
                                    break;
                                case 'VOIDED':
                                    $newStatus = 'failed';
                                    $message = 'Payment was voided.';
                                    break;
                                default:
                                    $message = 'PayPal status: ' . $paypalStatus;
                            }
                            
                            if ($newStatus !== $currentStatus) {
                                $this->db->update('subscription_payments', ['status' => $newStatus], ['id' => $paymentId]);
                                if ($newStatus === 'completed') {
                                    $this->db->update('users', ['payment_status' => 'completed'], ['id' => $userId]);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('PayPal status check error: ' . $e->getMessage());
                    $message = 'Unable to check PayPal status.';
                }
            }
        } else {
            switch ($currentStatus) {
                case 'completed': $message = 'Payment has been approved.'; break;
                case 'pending': $message = 'Payment is pending admin verification.'; break;
                case 'failed': $message = 'Payment was rejected.'; break;
                default: $message = 'Status: ' . $currentStatus;
            }
        }
        
        echo json_encode([
            'success' => true,
            'payment_id' => $paymentId,
            'previous_status' => $currentStatus,
            'current_status' => $newStatus,
            'new_status' => $newStatus,
            'status_changed' => $newStatus !== $currentStatus,
            'synced_from_paypal' => $syncedFromPaypal,
            'admin_review_requested' => (bool)($payment['admin_review_requested'] ?? false),
            'message' => $message
        ]);
        exit;
    }

    /**
     * Request Admin Review for Payment
     */
    public function requestReview($paymentId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        
        $payment = $this->db->get('subscription_payments', ['id', 'user_id', 'status', 'admin_review_requested'], ['id' => $paymentId]);
        
        if (!$payment || $payment['user_id'] != $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
            exit;
        }
        
        if ($payment['status'] === 'completed') {
            echo json_encode(['success' => false, 'message' => 'Payment is already approved']);
            exit;
        }
        
        if ($payment['admin_review_requested']) {
            echo json_encode(['success' => false, 'message' => 'Admin review already requested']);
            exit;
        }
        
        $this->db->update('subscription_payments', [
            'admin_review_requested' => 1,
            'admin_review_requested_at' => date('Y-m-d H:i:s')
        ], ['id' => $paymentId]);
        
        error_log("Admin review requested for payment ID: $paymentId by user ID: $userId");
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin review has been requested. You will be notified once reviewed.'
        ]);
        exit;
    }

    /**
     * Create PayPal order for registration
     * POST /api/register/paypal-order
     */
    public function paypalOrder(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Origin validation for CSRF protection
        $appUrl = $_ENV['APP_URL'] ?? 'https://ginto.app';
        $allowedOrigins = [$appUrl, rtrim($appUrl, '/'), 'http://localhost', 'http://localhost:8000', 'http://127.0.0.1:8000'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        
        $originAllowed = in_array($origin, $allowedOrigins) || $origin === '';
        $refererAllowed = $referer === $appHost || $referer === 'localhost' || $referer === '127.0.0.1' || empty($referer);
        
        if (!$originAllowed && !$refererAllowed) {
            error_log("CSRF blocked: origin=$origin, referer=$referer, allowed_host=$appHost");
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - invalid origin']);
            exit;
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $levelId = $input['level_id'] ?? null;
            $amount = $input['amount'] ?? null;
            $currency = $input['currency'] ?? 'PHP';
            
            if (!$levelId || !$amount) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing level_id or amount']);
                exit;
            }
            
            // Validate level exists
            $level = $this->db->get('tier_plans', '*', ['id' => $levelId]);
            if (!$level) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid membership level']);
                exit;
            }
            
            // Validate amount matches level price
            $expectedAmount = floatval($level['price']);
            if (abs(floatval($amount) - $expectedAmount) > 0.01) {
                http_response_code(400);
                echo json_encode(['error' => 'Amount mismatch']);
                exit;
            }
            
            // Get PayPal credentials based on environment
            $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';
            if ($paypalEnv === 'sandbox') {
                $clientId = $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? '';
                $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? '';
                $baseUrl = 'https://api-m.sandbox.paypal.com';
            } else {
                $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
                $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
                $baseUrl = 'https://api-m.paypal.com';
            }
            
            if (!$clientId || !$clientSecret) {
                throw new \Exception('PayPal credentials not configured');
            }
            
            // Get access token
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            
            $tokenResponse = curl_exec($ch);
            $tokenData = json_decode($tokenResponse, true);
            curl_close($ch);
            
            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Failed to get PayPal access token');
            }
            
            $accessToken = $tokenData['access_token'];
            
            // Create PayPal order
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'REG-' . $levelId . '-' . time(),
                        'description' => $level['name'] . ' Membership',
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', '')
                        ]
                    ]
                ],
                'application_context' => [
                    'brand_name' => 'Ginto',
                    'landing_page' => 'NO_PREFERENCE',
                    'user_action' => 'PAY_NOW',
                    'return_url' => ($_ENV['APP_URL'] ?? 'http://localhost') . '/register/paypal-success',
                    'cancel_url' => ($_ENV['APP_URL'] ?? 'http://localhost') . '/register'
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . uniqid('order-', true)
            ]);
            
            $orderResponse = curl_exec($ch);
            $order = json_decode($orderResponse, true);
            curl_close($ch);
            
            if (!isset($order['id'])) {
                error_log('PayPal order creation failed: ' . $orderResponse);
                throw new \Exception('Failed to create PayPal order');
            }
            
            error_log("PayPal order created: " . $order['id'] . " for level $levelId, amount $amount $currency");
            
            echo json_encode([
                'id' => $order['id'],
                'status' => $order['status']
            ]);
            
        } catch (\Throwable $e) {
            error_log('PayPal order creation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create order', 'details' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Capture PayPal payment for registration
     * POST /api/register/paypal-capture
     */
    public function paypalCapture(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Origin validation for CSRF protection
        $appUrl = $_ENV['APP_URL'] ?? 'https://ginto.app';
        $prodUrl = $_ENV['PRODUCTION_URL'] ?? 'https://ginto.ai';
        $allowedOrigins = [
            $appUrl, 
            rtrim($appUrl, '/'),
            $prodUrl,
            rtrim($prodUrl, '/'),
            'https://ginto.ai',
            'https://www.ginto.ai',
            'http://localhost', 
            'http://localhost:8000', 
            'http://127.0.0.1:8000'
        ];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $prodHost = parse_url($prodUrl, PHP_URL_HOST);
        
        $originAllowed = in_array($origin, $allowedOrigins) || $origin === '';
        $refererAllowed = $referer === $appHost || $referer === $prodHost || $referer === 'ginto.ai' || $referer === 'www.ginto.ai' || $referer === 'localhost' || $referer === '127.0.0.1' || empty($referer);
        
        if (!$originAllowed && !$refererAllowed) {
            error_log("CSRF blocked: origin=$origin, referer=$referer, allowed_host=$appHost");
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - invalid origin']);
            exit;
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['order_id'] ?? null;
            $levelId = $input['level_id'] ?? null;
            $registrationData = $input['registration_data'] ?? null;
            
            if (!$orderId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing order_id']);
                exit;
            }
            
            // Get PayPal credentials based on environment
            $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';
            if ($paypalEnv === 'sandbox') {
                $clientId = $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? '';
                $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? '';
                $baseUrl = 'https://api-m.sandbox.paypal.com';
            } else {
                $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
                $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
                $baseUrl = 'https://api-m.paypal.com';
            }
            
            if (!$clientId || !$clientSecret) {
                throw new \Exception('PayPal credentials not configured');
            }
            
            // Get access token
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            
            $tokenResponse = curl_exec($ch);
            $tokenData = json_decode($tokenResponse, true);
            curl_close($ch);
            
            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Failed to get PayPal access token');
            }
            
            $accessToken = $tokenData['access_token'];
            
            // Capture the order
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $captureResponse = curl_exec($ch);
            $capture = json_decode($captureResponse, true);
            curl_close($ch);
            
            // Handle different PayPal statuses
            $paypalStatus = $capture['status'] ?? 'UNKNOWN';
            $captureDetails = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
            $paymentId = $captureDetails['id'] ?? $orderId;
            $amount = $captureDetails['amount']['value'] ?? '0.00';
            $currency = $captureDetails['amount']['currency_code'] ?? 'PHP';
            
            // Map PayPal status to our internal status
            $internalStatus = 'pending';
            $statusMessage = '';
            
            switch ($paypalStatus) {
                case 'COMPLETED':
                    $internalStatus = 'completed';
                    $statusMessage = 'Payment completed successfully';
                    break;
                case 'PENDING':
                case 'APPROVED':
                    $internalStatus = 'pending';
                    $statusMessage = 'Payment is pending review by PayPal. This may take 24-48 hours.';
                    break;
                case 'VOIDED':
                case 'DECLINED':
                    $internalStatus = 'failed';
                    $statusMessage = 'Payment was declined or voided';
                    error_log('PayPal payment failed: ' . $captureResponse);
                    throw new \Exception('Payment was declined: ' . ($capture['message'] ?? $paypalStatus));
                default:
                    if (!isset($capture['status'])) {
                        error_log('PayPal capture failed - no status: ' . $captureResponse);
                        throw new \Exception('Payment capture failed: ' . ($capture['message'] ?? 'Unknown error'));
                    }
                    $internalStatus = 'pending';
                    $statusMessage = 'Payment status: ' . $paypalStatus;
            }
            
            error_log("PayPal payment captured: $paymentId for $amount $currency - Status: $paypalStatus -> $internalStatus");
            
            // Store payment in session for registration completion
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['paypal_payment'] = [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'currency' => $currency,
                'level_id' => $levelId,
                'captured_at' => date('Y-m-d H:i:s'),
                'status' => $internalStatus,
                'paypal_status' => $paypalStatus
            ];
            
            echo json_encode([
                'success' => true,
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $internalStatus,
                'paypal_status' => $paypalStatus,
                'message' => $statusMessage
            ]);
            
        } catch (\Throwable $e) {
            error_log('PayPal capture error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to capture payment', 'details' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Validate a promo code during registration
     * POST /register/promo-code
     */
    public function validatePromoCode(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['valid' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        // Get JSON input (middleware may have already parsed it)
        $input = $GLOBALS['_JSON_BODY'] ?? json_decode(file_get_contents('php://input'), true);
        
        // Validate CSRF token
        $csrfToken = $input['csrf_token'] ?? '';
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['valid' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $code = strtoupper(trim($input['code'] ?? ''));
        $packageName = $input['package_name'] ?? null;
        $packageAmount = floatval($input['package_amount'] ?? 0);
        
        if (empty($code)) {
            echo json_encode(['valid' => false, 'error' => 'Promo code is required']);
            exit;
        }
        
        // Look up promo code
        $promo = $this->db->get('promo_codes', '*', ['code' => $code]);
        
        if (!$promo) {
            echo json_encode(['valid' => false, 'error' => 'Invalid promo code']);
            exit;
        }
        
        // Check if active
        if (!$promo['is_active']) {
            echo json_encode(['valid' => false, 'error' => 'This promo code is no longer active']);
            exit;
        }
        
        // Check date validity - use Manila timezone
        $tz = new \DateTimeZone('Asia/Manila');
        $now = new \DateTime('now', $tz);
        if ($promo['valid_from']) {
            $validFrom = new \DateTime($promo['valid_from'], $tz);
            if ($now < $validFrom) {
                echo json_encode(['valid' => false, 'error' => 'This promo code is not yet valid']);
                exit;
            }
        }
        if ($promo['valid_until']) {
            $validUntil = new \DateTime($promo['valid_until'], $tz);
            if ($now > $validUntil) {
                echo json_encode(['valid' => false, 'error' => 'This promo code has expired']);
                exit;
            }
        }
        
        // Check usage limit
        if ($promo['max_uses'] !== null && $promo['used_count'] >= $promo['max_uses']) {
            echo json_encode(['valid' => false, 'error' => 'This promo code has reached its usage limit']);
            exit;
        }
        
        // Calculate discount
        $discount = 0;
        $discountType = $promo['discount_type'];
        $discountValue = floatval($promo['discount_value']);
        
        if ($discountType === 'percentage' && $discountValue > 0) {
            $discount = round($packageAmount * ($discountValue / 100), 2);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $discount = min($discountValue, $packageAmount);
        }
        // If discount_value is 0, it's a tracking-only code - still valid
        
        $finalAmount = max(0, $packageAmount - $discount);
        
        $response = [
            'valid' => true,
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discount,
            'original_amount' => $packageAmount,
            'final_amount' => $finalAmount,
            'valid_until' => $promo['valid_until'],
            'message' => $discount > 0 
                ? "Promo code applied! You save ₱" . number_format($discount, 2)
                : "Promo code verified!"
        ];
        
        echo json_encode($response);
        exit;
    }

    /**
     * PayMongo QRPH Initialization
     * Creates a payment intent + QRPH payment method, returns QR code image.
     */
    public function paymongoQrphInit(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
            exit;
        }

        // Input validation
        $amountRaw = $_POST['amount'] ?? '';
        $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $name      = strip_tags(trim($_POST['name'] ?? 'Ginto User'));
        $phone     = preg_replace('/[^0-9+\-\s]/', '', $_POST['phone'] ?? '');
        $tier      = strip_tags(trim($_POST['tier'] ?? 'Membership'));

        if (!is_numeric($amountRaw) || (int)$amountRaw <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
            exit;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid email is required.']);
            exit;
        }

        $amountPhp = (int)$amountRaw;

        // Billing duration: '1m' (1 month) or '12m' (12 months / annual one-time)
        $duration = in_array($_POST['duration'] ?? '', ['1m', '12m']) ? $_POST['duration'] : '1m';

        if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'PayMongo is not configured. Please contact support.']);
            exit;
        }

        try {
            $handler = new \Ginto\Handlers\PayMongoHandler();
            $description = 'Ginto ' . $tier . ' Membership';
            $result = $handler->initQrph($amountPhp, $email, $name, $phone, $description);

            if (!$result['success']) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => $result['message']]);
                exit;
            }

            // Store PI ID in session for later verification (prevents forgery)
            $_SESSION['paymongo_pi_id']       = $result['pi_id'];
            $_SESSION['paymongo_pi_amount']   = $amountPhp;
            $_SESSION['paymongo_pi_tier']     = $tier;
            $_SESSION['paymongo_pi_email']    = $email;
            $_SESSION['paymongo_pi_duration'] = $duration;

            echo json_encode([
                'success'   => true,
                'pi_id'     => $result['pi_id'],
                'qr_image'  => $result['qr_image'],
                'qr_string' => $result['qr_string'],
                'status'    => $result['status'],
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('PayMongo QRPH init error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
            exit;
        }
    }

    /**
     * PayMongo QRPH Status Poll
     * Returns current payment intent status.
     */
    public function paymongoQrphStatus(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        header('Content-Type: application/json');

        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }

        $piId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['pi_id'] ?? '');

        if (empty($piId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing payment intent ID.']);
            exit;
        }

        // Verify the PI ID matches what was created in this session
        if (empty($_SESSION['paymongo_pi_id']) || $_SESSION['paymongo_pi_id'] !== $piId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Session mismatch.']);
            exit;
        }

        if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'PayMongo not configured.']);
            exit;
        }

        try {
            $handler = new \Ginto\Handlers\PayMongoHandler();
            $result = $handler->getPaymentIntentStatus($piId);

            if (!$result['success']) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => $result['message']]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'status'  => $result['status'],
                'paid'    => ($result['status'] === 'succeeded'),
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('PayMongo QRPH status error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred.']);
            exit;
        }
    }

    /**
     * PayMongo QRPH Payment Registration
     * Verifies payment is succeeded, then creates the user account.
     */
    public function paymongoPayments(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        $this->validateAjaxRequest();
        header('Content-Type: application/json');

        try {
            $this->validateCsrf();
            $this->validateRequired(['username', 'email', 'password', 'country', 'phone']);

            // Validate the PI ID from POST against session
            $piId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['pi_id'] ?? '');

            if (empty($piId) || empty($_SESSION['paymongo_pi_id']) || $_SESSION['paymongo_pi_id'] !== $piId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid or missing payment reference. Please restart the payment process.']);
                exit;
            }

            // Verify expected amount matches
            $expectedAmount = (int)($_SESSION['paymongo_pi_amount'] ?? 0);
            $submittedAmount = (int)($_POST['package_amount'] ?? 0);
            if ($expectedAmount <= 0 || $expectedAmount !== $submittedAmount) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Payment amount mismatch. Please contact support.']);
                exit;
            }

            if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
                http_response_code(503);
                echo json_encode(['success' => false, 'message' => 'PayMongo not configured.']);
                exit;
            }

            // Verify payment is actually succeeded via API
            $handler = new \Ginto\Handlers\PayMongoHandler();
            $statusResult = $handler->getPaymentIntentStatus($piId);

            if (!$statusResult['success']) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => 'Could not verify payment. Please try again.']);
                exit;
            }

            if ($statusResult['status'] !== 'succeeded') {
                http_response_code(402);
                echo json_encode(['success' => false, 'message' => 'Payment has not been completed yet. Please scan the QR code and pay first.']);
                exit;
            }

            // The finalized charge ID (pay_xxxxxxxx) — distinct from the Payment Intent ID
            $gatewayPaymentId = $statusResult['payment_id'] ?? null;

            // Check for duplicate PI (prevent double registration)
            $existing = $this->db->get('subscription_payments', 'id', ['payment_reference' => $piId]);
            if ($existing) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'This payment has already been used to register an account.']);
                exit;
            }

            $this->checkExistingUser();

            // Create user and payment record with 'paid' status (auto-verified)
            $referrerId  = $this->resolveReferrerId();
            $fullname    = $this->getFullName();
            $planId      = $this->getPlanId();
            $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $publicId    = substr(md5(uniqid(mt_rand(), true)), 0, 12);

            $this->db->insert('users', [
                'email'          => $_POST['email'],
                'username'       => $_POST['username'],
                'password_hash'  => $passwordHash,
                'fullname'       => $fullname,
                'phone'          => $_POST['phone'],
                'country'        => $_POST['country'],
                'referrer_id'    => $referrerId,
                'public_id'      => $publicId,
                'payment_status' => 'paid',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            $userId = $this->db->id();

            if (!$userId) {
                error_log('PayMongo: Failed to create user account for PI ' . $piId);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create account. Please contact support.']);
                exit;
            }

            $paymentNotes = json_encode([
                'email'               => $_POST['email'],
                'username'            => $_POST['username'],
                'fullname'            => $fullname,
                'phone'               => $_POST['phone'],
                'country'             => $_POST['country'],
                'referrer_id'         => $referrerId,
                'paymongo_pi_id'      => $piId,
                'paymongo_payment_id' => $gatewayPaymentId,
                'payment_gateway'     => 'paymongo',
                'payment_type'        => 'qrph',
            ]);

            $transactionId = \Ginto\Helpers\TransactionHelper::generateTransactionId($this->db);
            $auditData     = \Ginto\Helpers\TransactionHelper::captureAuditData();
            $paymentAmount = (float)($_POST['package_amount'] ?? $expectedAmount);

            $this->db->insert('subscription_payments', array_merge([
                'user_id'             => $userId,
                'subscription_id'     => null,
                'plan_id'             => $planId,
                'type'                => 'registration',
                'amount'              => $paymentAmount,
                'currency'            => 'PHP',
                'payment_method'      => 'paymongo_qrph',
                'payment_reference'   => $piId,
                'gateway_payment_id'  => $gatewayPaymentId,
                'status'              => 'paid',
                'notes'               => $paymentNotes,
                'receipt_filename'    => null,
                'receipt_path'        => null,
                'transaction_id'      => $transactionId,
            ], $auditData));

            $paymentId = $this->db->id();

            if (!$paymentId) {
                $this->db->delete('users', ['id' => $userId]);
                error_log('PayMongo: Failed to insert payment record for user ' . $userId);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save payment record. Please contact support.']);
                exit;
            }

            // Create subscription record (QRPH = one-time payment, no auto-renew)
            $packageName = strtolower($_POST['package'] ?? 'go');
            $now         = date('Y-m-d H:i:s');
            $duration    = $_SESSION['paymongo_pi_duration'] ?? '12m';
            $expiresAt   = ($duration === '1m')
                ? date('Y-m-d H:i:s', strtotime('+1 month'))
                : date('Y-m-d H:i:s', strtotime('+1 year'));

            $this->db->insert('user_subscriptions', [
                'user_id'            => $userId,
                'plan_id'            => $planId,
                'status'             => 'active',
                'started_at'         => $now,
                'expires_at'         => $expiresAt,
                'payment_method'     => 'paymongo_qrph',
                'payment_reference'  => $piId,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount_paid'        => $paymentAmount,
                'currency'           => 'PHP',
                'auto_renew'         => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $subscriptionId = $this->db->id();

            // Link the payment record to the subscription
            if ($subscriptionId) {
                $this->db->update('subscription_payments', ['subscription_id' => $subscriptionId], ['id' => $paymentId]);
            }

            // Update user's subscription plan
            $this->db->update('users', ['subscription_plan' => $packageName], ['id' => $userId]);

            // Clear PayMongo session data
            unset($_SESSION['paymongo_pi_id'], $_SESSION['paymongo_pi_amount'], $_SESSION['paymongo_pi_tier'], $_SESSION['paymongo_pi_email'], $_SESSION['paymongo_pi_duration']);

            // Log in the user
            $_SESSION['user_id']        = $userId;
            $_SESSION['username']       = $_POST['username'];
            $_SESSION['email']          = $_POST['email'];
            $_SESSION['payment_status'] = 'paid';

            error_log("PayMongo QRPH registration complete: user_id={$userId}, payment_id={$paymentId}, subscription_id=" . ($subscriptionId ?? 'null') . ", pi_id={$piId}, pay_id=" . ($gatewayPaymentId ?? 'null'));

            echo json_encode([
                'success'    => true,
                'message'    => 'Payment confirmed! Your account is now active.',
                'payment_id' => $paymentId,
                'user_id'    => $userId,
                'redirect'   => '/chat',
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('PayMongo registration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
            exit;
        }
    }

    /**
     * Standalone Ginto Pay (Card) init for isolated debugging at /gintopay.
     * Charges card via PayMongo Payment Intent API (no hosted checkout redirect),
     * then waits for webhook to finalize account/subscription creation.
     */
    public function gintoPayStandaloneInit(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $this->requireAdminForStandaloneGintoPay();

        header('Content-Type: application/json');
        $this->validateAjaxRequest();
        $this->validateCsrf();

        $required = [
            'username', 'email', 'password', 'country', 'phone',
            'amount', 'tier', 'card_number', 'exp_month', 'exp_year', 'cvc'
        ];
        foreach ($required as $field) {
            if (empty(trim((string)($_POST[$field] ?? '')))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
                exit;
            }
        }

        $amountPhp = (int)($_POST['amount'] ?? 0);
        if ($amountPhp <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
            exit;
        }

        $email = filter_var(trim((string)$_POST['email']), FILTER_SANITIZE_EMAIL);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Ginto Pay is not configured.']);
            exit;
        }

        $fullname = trim(strip_tags((string)(
            $_POST['fullname']
            ?? (($_POST['firstname'] ?? '') . ' ' . ($_POST['lastname'] ?? ''))
        )));
        if ($fullname === '') {
            $fullname = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['username'] ?? 'user'));
        }

        // Ginto Pay is monthly-only.
        $duration = '1m';
        $tier     = strip_tags(trim((string)($_POST['tier'] ?? 'Membership')));

        $cardNumber = preg_replace('/[^0-9]/', '', (string)($_POST['card_number'] ?? ''));
        $expMonth   = preg_replace('/[^0-9]/', '', (string)($_POST['exp_month'] ?? ''));
        $expYear    = preg_replace('/[^0-9]/', '', (string)($_POST['exp_year'] ?? ''));
        $cvc        = preg_replace('/[^0-9]/', '', (string)($_POST['cvc'] ?? ''));
        $addressLine1 = strip_tags(trim((string)($_POST['billing_line1'] ?? '')));
        $addressLine2 = strip_tags(trim((string)($_POST['billing_line2'] ?? '')));
        $billingCity  = strip_tags(trim((string)($_POST['billing_city'] ?? '')));
        $billingState = strip_tags(trim((string)($_POST['billing_state'] ?? '')));
        $billingPostalCode = preg_replace('/[^a-zA-Z0-9\-\s]/', '', (string)($_POST['billing_postal_code'] ?? ''));
        $billingCountry = strtoupper(preg_replace('/[^a-zA-Z]/', '', (string)($_POST['billing_country'] ?? ($_POST['country'] ?? 'PH'))));

        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid card number.']);
            exit;
        }

        if ((int)$expMonth < 1 || (int)$expMonth > 12 || strlen($expYear) < 2 || strlen($cvc) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid card expiry or CVC.']);
            exit;
        }

        try {
            $regData = [
                'username'      => preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['username'] ?? '')),
                'email'         => $email,
                'password_hash' => password_hash((string)($_POST['password'] ?? ''), PASSWORD_DEFAULT),
                'firstname'     => strip_tags(trim((string)($_POST['firstname'] ?? ''))),
                'lastname'      => strip_tags(trim((string)($_POST['lastname'] ?? ''))),
                'fullname'      => $fullname,
                'phone'         => preg_replace('/[^0-9+\-\s]/', '', (string)($_POST['phone'] ?? '')),
                'country'       => preg_replace('/[^a-zA-Z]/', '', (string)($_POST['country'] ?? '')),
                'package'       => strip_tags(trim((string)($_POST['package'] ?? 'go'))),
                'amount'        => $amountPhp,
                'currency'      => 'PHP',
                'duration'      => $duration,
                'tier'          => $tier,
                'referrer_id'   => $this->resolveReferrerId(),
                'promo_code'    => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_POST['promo_code'] ?? '')),
                'billing_line1' => $addressLine1,
                'billing_line2' => $addressLine2,
                'billing_city'  => $billingCity,
                'billing_state' => $billingState,
                'billing_postal_code' => $billingPostalCode,
                'billing_country' => $billingCountry,
                'source'        => 'gintopay_standalone',
            ];

            $description = 'Ginto ' . $tier . ' Membership';

            $handler = new \Ginto\Handlers\PayMongoHandler();
            $result = $handler->initCardPayment(
                $amountPhp,
                $email,
                $fullname,
                $regData['phone'],
                $description,
                [
                    'number'    => $cardNumber,
                    'exp_month' => $expMonth,
                    'exp_year'  => $expYear,
                    'cvc'       => $cvc,
                ],
                [
                    'line1'       => $addressLine1,
                    'line2'       => $addressLine2,
                    'city'        => $billingCity,
                    'state'       => $billingState,
                    'postal_code' => $billingPostalCode,
                    'country'     => $billingCountry,
                ]
            );

            if (!$result['success']) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Unable to initialize card payment.']);
                exit;
            }

            $piId = $result['pi_id'] ?? '';
            if ($piId === '') {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => 'Missing payment intent ID.']);
                exit;
            }

            $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $this->db->insert('pending_registrations', [
                'checkout_session_id' => $piId,
                'reg_data'            => json_encode($regData),
                'amount'              => $amountPhp,
                'duration'            => $duration,
                'status'              => 'pending',
                'expires_at'          => $expiresAt,
            ]);

            $nextActionUrl = $result['next_action']['redirect']['url']
                ?? $result['next_action']['url']
                ?? null;

            echo json_encode([
                'success'         => true,
                'pi_id'           => $piId,
                'status'          => $result['status'] ?? 'unknown',
                'requires_action' => !empty($nextActionUrl),
                'next_action_url' => $nextActionUrl,
                'billing_name'    => $fullname,
                'billing_email'   => $email,
                'message'         => 'Payment initialized. Waiting for webhook confirmation.',
            ]);
            exit;

        } catch (\Throwable $e) {
            error_log('GintoPay standalone init error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
            exit;
        }
    }

    /**
     * Standalone status endpoint for /gintopay.
     */
    public function gintoPayStandaloneStatus(): void
    {
        $this->requireAdminForStandaloneGintoPay();
        header('Content-Type: application/json');

        $piId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['pi_id'] ?? ''));
        if ($piId === '') {
            echo json_encode(['processed' => false, 'failed' => true, 'message' => 'Missing payment intent ID.']);
            exit;
        }

        $pending = $this->db->get('pending_registrations', ['status', 'user_id', 'expires_at'], [
            'checkout_session_id' => $piId,
        ]);

        if (!$pending) {
            echo json_encode(['processed' => false, 'failed' => false]);
            exit;
        }

        if ($pending['status'] === 'completed' && !empty($pending['user_id'])) {
            echo json_encode([
                'processed' => true,
                'failed'    => false,
                'redirect'  => '/chat',
            ]);
            exit;
        }

        if ($pending['status'] === 'failed') {
            echo json_encode([
                'processed' => false,
                'failed'    => true,
                'message'   => 'Payment processing failed.',
            ]);
            exit;
        }

        if (!empty($pending['expires_at']) && strtotime((string)$pending['expires_at']) < time()) {
            echo json_encode([
                'processed' => false,
                'failed'    => true,
                'message'   => 'Payment session expired.',
            ]);
            exit;
        }

        echo json_encode(['processed' => false, 'failed' => false]);
        exit;
    }

    /**
     * Ginto Pay (Card) Initialization
     * Validates registration form data, stores it in session, creates a PayMongo
     * Checkout Session for card payment, and returns the checkout URL.
     */
    public function gintoPayInit(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
            exit;
        }

        // Validate required registration fields
        $required = ['username', 'email', 'password', 'country', 'phone'];
        foreach ($required as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please complete all registration steps before payment.']);
                exit;
            }
        }

        $amountRaw = $_POST['amount'] ?? '';
        if (!is_numeric($amountRaw) || (int)$amountRaw <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
            exit;
        }
        $amountPhp = (int)$amountRaw;

        $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $fullname = trim(($_POST['firstname'] ?? '') . ' ' . ($_POST['lastname'] ?? ''));
        if (empty($fullname)) $fullname = $_POST['username'];
        $tier     = strip_tags(trim($_POST['tier'] ?? 'Membership'));
        // Ginto Pay is monthly-only.
        $duration = '1m';

        if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Ginto Pay is not configured. Please contact support.']);
            exit;
        }

        try {
            // Store all registration form fields in session before redirect
            $_SESSION['ginto_pay_reg'] = [
                'username'         => preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['username']),
                'email'            => $email,
                'password_hash'    => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'firstname'        => strip_tags(trim($_POST['firstname'] ?? '')),
                'lastname'         => strip_tags(trim($_POST['lastname'] ?? '')),
                'fullname'         => strip_tags($fullname),
                'phone'            => preg_replace('/[^0-9+\-\s]/', '', $_POST['phone'] ?? ''),
                'country'          => preg_replace('/[^a-zA-Z]/', '', $_POST['country'] ?? ''),
                'package'          => strip_tags(trim($_POST['package'] ?? 'go')),
                'amount'           => $amountPhp,
                'currency'         => 'PHP',
                'duration'         => $duration,
                'tier'             => $tier,
                'referrer_id'      => $this->resolveReferrerId(),
                'promo_code'       => preg_replace('/[^a-zA-Z0-9\-_]/', '', $_POST['promo_code'] ?? ''),
            ];

            $appUrl     = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? '', '/');
            $successUrl = $appUrl . '/register/awaiting?subscription_id={SUBSCRIPTION_ID}';
            $cancelUrl  = $appUrl . '/register';

            $durationLabel = 'Monthly';
            $description   = 'Ginto ' . $tier . ' ' . $durationLabel . ' Subscription';

            $handler = new \Ginto\Handlers\PayMongoHandler();

            // Create subscription plan
            $planResult = $handler->createSubscriptionPlan(
                $tier . ' Monthly Plan',
                $description,
                $amountPhp * 100,
                'PHP',
                'MONTH',
                1
            );

            if (!$planResult['success']) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => $planResult['message']]);
                exit;
            }

            $planId = $planResult['plan_id'];

            // Create subscription
            $billing = [
                'name'    => strip_tags($fullname),
                'email'   => $email,
                'phone'   => preg_replace('/[^0-9+\-\s]/', '', $_POST['phone'] ?? ''),
            ];

            if (!empty($_POST['billing_line1'])) {
                $billing['address'] = [
                    'line1'       => strip_tags(trim($_POST['billing_line1'])),
                    'line2'       => strip_tags(trim($_POST['billing_line2'] ?? '')),
                    'city'        => strip_tags(trim($_POST['billing_city'] ?? '')),
                    'state'       => strip_tags(trim($_POST['billing_state'] ?? '')),
                    'postal_code' => preg_replace('/[^a-zA-Z0-9\-\s]/', '', $_POST['billing_postal_code'] ?? ''),
                    'country'     => strtoupper(preg_replace('/[^a-zA-Z]/', '', $_POST['billing_country'] ?? ($_POST['country'] ?? 'PH'))),
                ];
            }

            $subscriptionResult = $handler->createSubscription($planId, null, $billing);

            if (!$subscriptionResult['success']) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => $subscriptionResult['message']]);
                exit;
            }

            $_SESSION['ginto_pay_subscription_id'] = $subscriptionResult['subscription_id'];

            // Persist registration data to DB so webhook handler can create the user
            // without needing access to this PHP session.
            $regDataJson = json_encode($_SESSION['ginto_pay_reg']);
            $expiresAt   = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $this->db->insert('pending_registrations', [
                'checkout_session_id' => $subscriptionResult['subscription_id'], // Use subscription ID as key
                'reg_data'            => $regDataJson,
                'amount'              => $amountPhp,
                'duration'            => '1m',
                'status'              => 'pending',
                'expires_at'          => $expiresAt,
            ]);

            echo json_encode([
                'success'      => true,
                'checkout_url' => $subscriptionResult['checkout_url'],
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('Ginto Pay init error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
            exit;
        }
    }

    /**
     * Ginto Pay Awaiting Page
     * Shown after PayMongo redirects back from the hosted checkout.
     * The actual account creation is handled by the webhook; this page
     * polls /api/payments/ginto-pay-status until the account is ready.
     */
    public function gintoPayAwaiting(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['session_id'] ?? '');
        if (empty($sessionId)) {
            $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? '', '/');
            header('Location: ' . $appUrl . '/register?error=' . urlencode('Missing session ID.'));
            exit;
        }

        $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? '', '/');
        $sessionIdEsc = htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Received – Ginto AI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
               background: #0f0f0f; color: #fff; display: flex; align-items: center;
               justify-content: center; min-height: 100vh; }
        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 16px;
                padding: 48px 40px; text-align: center; max-width: 460px; width: 90%; }
        .spinner { width: 56px; height: 56px; border: 4px solid #333;
                   border-top-color: #a78bfa; border-radius: 50%;
                   animation: spin 0.9s linear infinite; margin: 0 auto 28px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 1.5rem; margin-bottom: 10px; }
        p  { color: #999; line-height: 1.6; }
        .status { margin-top: 20px; font-size: 0.85rem; color: #666; }
        .success-icon { display: none; font-size: 3rem; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner" id="spinner"></div>
        <div class="success-icon" id="success-icon">✅</div>
        <h1 id="title">Payment Received!</h1>
        <p id="message">We're setting up your account. This usually takes just a few seconds…</p>
        <p class="status" id="status-text">Checking status…</p>
    </div>
    <script>
        const sessionId = '{$sessionIdEsc}';
        const apiUrl    = '/api/payments/ginto-pay-status?session_id=' + encodeURIComponent(sessionId);
        let   attempts  = 0;
        const maxAttempts = 40; // ~80 seconds

        function check() {
            fetch(apiUrl)
                .then(r => r.json())
                .then(data => {
                    attempts++;
                    if (data.processed) {
                        document.getElementById('spinner').style.display = 'none';
                        document.getElementById('success-icon').style.display = 'block';
                        document.getElementById('title').textContent = 'Account Ready!';
                        document.getElementById('message').textContent = 'Redirecting you to Ginto AI…';
                        document.getElementById('status-text').textContent = '';
                        setTimeout(() => { window.location.href = data.redirect || '/chat'; }, 1500);
                    } else if (data.failed) {
                        document.getElementById('spinner').style.display = 'none';
                        document.getElementById('title').textContent = 'Payment Issue';
                        document.getElementById('message').textContent = data.message || 'Something went wrong. Please contact support.';
                        document.getElementById('status-text').textContent = '';
                    } else if (attempts < maxAttempts) {
                        document.getElementById('status-text').textContent = 'Still checking… (attempt ' + attempts + ')';
                        setTimeout(check, 2000);
                    } else {
                        document.getElementById('spinner').style.display = 'none';
                        document.getElementById('title').textContent = 'Taking Longer Than Expected';
                        document.getElementById('message').textContent = 'Your payment was received. Your account will be ready shortly. Please check your email or try logging in.';
                        document.getElementById('status-text').textContent = '';
                    }
                })
                .catch(() => {
                    attempts++;
                    if (attempts < maxAttempts) setTimeout(check, 3000);
                });
        }

        setTimeout(check, 2000);
    </script>
</body>
</html>
HTML;
        exit;
    }

    /**
     * Ginto Pay Status Polling Endpoint
     * JSON API called by the /register/awaiting page to check if account creation is done.
     */
    public function gintoPayStatus(): void
    {
        header('Content-Type: application/json');

        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['session_id'] ?? '');
        if (empty($sessionId)) {
            echo json_encode(['processed' => false, 'failed' => true, 'message' => 'Missing session ID.']);
            exit;
        }

        $pending = $this->db->get('pending_registrations', ['status', 'user_id', 'expires_at'], [
            'checkout_session_id' => $sessionId,
        ]);

        if (!$pending) {
            // Row may not exist if init failed; wait gracefully
            echo json_encode(['processed' => false, 'failed' => false]);
            exit;
        }

        if ($pending['status'] === 'completed' && $pending['user_id']) {
            echo json_encode(['processed' => true, 'redirect' => '/chat']);
            exit;
        }

        if ($pending['status'] === 'failed') {
            echo json_encode(['processed' => false, 'failed' => true, 'message' => 'Payment processing failed. Please contact support.']);
            exit;
        }

        // Check expiry
        if (strtotime($pending['expires_at']) < time()) {
            echo json_encode(['processed' => false, 'failed' => true, 'message' => 'Session expired. Please try registering again.']);
            exit;
        }

        echo json_encode(['processed' => false, 'failed' => false]);
        exit;
    }

    /**
     * Ginto Pay (Card) Completion
     * Called via GET after PayMongo redirects back from the hosted checkout page.
     * Verifies the session, creates the user account and subscription.
     */
    public function gintoPayComplete(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['session_id'] ?? '');

        $fail = function(string $msg) {
            $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? '', '/');
            header('Location: ' . $appUrl . '/register?error=' . urlencode($msg));
            exit;
        };

        if (empty($sessionId)) {
            $fail('Missing checkout session ID.');
        }

        // Verify session matches what we stored
        if (empty($_SESSION['ginto_pay_session_id']) || $_SESSION['ginto_pay_session_id'] !== $sessionId) {
            $fail('Session mismatch. Please try registering again.');
        }

        $regData = $_SESSION['ginto_pay_reg'] ?? null;
        if (empty($regData)) {
            $fail('Registration data expired. Please fill in the form again.');
        }

        if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
            $fail('Payment gateway not configured.');
        }

        try {
            $handler       = new \Ginto\Handlers\PayMongoHandler();
            $sessionResult = $handler->getCheckoutSession($sessionId);

            if (!$sessionResult['success']) {
                $fail('Could not verify payment: ' . $sessionResult['message']);
            }

            if ($sessionResult['status'] !== 'completed') {
                $fail('Payment was not completed. Please try again.');
            }

            // Check duplicate session
            $existing = $this->db->get('subscription_payments', 'id', ['payment_reference' => $sessionId]);
            if ($existing) {
                // Already registered — just redirect to chat
                $_SESSION['user_id']        = $existing['user_id'] ?? null;
                header('Location: /chat');
                exit;
            }

            $planIdMap    = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4, 'starter' => 1, 'professional' => 2, 'executive' => 3, 'gold' => 4, 'platinum' => 5];
            $packageName  = strtolower($regData['package'] ?? 'go');
            $planId       = $planIdMap[$packageName] ?? 2;
            // Ginto Pay is monthly-only.
            $duration     = '1m';
            $paymentAmount = (float)($regData['amount'] ?? 0);
            $now          = date('Y-m-d H:i:s');
            $expiresAt    = ($duration === '1m')
                ? date('Y-m-d H:i:s', strtotime('+1 month'))
                : date('Y-m-d H:i:s', strtotime('+1 year'));

            $publicId = substr(md5(uniqid(mt_rand(), true)), 0, 12);

            $this->db->insert('users', [
                'email'          => $regData['email'],
                'username'       => $regData['username'],
                'password_hash'  => $regData['password_hash'],
                'fullname'       => $regData['fullname'],
                'phone'          => $regData['phone'],
                'country'        => $regData['country'],
                'referrer_id'    => $regData['referrer_id'],
                'public_id'      => $publicId,
                'payment_status' => 'paid',
                'subscription_plan' => $packageName,
                'created_at'     => $now,
            ]);

            $userId = $this->db->id();
            if (!$userId) {
                $fail('Failed to create account. Please contact support.');
            }

            $transactionId    = \Ginto\Helpers\TransactionHelper::generateTransactionId($this->db);
            $auditData        = \Ginto\Helpers\TransactionHelper::captureAuditData();
            $gatewayPaymentId = $sessionResult['payment_id'] ?? null;
            $paymentIntentId  = $sessionResult['payment_intent_id'] ?? null;

            $paymentNotes = json_encode([
                'email'               => $regData['email'],
                'username'            => $regData['username'],
                'fullname'            => $regData['fullname'],
                'phone'               => $regData['phone'],
                'country'             => $regData['country'],
                'paymongo_session_id' => $sessionId,
                'paymongo_pi_id'      => $paymentIntentId,
                'paymongo_payment_id' => $gatewayPaymentId,
                'payment_gateway'     => 'paymongo',
                'payment_type'        => 'card',
                'duration'            => $duration,
            ]);

            $this->db->insert('subscription_payments', array_merge([
                'user_id'            => $userId,
                'subscription_id'    => null,
                'plan_id'            => $planId,
                'type'               => 'registration',
                'amount'             => $paymentAmount,
                'currency'           => 'PHP',
                'payment_method'     => 'ginto_pay_card',
                'payment_reference'  => $sessionId,
                'gateway_payment_id' => $gatewayPaymentId,
                'status'             => 'paid',
                'notes'              => $paymentNotes,
                'transaction_id'     => $transactionId,
            ], $auditData));

            $paymentId = $this->db->id();
            if (!$paymentId) {
                $this->db->delete('users', ['id' => $userId]);
                $fail('Failed to save payment record. Please contact support.');
            }

            // Create subscription record
            $this->db->insert('user_subscriptions', [
                'user_id'            => $userId,
                'plan_id'            => $planId,
                'status'             => 'active',
                'started_at'         => $now,
                'expires_at'         => $expiresAt,
                'payment_method'     => 'ginto_pay_card',
                'payment_reference'  => $sessionId,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount_paid'        => $paymentAmount,
                'currency'           => 'PHP',
                'auto_renew'         => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $subscriptionId = $this->db->id();
            if ($subscriptionId) {
                $this->db->update('subscription_payments', ['subscription_id' => $subscriptionId], ['id' => $paymentId]);
            }

            // Clear Ginto Pay session data
            unset($_SESSION['ginto_pay_reg'], $_SESSION['ginto_pay_session_id']);

            // Log in the user
            $_SESSION['user_id']        = $userId;
            $_SESSION['username']       = $regData['username'];
            $_SESSION['email']          = $regData['email'];
            $_SESSION['payment_status'] = 'paid';

            error_log("Ginto Pay card registration complete: user_id={$userId}, payment_id={$paymentId}, session_id={$sessionId}");

            $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? '', '/');
            header('Location: ' . $appUrl . '/chat');
            exit;

        } catch (\Exception $e) {
            error_log('Ginto Pay complete error: ' . $e->getMessage());
            $fail('An error occurred. Please contact support.');
        }
    }
}
