<?php
$path = __DIR__ . '/index.html';
$contents = file_get_contents($path);
if ($contents === false) { echo "ERR read"; exit(1); }

// 1) Insert B2 fields into Step 3 (after Timezone select closing div)
$needle = "</div>\n                            <div class=\"mt-8 flex justify-between\">\n                                <button type=\"button\" class=\"px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600\" onclick=\"previousStep(2)\">\n                                    Back\n                                </button>\n                                <button type=\"button\" class=\"px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600\" onclick=\"nextStep(4)\">\n                                    Next: Admin Account\n                                </button>\n                            </div>\n                        </div>\n                    </div>\n\n                    <!-- Step 4: Admin Account -->";

// We will instead insert B2 block before the mt-8 div (the previous code uses the exact needle).
$insert = "\n                                <div class=\"col-span-full\">\n                                    <h3 class=\"text-lg font-semibold text-gray-800 dark:text-white mb-2\">Cloud Storage (Backblaze B2) - optional</h3>\n                                    <div class=\"grid grid-cols-1 md:grid-cols-2 gap-4\">\n                                        <div>\n                                            <label class=\"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2\">B2 Account ID</label>\n                                            <input type=\"text\" name=\"b2_account_id\" value=\"\" class=\"w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white\" placeholder=\"B2 Account ID\">\n                                        </div>\n                                        <div>\n                                            <label class=\"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2\">B2 App Key</label>\n                                            <input type=\"text\" name=\"b2_app_key\" value=\"\" class=\"w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white\" placeholder=\"B2 App Key\">\n                                        </div>\n                                        <div>\n                                            <label class=\"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2\">B2 Bucket ID</label>\n                                            <input type=\"text\" name=\"b2_bucket_id\" value=\"\" class=\"w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white\" placeholder=\"B2 Bucket ID\">\n                                        </div>\n                                        <div>\n                                            <label class=\"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2\">B2 Bucket Name</label>\n                                            <input type=\"text\" name=\"b2_bucket_name\" value=\"\" class=\"w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white\" placeholder=\"B2 Bucket Name\">\n                                        </div>\n                                        <div class=\"col-span-full\">\n                                            <label class=\"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2\">CDN Base URL</label>\n                                            <input type=\"url\" name=\"file_cdn_base_url\" value=\"\" class=\"w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white\" placeholder=\"https://...\">\n                                        </div>\n                                    </div>\n                                </div>\n";

// Find a safer insertion point: after the Timezone select closing </div> that is part of the Site Configuration grid.
$pattern = '/(<\/div>\s*<\/div>\s*<\/div>\s*<div class=\"mt-8 flex justify-between\">)/s';
if (preg_match($pattern, $contents, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1];
    // Insert before the mt-8 div
    $before = substr($contents, 0, $pos);
    $after = substr($contents, $pos);
    $newContents = $before . $insert . $after;
    file_put_contents($path, $newContents);
    echo "OK: injected B2 UI block into install/index.html\n";
} else {
    echo "ERR: insertion point not found\n";
}

// 2) Add JS population for existing .env values and defaults in two places
// We'll append small JS snippets near existing loadExistingEnvValues() and startInstallation() default sections.
$jsPath = $path;
$js = file_get_contents($jsPath);
if ($js === false) { echo "ERR2"; exit(1); }

// Insert into loadExistingEnvValues: after the existing DB and APP_URL population logic
$search = "                if (envValues.DB_FILE) {\n                    const dbFileField = document.querySelector('[name=\"db_file\"]');\n                    if (dbFileField) dbFileField.value = envValues.DB_FILE;\n                }\n                \n            } catch (error) {";
if (strpos($js, $search) !== false) {
    $add = "\n                // Populate B2 / CDN values if present\n                if (envValues.B2_ACCOUNT_ID) { const f = document.querySelector('[name=\'b2_account_id\']'); if (f) f.value = envValues.B2_ACCOUNT_ID; }\n                if (envValues.B2_APP_KEY) { const f2 = document.querySelector('[name=\'b2_app_key\']'); if (f2) f2.value = envValues.B2_APP_KEY; }\n                if (envValues.B2_BUCKET_ID) { const f3 = document.querySelector('[name=\'b2_bucket_id\']'); if (f3) f3.value = envValues.B2_BUCKET_ID; }\n                if (envValues.B2_BUCKET_NAME) { const f4 = document.querySelector('[name=\'b2_bucket_name\']'); if (f4) f4.value = envValues.B2_BUCKET_NAME; }\n                if (envValues.FILE_CDN_BASE_URL) { const f5 = document.querySelector('[name=\'file_cdn_base_url\']'); if (f5) f5.value = envValues.FILE_CDN_BASE_URL; }\n";
    $js = str_replace($search, $search . $add, $js);
}

// Insert defaults in startInstallation: look for the block that sets defaults for site and db
$search2 = "            if (!formData.get('db_file')) formData.set('db_file', 'database.sqlite');\n            if (!formData.get('admin_email')) formData.set('admin_email', 'admin@example.com');\n            if (!formData.get('admin_username')) formData.set('admin_username', 'admin');\n";
if (strpos($js, $search2) !== false) {
    $add2 = "            // B2 values are optional - no defaults set\n";
    $js = str_replace($search2, $search2 . $add2, $js);
}

file_put_contents($jsPath, $js);
echo "OK: injected JS snippets\n";
