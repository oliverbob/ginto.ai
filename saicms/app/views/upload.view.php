<?php
// This view file assumes it's being included by Core\View::render()
// which extracts the $data array passed from UploadController@upload.
// It also assumes session_start() has been called before this view is rendered.

// Helper function (can be moved to a global helper file)
if (!function_exists('formatBytes')) {
    function formatBytes($size, $precision = 2) {
        if ($size <= 0) return '0 B';
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
}

// Access variables from the $data array passed by the controller
$pageTitle = $pageTitle ?? 'Cloud File Manager'; // Default title
$fileCdnBaseUrl = $fileCdnBaseUrl ?? '';         // From controller
$uploadedFiles = $uploadedFiles ?? [];           // From controller
$currentUserId = $_SESSION['user_id'] ?? null;   // Get current user for UI logic

// Flash messages (already prepared in $data by controller, or use session directly if preferred)
$successFlashMessage = $successFlashMessage ?? null;
$errorFlashMessage = $errorFlashMessage ?? null;
$infoFlashMessage = $infoFlashMessage ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #f8f9fa; line-height: 1.6; color: #333; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 20px rgba(0,0,0,0.08); margin-bottom: 20px; }
        h2, h3 { color: #2c3e50; margin-bottom: 20px; font-weight: 600; }
        h2 { border-bottom: 2px solid #3498db; padding-bottom: 10px; font-size: 24px; }
        h3 { font-size: 20px; }
        .upload-form-section { border-bottom: 1px solid #e9ecef; padding-bottom: 30px; margin-bottom: 30px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input[type="file"] { display: block; margin-top: 5px; margin-bottom:15px; padding: 10px; border: 2px dashed #ced4da; border-radius: 6px; width: calc(100% - 24px); box-sizing: border-box; background: #f8f9fa; cursor: pointer; }
        input[type="file"]:hover { border-color: #3498db; }
        input[type="text"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; margin-bottom: 10px; font-size: 14px; }
        textarea { min-height: 80px; resize: vertical; }
        button, .btn { background: linear-gradient(145deg, #3498db, #2980b9); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 500; transition: all 0.2s ease-in-out; text-decoration: none; display: inline-block; text-align: center; line-height: normal; }
        button:hover, .btn:hover { background: linear-gradient(145deg, #2980b9, #21618c); box-shadow: 0 2px 8px rgba(0,0,0,0.1); transform: translateY(-1px); }
        .btn-danger { background: linear-gradient(145deg, #e74c3c, #c0392b); }
        .btn-danger:hover { background: linear-gradient(145deg, #c0392b, #a93226); }
        .btn-edit { background: linear-gradient(145deg, #f39c12, #e67e22); }
        .btn-edit:hover { background: linear-gradient(145deg, #e67e22, #d35400); }
        .flash-message { padding: 15px; border-radius: 6px; margin: 20px 0; color: white; font-weight: 500; }
        .flash-success { background: linear-gradient(145deg, #2ecc71, #27ae60); }
        .flash-error { background: linear-gradient(145deg, #e74c3c, #c0392b); }
        .flash-info { background: linear-gradient(145deg, #3498db, #2980b9); }
        .config-note { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .file-list { margin-top: 30px; }
        .file-item { display: grid; grid-template-columns: 1fr auto; gap: 15px; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 15px; background: #fff; transition: box-shadow 0.2s ease-in-out; }
        .file-item:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.07); }
        .file-info { display: flex; flex-direction: column; gap: 6px; }
        .file-name { font-weight: 600; color: #2c3e50; font-size: 17px; word-break: break-all; }
        .file-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px 15px; font-size: 13px; color: #6c757d; }
        .file-meta span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; justify-content: center; }
        .file-actions .btn { padding: 6px 12px; font-size: 13px; min-width: 90px; }
        .no-files { text-align: center; color: #6c757d; padding: 40px 20px; background: #f8f9fa; border: 1px dashed #ced4da; border-radius: 8px; font-size: 16px; }
        .category-badge, .visibility-badge { display: inline-block; padding: 3px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; margin-left: 8px; vertical-align: middle; }
        .category-image { background: #d4edda; color: #155724; } .category-video { background: #fff3cd; color: #856404; } .category-audio { background: #f8d7da; color: #721c24; } .category-document { background: #d1ecf1; color: #0c5460; } .category-archive { background: #e2e3e5; color: #383d41; } .category-code { background: #d6d8d9; color: #1b1e21; } .category-font { background: #cce5ff; color: #004085; } .category-other { background: #fdfdfe; color: #545b62; border: 1px solid #ddd;}
        .visibility-public { background: #c8e6c9; color: #1b5e20; } .visibility-unlisted { background: #fff3e0; color: #e65100; } .visibility-private { background: #ffcdd2; color: #b71c1c; }
        .edit-form { display: none; grid-column: 1 / -1; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; }
        .edit-form.active { display: block; }
        .form-group { margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-row.full { grid-template-columns: 1fr; }
        @media (max-width: 768px) {
            .file-item { grid-template-columns: 1fr; }
            .file-actions { flex-direction: row; align-items: center; justify-content: flex-start; margin-top: 10px; }
            .file-meta { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>☁️ <?= htmlspecialchars($pageTitle) ?></h2>

        <?php if (isset($_ENV['B2_ACCOUNT_ID']) && empty($_ENV['B2_ACCOUNT_ID'])): // Simple check, improve as needed ?>
        <div class="config-note">
            <strong>⚠️ Configuration Notice:</strong> Core service details (like B2 Account ID) appear to be missing from your environment setup. Uploads may fail. Please check server configuration.
        </div>
        <?php endif; ?>

        <?php if ($successFlashMessage): ?>
            <div class="flash-message flash-success">✅ <?= htmlspecialchars($successFlashMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorFlashMessage): ?>
            <div class="flash-message flash-error">❌ <strong>Error:</strong> <?= nl2br(htmlspecialchars($errorFlashMessage)) ?></div>
        <?php endif; ?>
        <?php if ($infoFlashMessage): ?>
            <div class="flash-message flash-info">ℹ️ <?= htmlspecialchars($infoFlashMessage) ?></div>
        <?php endif; ?>

        <?php if ($currentUserId): ?>
            <div class="upload-form-section">
                <h3>📤 Upload New File</h3>
                <form action="/upload" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fileInput">Choose file to upload:</label>
                        <input type="file" name="file" id="fileInput" required>
                    </div>
                    <button type="submit">Upload to Cloud</button>
                </form>
            </div>
        <?php else: ?>
            <div class="flash-message flash-info">
                Please <a href="/login" style="color:white; text-decoration:underline;">log in</a> to upload files.
            </div>
        <?php endif; ?>

        <div class="file-list">
            <h3>📋 Cloud Files (<?= count($uploadedFiles) ?>)</h3>

            <?php if (empty($uploadedFiles)): ?>
                <div class="no-files">
                    <?php if ($currentUserId): ?>
                        📁 No files uploaded yet by you, or matching current filters.
                    <?php else: ?>
                        📁 No public files available, or you need to log in to see more.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($uploadedFiles as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-name">
                                <?= htmlspecialchars($file['title'] ?: $file['original_filename']) ?>
                                <span class="category-badge category-<?= htmlspecialchars($file['file_category']) ?>">
                                    <?= strtoupper(htmlspecialchars($file['file_category'])) ?>
                                </span>
                                <span class="visibility-badge visibility-<?= htmlspecialchars($file['visibility']) ?>">
                                    <?= strtoupper(htmlspecialchars($file['visibility'])) ?>
                                </span>
                            </div>
                            <?php if (!empty($file['description'])): ?>
                                <p style="color: #555; font-size: 14px; margin: 5px 0;"><?= nl2br(htmlspecialchars($file['description'])) ?></p>
                            <?php endif; ?>
                            <?php
                            $tagsDisplay = '';
                            if (!empty($file['tags'])) {
                                $tagsArray = json_decode($file['tags'], true);
                                if (is_array($tagsArray) && !empty($tagsArray)) {
                                    $tagsDisplay = htmlspecialchars(implode(', ', $tagsArray));
                                }
                            }
                            ?>
                            <?php if ($tagsDisplay): ?>
                                <div style="color: #007bff; font-size: 12px;">
                                    <strong>Tags:</strong> <?= $tagsDisplay ?>
                                </div>
                            <?php endif; ?>
                            <div class="file-meta">
                                <span><strong>Size:</strong> <?= htmlspecialchars(formatBytes($file['size_bytes'])) ?></span>
                                <span><strong>Original Name:</strong> <?= htmlspecialchars($file['original_filename']) ?></span>
                                <?php if ($file['file_path_in_provider'] !== $file['original_filename']): ?>
                                <span><strong>Server Name:</strong> <span title="<?= htmlspecialchars($file['file_path_in_provider']) ?>"><?= htmlspecialchars(substr($file['file_path_in_provider'], 0, 15)) ?>...</span></span>
                                <?php endif; ?>
                                <span><strong>Type:</strong> <?= htmlspecialchars($file['content_type']) ?></span>
                                <span><strong>Uploaded:</strong> <?= htmlspecialchars(date('M j, Y, g:i A T', strtotime($file['created_at']))) ?></span>
                            </div>
                        </div>
                        <div class="file-actions">
                            <?php
                            $viewLink = '#';
                            if (!empty($fileCdnBaseUrl) && $fileCdnBaseUrl !== '/' && !empty($file['file_path_in_provider'])) {
                                $viewLink = $fileCdnBaseUrl . rawurlencode($file['file_path_in_provider']);
                            }
                            ?>
                            <a href="<?= htmlspecialchars($viewLink) ?>" target="_blank" class="btn file-link">📥 View/Download</a>
                            <?php if ($currentUserId && $currentUserId == ($file['user_id'] ?? null)): ?>
                                <a href="#" class="btn btn-edit" onclick="toggleEdit(<?= $file['id'] ?>); return false;">✏️ Edit</a>
                                <a href="/upload?delete_id=<?= $file['id'] ?>" class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete the record for \'<?= htmlspecialchars(addslashes($file['original_filename'])) ?>\'?\nThis action only removes the database record in this example, not the actual cloud file.')">🗑️ Delete Record</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($currentUserId && $currentUserId == ($file['user_id'] ?? null)): ?>
                        <div class="edit-form" id="edit-<?= $file['id'] ?>">
                            <h4>Edit Metadata for: <?= htmlspecialchars($file['original_filename']) ?></h4>
                            <form action="/upload" method="post">
                                <input type="hidden" name="update_metadata" value="1">
                                <input type="hidden" name="file_id" value="<?= $file['id'] ?>">

                                <div class="form-group">
                                    <label for="title-<?= $file['id'] ?>">Title:</label>
                                    <input type="text" id="title-<?= $file['id'] ?>" name="title" value="<?= htmlspecialchars($file['title'] ?? '') ?>" placeholder="Friendly file title">
                                </div>
                                <div class="form-group">
                                    <label for="description-<?= $file['id'] ?>">Description:</label>
                                    <textarea id="description-<?= $file['id'] ?>" name="description" rows="3" placeholder="File description"><?= htmlspecialchars($file['description'] ?? '') ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tags-<?= $file['id'] ?>">Tags (comma-separated):</label>
                                        <input type="text" id="tags-<?= $file['id'] ?>" name="tags" value="<?= ($file['tags'] && ($tagsDecoded = json_decode($file['tags'], true)) && is_array($tagsDecoded)) ? htmlspecialchars(implode(', ', $tagsDecoded)) : '' ?>" placeholder="tag1, tag2, image">
                                    </div>
                                    <div class="form-group">
                                        <label for="visibility-<?= $file['id'] ?>">Visibility:</label>
                                        <select id="visibility-<?= $file['id'] ?>" name="visibility">
                                            <option value="public" <?= ($file['visibility'] ?? 'private') === 'public' ? 'selected' : '' ?>>Public</option>
                                            <option value="unlisted" <?= ($file['visibility'] ?? 'private') === 'unlisted' ? 'selected' : '' ?>>Unlisted (Direct link only)</option>
                                            <option value="private" <?= ($file['visibility'] ?? 'private') === 'private' ? 'selected' : '' ?>>Private (Requires login/permission)</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit">💾 Update Metadata</button>
                                <button type="button" class="btn btn-danger" style="margin-left:10px;" onclick="toggleEdit(<?= $file['id'] ?>); return false;">❌ Cancel</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleEdit(fileId) {
            const allEditForms = document.querySelectorAll('.edit-form');
            allEditForms.forEach(form => {
                if (form.id !== 'edit-' + fileId) {
                    form.classList.remove('active');
                }
            });
            const currentEditForm = document.getElementById('edit-' + fileId);
            if (currentEditForm) {
                currentEditForm.classList.toggle('active');
            }
        }
    </script>
</body>
</html>