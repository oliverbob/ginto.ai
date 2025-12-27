<?php
$path = __DIR__ . '/install.php';
$contents = file_get_contents($path);
if ($contents === false) {
    echo "ERROR: cannot read $path\n";
    exit(1);
}
$needle = 'return $env;';
if (strpos($contents, $needle) === false) {
    echo "ERROR: pattern not found\n";
    exit(1);
}
$staticBlock = "\n    // Backblaze B2 / CDN configuration (optional)\n    // Use provided installer values or sensible defaults\n    \$env .= \"\\n# Backblaze B2 / CDN Configuration\\n\";\n    \$env .= \"B2_ACCOUNT_ID=\\n\";\n    \$env .= \"B2_APP_KEY=\\n\";\n    \$env .= \"B2_BUCKET_ID=\\n\";\n    \$env .= \"B2_BUCKET_NAME=\\n\";\n    \$env .= \"FILE_CDN_BASE_URL=\\n\";\n";
$new = preg_replace('/return \\\$env;/', $staticBlock . "    return $env;", $contents, 1);
if ($new === null) {
    echo "ERROR: preg_replace failed\n";
    exit(1);
}
if (file_put_contents($path, $new) === false) {
    echo "ERROR: cannot write $path\n";
    exit(1);
}
echo "OK: patched install.php\n";
?>
