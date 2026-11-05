<?php
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    echo "ERROR: .env not found at $envPath\n";
    exit(1);
}
$contents = file_get_contents($envPath);
if ($contents === false) { echo "ERROR: cannot read .env\n"; exit(1); }

$keys = [
    'B2_ACCOUNT_ID' => '',
    'B2_APP_KEY' => '',
    'B2_BUCKET_ID' => '',
    'B2_BUCKET_NAME' => '',
    'FILE_CDN_BASE_URL' => ''
];

$append = "\n# Backblaze B2 / CDN Configuration\n";
$added = false;
foreach ($keys as $k => $v) {
    if (strpos($contents, $k . '=') === false) {
        $append .= "$k=$v\n";
        $added = true;
    }
}

if ($added) {
    if (file_put_contents($envPath, $append, FILE_APPEND) === false) {
        echo "ERROR: failed to append to .env\n";
        exit(1);
    }
    echo "Appended B2 keys to .env\n";
} else {
    echo ".env already contains B2 keys (no changes made)\n";
}
