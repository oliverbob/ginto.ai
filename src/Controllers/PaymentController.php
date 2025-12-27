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
        
        $this->db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => null,
            'plan_id' => $planId,
            'type' => 'registration',
            'amount' => !empty($_POST['package_amount']) ? floatval($_POST['package_amount']) : 0,
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
            error_log("Failed to insert subscription_payment record for $paymentMethod");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save registration. Please try again.']);
            exit;
        }
        
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
}
