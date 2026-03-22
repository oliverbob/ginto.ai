<?php
namespace Ginto\Controllers;

use Ginto\Models\Product;
use Ginto\Core\Database;
use Ginto\Services\MallCommerceService;

class SellerController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        parent::__construct();
        $this->db = $db ?? Database::getInstance();
    }

    /** Redirect to /wallet/products if the request came from that namespace, else the legacy path. */
    private function productsUrl(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if (str_starts_with($uri, '/wallet/') || str_contains($ref, '/wallet/')) {
            return '/wallet/products';
        }
        return '/marketplace/sellers/products';
    }

    public function kycForm()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login'); exit;
        }
        $csrf   = generateCsrfToken();
        $userId = (int)$_SESSION['user_id'];
        $user   = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        // Admins don't need KYC — redirect them straight to products
        if (in_array($user['role_id'] ?? 0, [1, 2])) {
            header('Location: ' . $this->productsUrl()); exit;
        }
        $kyc = $this->db->get('kyc_profiles', '*', ['user_id' => $userId]);
        return $this->view('mall/kyc', ['csrf_token' => $csrf, 'kyc' => $kyc]);
    }

    public function submitKyc()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); echo 'Login required'; return; }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId = (int)$_SESSION['user_id'];
        $first         = trim($_POST['first_name']      ?? '');
        $middleName    = trim($_POST['middle_name']     ?? '');
        $last          = trim($_POST['last_name']       ?? '');
        $dob           = trim($_POST['dob']             ?? '');
        $placeOfBirth  = trim($_POST['place_of_birth']  ?? '');
        $nationality   = trim($_POST['nationality']     ?? '');
        $country       = trim($_POST['country']         ?? '');
        $phone         = trim($_POST['phone']           ?? '');
        $addressStreet = trim($_POST['address_street']  ?? '');
        $addressCity   = trim($_POST['address_city']    ?? '');
        $addressProv   = trim($_POST['address_province'] ?? '');
        $addressZip    = trim($_POST['address_zip']     ?? '');
        $tin           = trim($_POST['tin']             ?? '');
        $idType        = trim($_POST['id_type']         ?? '');
        $identifier    = trim($_POST['identifier']      ?? '');
        $businessName  = trim($_POST['business_name']   ?? '');
        $businessReg   = trim($_POST['business_reg']    ?? '');

        // Account type (from wizard Step 2)
        $allowedAccountTypes = [
            'personal','livelihood','retailer','wholesale','general_merchandise',
            'mall','products','services','real_estate','rentals','multi_purpose',
            'digital_content','intellectual_property','food_beverage',
            'fashion_apparel','health_wellness','arts_crafts',
            'business','cooperative','non_profit','government_program',
            'delivery_service',
            'ginto_sell_for_me','ginto_special_agreement','ginto_partnership_program',
        ];
        $rawAccountType = trim($_POST['account_type'] ?? '');
        $accountType = in_array($rawAccountType, $allowedAccountTypes, true) ? $rawAccountType : null;

        // Sanitise and capture doc_types checkboxes
        $rawDocTypes = $_POST['doc_types'] ?? [];
        $allowedDocTypes = [
            // Identity documents
            'id_front','id_back','selfie_with_id',
            'birth_certificate','barangay_clearance','church_clearance',
            'ncip_certificate','entity_endorsement','other_id',
            // Supporting / business documents
            'proof_of_address','dti_certificate','sec_certificate',
            'business_permit','bir_cor','cda_certificate','government_program_certificate','other_support',
            'police_clearance','nbi_clearance',
            'other', // legacy
        ];
        $docTypes = array_values(array_intersect((array)$rawDocTypes, $allowedDocTypes));

        // Delivery-service KYC requirements:
        // 1) valid ID, 2) barangay OR church clearance, 3) police OR NBI clearance
        if ($accountType === 'delivery_service') {
            $hasValidId = in_array('id_front', $docTypes, true) || in_array('other_id', $docTypes, true) || in_array('id_back', $docTypes, true);
            $hasLocalClearance = in_array('barangay_clearance', $docTypes, true) || in_array('church_clearance', $docTypes, true);
            $hasPoliceOrNbi = in_array('police_clearance', $docTypes, true) || in_array('nbi_clearance', $docTypes, true);

            if (!$hasValidId || !$hasLocalClearance || !$hasPoliceOrNbi) {
                http_response_code(400);
                echo 'Delivery service registration requires valid ID, barangay/church clearance, and police/NBI clearance.';
                return;
            }
        }

        // Handle uploaded documents from both upload areas
        $docs = [];
        $uploadDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage') . '/kyc/' . $userId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        foreach (['id_files', 'support_files', 'documents'] as $fieldName) {
            if (empty($_FILES[$fieldName])) continue;
            $files = $_FILES[$fieldName];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name   = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($files['name'][$i]));
                $target = $uploadDir . uniqid() . '_' . $name;
                if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                    $docs[] = str_replace(
                        (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage'),
                        '/storage', $target
                    );
                }
            }
        }

        $payload = [
            'user_id'          => $userId,
            'first_name'       => $first ?: null,
            'middle_name'      => $middleName ?: null,
            'last_name'        => $last ?: null,
            'dob'              => $dob ?: null,
            'place_of_birth'   => $placeOfBirth ?: null,
            'nationality'      => $nationality ?: null,
            'country'          => $country ?: null,
            'phone'            => $phone ?: null,
            'address_street'   => $addressStreet ?: null,
            'address_city'     => $addressCity ?: null,
            'address_province' => $addressProv ?: null,
            'address_zip'      => $addressZip ?: null,
            'tin'              => $tin ?: null,
            'id_type'          => $idType ?: null,
            'identifier'       => $identifier ?: null,
            'documents'        => !empty($docs) ? json_encode($docs) : null,
            'doc_types'        => !empty($docTypes) ? json_encode($docTypes) : null,
            'account_type'     => $accountType,
            'business_name'    => $businessName ?: null,
            'business_reg'     => $businessReg ?: null,
            'submitted_at'     => date('Y-m-d H:i:s'),
            'status'           => 'pending',
        ];

        $existing = $this->db->get('kyc_profiles', 'id', ['user_id' => $userId]);
        if ($existing) {
            $this->db->update('kyc_profiles', $payload, ['user_id' => $userId]);
        } else {
            $this->db->insert('kyc_profiles', $payload);
        }

        header('Location: /marketplace/sellers/kyc');
        exit;
    }

    public function products()
    {
        if (empty($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $userId   = (int)$_SESSION['user_id'];
        $user     = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin  = in_array($user['role_id'] ?? 0, [1, 2]);

        try {
            $productModel = new Product();
            // Always filter by the logged-in user — even admins only see their own products here
            // limit: 9999 ensures the count is accurate for sellers with many products
            $filterPublished = ['seller_id' => $userId, 'status' => 'published', 'limit' => 9999];
            $filterDraft     = ['seller_id' => $userId, 'status' => 'draft',     'limit' => 9999];
            $products = $productModel->list($filterPublished);
            $drafts   = $productModel->list($filterDraft);

            // KYC / subscription enrichment — admins are implicitly approved
            $kyc_status = $isAdmin ? 'approved' : 'none';
            $subscription_status = 'inactive';
            $subRow = null;
            if (!$isAdmin) {
                $kycRow = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
                $subRow = $this->db->get('seller_subscriptions', ['status','next_billing_at'], ['user_id' => $userId]);
                $kyc_status          = is_array($kycRow) ? ($kycRow['status'] ?? 'none') : 'none';
                $subscription_status = is_array($subRow) ? ($subRow['status'] ?? 'inactive') : 'inactive';
            }

            $csrf = generateCsrfToken();
            $commerce = new MallCommerceService();
            $walletSummary = $commerce->getWalletSummary($userId);
            return $this->view('mall/seller_products', [
                'products'            => $products,
                'drafts'              => $drafts,
                'csrf_token'          => $csrf,
                'kyc_status'          => $kyc_status,
                'subscription_status' => $subscription_status,
                'next_billing_at'     => $subRow['next_billing_at'] ?? null,
                'is_admin'            => $isAdmin,
                'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            // Log full stack trace and user context for debugging
            error_log('SellerController::products error: user=' . $userId . ' msg=' . $e->getMessage() . "\n" . $e->getTraceAsString());

            // Also write to a dedicated marketplace log file for easy retrieval
            $logFile = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__,2) . '/../storage') . '/logs/marketplace_errors.log';
            @mkdir(dirname($logFile), 0755, true);
            @file_put_contents($logFile, date('c') . ' - user=' . $userId . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);

            http_response_code(500);
            echo '<div style="max-width:800px;margin:40px auto;padding:20px;background:#fff;border-radius:8px;color:#111"><h2>Server error</h2><p>Sorry, we could not load your products right now. The incident has been logged.</p><p><a href="/dashboard">Return to dashboard</a></p></div>';
            return;
        }
    }

    public function productNew()
    {
        if (empty($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $csrf   = generateCsrfToken();
        $userId = (int)$_SESSION['user_id'];
        $user   = $this->db->get('users', ['id','role_id','seller_tos_agreed_at'], ['id' => $userId]);
        $isAdmin = in_array($user['role_id'] ?? 0, [1, 2]);

        // Admins always bypass KYC and TOS
        $kycStatus = 'approved';
        $tosAgreed = true;
        if (!$isAdmin) {
            $kycRow    = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
            $kycStatus = is_array($kycRow) ? ($kycRow['status'] ?? 'none') : 'none';
            $tosAgreed = !empty($user['seller_tos_agreed_at']);
        }

        $categories = $this->db->select('categories', '*') ?: [];
        $commerce = new MallCommerceService();
        $walletSummary = $commerce->getWalletSummary($userId);
        return $this->view('mall/product_form', [
            'csrf_token'          => $csrf,
            'categories'          => $categories,
            'kyc_status'          => $kycStatus,
            'tos_agreed'          => $tosAgreed,
            'is_admin'            => $isAdmin,
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    /** Render the storefront editor (GET). Creates the storefront row if it doesn't exist yet. */
    public function storefrontEdit()
    {
        if (empty($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $userId  = (int)$_SESSION['user_id'];
        $user    = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin = in_array($user['role_id'] ?? 0, [1, 2]);

        $commerce      = new MallCommerceService();
        $storefront    = $commerce->ensureStorefront($userId);
        $walletSummary = $commerce->getWalletSummary($userId);

        $saved    = isset($_GET['saved']);
        $errMsg   = $_GET['error'] ?? null;

        return $this->view('mall/seller_storefront_edit', [
            'csrf_token'          => generateCsrfToken(),
            'storefront'          => $storefront,
            'is_admin'            => $isAdmin,
            'saved'               => $saved,
            'error_msg'           => $errMsg ? htmlspecialchars((string)$errMsg, ENT_QUOTES, 'UTF-8') : null,
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    /** Handle storefront save (POST). */
    public function storefrontSave()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); return; }
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId   = (int)$_SESSION['user_id'];
        $commerce = new MallCommerceService();

        // Detect which namespace the request came from
        $referer  = $_SERVER['HTTP_REFERER'] ?? '';
        $base     = str_contains($referer, '/wallet/') ? '/wallet/storefront' : '/marketplace/sellers/storefront';

        try {
            $commerce->saveStorefront($userId, $_POST, $_FILES);
            header('Location: ' . $base . '?saved=1'); exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . $base . '?error=' . urlencode($e->getMessage())); exit;
        }
    }

    /**
     * AJAX endpoint: seller agrees to Terms of Service.
     * Stores timestamp server-side so the TOS modal won't be shown again on any device.
     */
    public function tosAgree()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Login required']); return; }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']); return; }

        $userId = (int)$_SESSION['user_id'];
        $this->db->update('users', ['seller_tos_agreed_at' => date('Y-m-d H:i:s')], ['id' => $userId]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function productCreate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); echo 'Login required'; return; }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId = (int)$_SESSION['user_id'];
        $user = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin = in_array($user['role_id'] ?? 0, [1,2]);

        // Check KYC status for non-admins
        if (!$isAdmin) {
            $kycRow = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
            $kycStatus = is_array($kycRow) ? ($kycRow['status'] ?? null) : $kycRow;
            if (empty($kycStatus) || $kycStatus !== 'approved') {
                http_response_code(403);
                echo 'KYC not approved - you must complete KYC to create products.'; return;
            }
        }

        $productType = trim((string)($_POST['product_type'] ?? 'physical'));
        if (!in_array($productType, ['physical','liquid','digital','virtual','subscription'], true)) {
            $productType = 'physical';
        }
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $short = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $currency = $_POST['currency'] ?? 'USD';
        $category = intval($_POST['category_id'] ?? 0) ?: null;
        $pricingModel = trim((string)($_POST['pricing_model'] ?? 'hands_off'));
        $validPricingModels = ['hands_off','active_discovery','full_service','referral','markup','standard'];
        if (!in_array($pricingModel, $validPricingModels, true)) {
            $pricingModel = 'hands_off';
        }
        // pricing_rate is determined by the plan choice
        $planRateMap = ['hands_off'=>12.00,'active_discovery'=>25.00,'full_service'=>35.00,'referral'=>15.00,'markup'=>0.00,'standard'=>10.00];
        $pricingRate = $planRateMap[$pricingModel] ?? 12.00;
        $markupRate  = max(0, min(200, (float)($_POST['markup_rate'] ?? 0)));
        $qty         = intval($_POST['quantity'] ?? 0);

        // Available colors (JSON array from tag input)
        $colorsJson = null;
        $rawColors = trim($_POST['colors'] ?? '');
        if ($rawColors !== '' && $rawColors !== '[]') {
            $colorsArr = json_decode($rawColors, true);
            if (is_array($colorsArr)) {
                $colorsArr = array_slice(array_values(array_map('strval', $colorsArr)), 0, 50);
                $colorsJson = !empty($colorsArr) ? json_encode($colorsArr) : null;
            }
        }

        // Shipping dimensions — must include item + all packaging (box, wrap, etc.)
        $weightKg = isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '' ? max(0.0, (float)$_POST['weight_kg']) : null;
        $lengthCm = isset($_POST['length_cm']) && $_POST['length_cm'] !== '' ? max(0.0, (float)$_POST['length_cm']) : null;
        $widthCm  = isset($_POST['width_cm'])  && $_POST['width_cm']  !== '' ? max(0.0, (float)$_POST['width_cm'])  : null;
        $heightCm = isset($_POST['height_cm']) && $_POST['height_cm'] !== '' ? max(0.0, (float)$_POST['height_cm']) : null;
        $volumeMl = isset($_POST['volume_ml']) && $_POST['volume_ml'] !== '' ? max(0.0, (float)$_POST['volume_ml']) : null;

        $attributes = [];
        if ($productType === 'liquid' && $volumeMl !== null && $volumeMl > 0) {
            $attributes['volume_ml'] = round($volumeMl, 2);
            $attributes['liquid_unit'] = 'mL';
        }

        // Handle images upload (multiple) — use B2 when configured, else local storage
        $imagesArray = [];
        $imgLogFile  = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage') . '/logs/marketplace_errors.log';
        if (!empty($_FILES['images'])) {
            $files   = $_FILES['images'];
            $useB2   = \Ginto\Helpers\B2Helper::isEnabled();
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage';
            @file_put_contents($imgLogFile, date('c') . " - productCreate image upload start - useB2=" . ($useB2 ? 'yes' : 'no') . " files=" . count($files['name']) . "\n", FILE_APPEND);
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    @file_put_contents($imgLogFile, date('c') . " - file[$i] upload error code: " . $files['error'][$i] . "\n", FILE_APPEND);
                    continue;
                }
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($files['name'][$i]));
                if ($useB2) {
                    $fileData   = file_get_contents($files['tmp_name'][$i]);
                    $remotePath = 'mall/images/' . uniqid() . '_' . $name;
                    $mimeType   = $files['type'][$i] ?: 'image/jpeg';
                    try {
                        $url = \Ginto\Helpers\B2Helper::upload($fileData, $remotePath, $mimeType);
                        $imagesArray[] = $url;
                        @file_put_contents($imgLogFile, date('c') . " - B2 upload OK: $url\n", FILE_APPEND);
                    } catch (\Exception $e) {
                        @file_put_contents($imgLogFile, date('c') . " - B2 upload FAILED [$i]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
                        // Fall back to local storage on B2 failure
                        $uploadDir = $storagePath . '/mall/images/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
                        $target = $uploadDir . uniqid() . '_' . $name;
                        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                            chmod($target, 0640);
                            $imagesArray[] = str_replace($storagePath, '/storage', $target);
                            @file_put_contents($imgLogFile, date('c') . " - Fell back to local: $target\n", FILE_APPEND);
                        }
                    }
                } else {
                    $uploadDir = $storagePath . '/mall/images/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
                    $target = $uploadDir . uniqid() . '_' . $name;
                    if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                        chmod($target, 0640);
                        $imagesArray[] = str_replace($storagePath, '/storage', $target);
                    }
                }
            }
        }

        $productModel = new Product();
        $data = [
            'seller_id'    => $userId,
            'product_type' => $productType,
            'title'        => $title,
            'slug'         => $slug ?: null,
            'short_description' => $short ?: null,
            'description'  => $description ?: null,
            'price'        => $price,
            'currency'     => $currency,
            'pricing_model' => $pricingModel,
            'pricing_rate' => $pricingRate,
            'markup_rate'  => $markupRate,
            'quantity'     => $qty,
            'category_id'  => $category,
            'images'       => $imagesArray,
            'status'       => 'draft',
            'is_visible'   => 0,
            'weight_kg'    => in_array($productType, ['physical', 'liquid'], true) ? $weightKg : null,
            'length_cm'    => in_array($productType, ['physical', 'liquid'], true) ? $lengthCm : null,
            'width_cm'     => in_array($productType, ['physical', 'liquid'], true) ? $widthCm  : null,
            'height_cm'    => in_array($productType, ['physical', 'liquid'], true) ? $heightCm : null,
            'attributes'   => !empty($attributes) ? $attributes : null,
            'colors'       => $colorsJson,
        ];

        $created = $productModel->create($data);
        if (!$created) {
            http_response_code(500); echo 'Failed to create product'; return;
        }

        // Ensure every seller has a storefront slug once they start listing products.
        try {
            $commerce = new MallCommerceService($this->db);
            $commerce->ensureStorefront($userId);
        } catch (\Throwable $_) {
            // Non-blocking: product listing should still succeed.
        }

        // Push notification to seller confirming product was listed.
        try {
            $pushService = new \Ginto\Services\MallPushService($this->db);
            $pushService->notifyProductListed($userId, $title);
        } catch (\Throwable $_) {
            // Non-blocking: redirect should still proceed.
        }

        header('Location: ' . $this->productsUrl()); exit;
    }

    public function productToggle()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); return; }
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        try {
            $userId    = (int)$_SESSION['user_id'];
            $productId = (int)($_POST['id'] ?? 0);
            $current   = $_POST['current_status'] ?? 'draft';
            $user      = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
            $isAdmin   = in_array($user['role_id'] ?? 0, [1, 2]);

            $product = $this->db->get('products', ['id', 'seller_id', 'title'], ['id' => $productId]);
            if (!$product || (!$isAdmin && (int)$product['seller_id'] !== $userId)) {
                http_response_code(403); echo 'Not authorized'; return;
            }

            $newStatus = ($current === 'published') ? 'draft' : 'published';
            $this->db->update('products', [
                'status'     => $newStatus,
                'is_visible' => ($newStatus === 'published') ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $productId]);

            try {
                $pushService = new \Ginto\Services\MallPushService($this->db);
                $productTitle = (string)($product['title'] ?? 'your product');
                if ($newStatus === 'published') {
                    $pushService->notifyProductPublished($userId, $productTitle, $productId);
                } else {
                    $pushService->notifyProductUnpublished($userId, $productTitle, $productId);
                }
            } catch (\Throwable $_) {
                // Non-blocking: publishing should not fail on notification errors.
            }

            header('Location: ' . $this->productsUrl()); exit;
        } catch (\Throwable $e) {
            $logDir  = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage') . '/logs';
            @mkdir($logDir, 0755, true);
            @file_put_contents($logDir . '/marketplace_errors.log',
                date('c') . ' - productToggle - user=' . ($_SESSION['user_id'] ?? '?') .
                ' product=' . ($_POST['id'] ?? '?') .
                ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
            http_response_code(500);
            echo '<div style="max-width:600px;margin:40px auto;padding:20px;font-family:sans-serif">'
               . '<h2>Failed to update product</h2>'
               . '<p style="color:#ef4444">' . htmlspecialchars($e->getMessage()) . '</p>'
               . '<p><a href="' . $this->productsUrl() . '">← Back to products</a></p>'
               . '</div>';
        }
    }

    public function productDelete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); return; }
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId    = (int)$_SESSION['user_id'];
        $productId = (int)($_POST['id'] ?? 0);
        $user      = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin   = in_array($user['role_id'] ?? 0, [1, 2]);

        $product = $this->db->get('products', ['id', 'seller_id'], ['id' => $productId]);
        if (!$product || (!$isAdmin && (int)$product['seller_id'] !== $userId)) {
            http_response_code(403); echo 'Not authorized'; return;
        }

        $this->db->update('products', [
            'status'     => 'deleted',
            'is_visible' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $productId]);

        header('Location: ' . $this->productsUrl()); exit;
    }

    public function productEdit($id = null)
    {
        if (empty($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $userId    = (int)$_SESSION['user_id'];
        $productId = (int)$id;
        $user      = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin   = in_array($user['role_id'] ?? 0, [1, 2]);

        $product = $this->db->get('products', '*', ['id' => $productId]);
        if (!$product || (!$isAdmin && (int)$product['seller_id'] !== $userId)) {
            http_response_code(403); echo 'Not authorized'; return;
        }

        $categories = $this->db->select('categories', '*') ?: [];
        $csrf = generateCsrfToken();

        // Admins always bypass KYC and TOS — also populate for non-admin editors
        $kycStatus = 'approved';
        $tosAgreed = true;
        if (!$isAdmin) {
            $kycRow    = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
            $kycStatus = is_array($kycRow) ? ($kycRow['status'] ?? 'none') : 'none';
            $userRow   = $this->db->get('users', ['seller_tos_agreed_at'], ['id' => $userId]);
            $tosAgreed = !empty($userRow['seller_tos_agreed_at']);
        }

        $commerce = new MallCommerceService();
        $walletSummary = $commerce->getWalletSummary($userId);
        return $this->view('mall/product_form', [
            'csrf_token'          => $csrf,
            'categories'          => $categories,
            'product'             => $product,
            'editing'             => true,
            'kyc_status'          => $kycStatus,
            'tos_agreed'          => $tosAgreed,
            'is_admin'            => $isAdmin,
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    public function productUpdate($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); return; }
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId    = (int)$_SESSION['user_id'];
        $productId = (int)$id;
        $user      = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin   = in_array($user['role_id'] ?? 0, [1, 2]);

        $existing = $this->db->get('products', ['id', 'seller_id', 'images', 'attributes'], ['id' => $productId]);
        if (!$existing || (!$isAdmin && (int)$existing['seller_id'] !== $userId)) {
            http_response_code(403); echo 'Not authorized'; return;
        }

        $productType = trim((string)($_POST['product_type'] ?? 'physical'));
        if (!in_array($productType, ['physical','liquid','digital','virtual','subscription'], true)) {
            $productType = 'physical';
        }
        $title    = trim($_POST['title'] ?? '');
        $slug     = trim($_POST['slug'] ?? '') ?: null;
        $short    = trim($_POST['short_description'] ?? '') ?: null;
        $desc     = trim($_POST['description'] ?? '') ?: null;
        $price    = floatval($_POST['price'] ?? 0);
        $currency = $_POST['currency'] ?? 'USD';
        $qty      = intval($_POST['quantity'] ?? 0);
        $category = intval($_POST['category_id'] ?? 0) ?: null;
        $pricingModel = trim((string)($_POST['pricing_model'] ?? 'hands_off'));
        $validPricingModels = ['hands_off','active_discovery','full_service','referral','markup','standard'];
        if (!in_array($pricingModel, $validPricingModels, true)) {
            $pricingModel = 'hands_off';
        }
        $planRateMap = ['hands_off'=>12.00,'active_discovery'=>25.00,'full_service'=>35.00,'referral'=>15.00,'markup'=>0.00,'standard'=>10.00];
        $pricingRate = $planRateMap[$pricingModel] ?? 12.00;
        $markupRate  = max(0, min(200, (float)($_POST['markup_rate'] ?? 0)));

        // Available colors (JSON array from tag input)
        $colorsJson = null;
        $rawColors = trim($_POST['colors'] ?? '');
        if ($rawColors !== '' && $rawColors !== '[]') {
            $colorsArr = json_decode($rawColors, true);
            if (is_array($colorsArr)) {
                $colorsArr = array_slice(array_values(array_map('strval', $colorsArr)), 0, 50);
                $colorsJson = !empty($colorsArr) ? json_encode($colorsArr) : null;
            }
        }

        // Shipping dimensions — must include item + all packaging
        $weightKg = isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '' ? max(0.0, (float)$_POST['weight_kg']) : null;
        $lengthCm = isset($_POST['length_cm']) && $_POST['length_cm'] !== '' ? max(0.0, (float)$_POST['length_cm']) : null;
        $widthCm  = isset($_POST['width_cm'])  && $_POST['width_cm']  !== '' ? max(0.0, (float)$_POST['width_cm'])  : null;
        $heightCm = isset($_POST['height_cm']) && $_POST['height_cm'] !== '' ? max(0.0, (float)$_POST['height_cm']) : null;
        $volumeMl = isset($_POST['volume_ml']) && $_POST['volume_ml'] !== '' ? max(0.0, (float)$_POST['volume_ml']) : null;

        $attributes = [];
        if (!empty($existing['attributes'])) {
            $decodedAttrs = json_decode((string)$existing['attributes'], true);
            if (is_array($decodedAttrs)) {
                $attributes = $decodedAttrs;
            }
        }
        if ($productType === 'liquid' && $volumeMl !== null && $volumeMl > 0) {
            $attributes['volume_ml'] = round($volumeMl, 2);
            $attributes['liquid_unit'] = 'mL';
        } else {
            unset($attributes['volume_ml'], $attributes['liquid_unit']);
        }

        // Start from the kept images (user may have removed some via delete buttons)
        $allExisting  = json_decode($existing['images'] ?? '[]', true) ?: [];
        $keepImages   = $_POST['keep_images'] ?? null;
        $imagesArray  = ($keepImages !== null)
            ? array_values(array_filter($allExisting, fn($u) => in_array($u, (array)$keepImages, true)))
            : $allExisting;

        // Append any new image uploads — use B2 when configured, else local storage
        $imgLogFile = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage') . '/logs/marketplace_errors.log';
        if (!empty($_FILES['images'])) {
            $files   = $_FILES['images'];
            $useB2   = \Ginto\Helpers\B2Helper::isEnabled();
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage';
            @file_put_contents($imgLogFile, date('c') . " - productUpdate image upload start - useB2=" . ($useB2 ? 'yes' : 'no') . " files=" . count($files['name']) . "\n", FILE_APPEND);
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    @file_put_contents($imgLogFile, date('c') . " - file[$i] upload error code: " . $files['error'][$i] . "\n", FILE_APPEND);
                    continue;
                }
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($files['name'][$i]));
                if ($useB2) {
                    $fileData   = file_get_contents($files['tmp_name'][$i]);
                    $remotePath = 'mall/images/' . uniqid() . '_' . $name;
                    $mimeType   = $files['type'][$i] ?: 'image/jpeg';
                    try {
                        $url = \Ginto\Helpers\B2Helper::upload($fileData, $remotePath, $mimeType);
                        $imagesArray[] = $url;
                        @file_put_contents($imgLogFile, date('c') . " - B2 upload OK: $url\n", FILE_APPEND);
                    } catch (\Exception $e) {
                        @file_put_contents($imgLogFile, date('c') . " - B2 upload FAILED [$i]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
                        // Fall back to local storage on B2 failure
                        $uploadDir = $storagePath . '/mall/images/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
                        $target = $uploadDir . uniqid() . '_' . $name;
                        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                            chmod($target, 0640);
                            $imagesArray[] = str_replace($storagePath, '/storage', $target);
                            @file_put_contents($imgLogFile, date('c') . " - Fell back to local: $target\n", FILE_APPEND);
                        }
                    }
                } else {
                    $uploadDir = $storagePath . '/mall/images/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
                    $target = $uploadDir . uniqid() . '_' . $name;
                    if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                        chmod($target, 0640);
                        $imagesArray[] = str_replace($storagePath, '/storage', $target);
                    }
                }
            }
        }

        $this->db->update('products', [
            'product_type'      => $productType,
            'title'             => $title,
            'slug'              => $slug,
            'short_description' => $short,
            'description'       => $desc,
            'price'             => $price,
            'currency'          => $currency,
            'quantity'          => $qty,
            'category_id'       => $category,
            'pricing_model'     => $pricingModel,
            'pricing_rate'      => $pricingRate,
            'markup_rate'       => $markupRate,
            'images'            => json_encode($imagesArray),
            'weight_kg'         => in_array($productType, ['physical', 'liquid'], true) ? $weightKg : null,
            'length_cm'         => in_array($productType, ['physical', 'liquid'], true) ? $lengthCm : null,
            'width_cm'          => in_array($productType, ['physical', 'liquid'], true) ? $widthCm  : null,
            'height_cm'         => in_array($productType, ['physical', 'liquid'], true) ? $heightCm : null,
            'attributes'        => !empty($attributes) ? json_encode($attributes) : null,
            'colors'            => $colorsJson,
            'updated_at'        => date('Y-m-d H:i:s'),
        ], ['id' => $productId]);

        header('Location: ' . $this->productsUrl()); exit;
    }
}
