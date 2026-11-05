<?php
$path = __DIR__ . '/install.php';
$bak = $path . '.bak';
copy($path, $bak);
$contents = file_get_contents($path);
if ($contents === false) {
    echo "ERROR: cannot read $path\n";
    exit(1);
}
$pattern = '/function createConfigurationFiles\(\$data\)\s*\{[\s\S]*?return \'Configuration files created successfully\';\s*\}/m';
$replacement = <<<'PHP'
function createConfigurationFiles($data) {
    $envContent = generateEnvContent($data);
    $envPath = '../.env';

    if (file_put_contents($envPath, $envContent) === false) {
        throw new Exception('Failed to create .env file');
    }

    // Ensure Backblaze B2 keys are present in .env (append if missing)
    $existing = file_exists($envPath) ? file_get_contents($envPath) : '';
    $b2_account = $data['b2_account_id'] ?? '';
    $b2_appkey = $data['b2_app_key'] ?? '';
    $b2_bucket_id = $data['b2_bucket_id'] ?? '';
    $b2_bucket_name = $data['b2_bucket_name'] ?? '';
    $b2_url = $data['file_cdn_base_url'] ?? '';

    if (strpos($existing, 'B2_ACCOUNT_ID=') === false) {
        $b2block = "\n# Backblaze B2 / CDN Configuration\n";
        $b2block .= "B2_ACCOUNT_ID={$b2_account}\n";
        $b2block .= "B2_APP_KEY={$b2_appkey}\n";
        $b2block .= "B2_BUCKET_ID={$b2_bucket_id}\n";
        $b2block .= "B2_BUCKET_NAME={$b2_bucket_name}\n";
        $b2block .= "FILE_CDN_BASE_URL=\"{$b2_url}\"\n";
        file_put_contents($envPath, $b2block, FILE_APPEND);
    }

    return 'Configuration files created successfully';
}
PHP;

$new = preg_replace($pattern, $replacement, $contents, 1);
if ($new === null) {
    echo "ERROR: preg_replace failed\n";
    exit(1);
}
if ($new === $contents) {
    echo "No changes made: pattern not found or already patched\n";
    exit(0);
}
if (file_put_contents($path, $new) === false) {
    echo "ERROR: cannot write $path\n";
    exit(1);
}
echo "OK: patched install.php (backup at $bak)\n";
