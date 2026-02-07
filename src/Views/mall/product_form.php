<?php
/** @var array $categories */
/** @var string $csrf_token */
?>
<?php
$title = $title ?? 'Create Product';
$isLoggedIn = $isLoggedIn ?? (!empty($_SESSION['user_id']));
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="max-w-3xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">Create Product</h2>
    <form method="POST" action="/marketplace/sellers/products/create" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm">Title</label>
                <input type="text" name="title" class="w-full px-3 py-2 rounded border" required>
            </div>
            <div>
                <label class="block text-sm">Slug (optional)</label>
                <input type="text" name="slug" class="w-full px-3 py-2 rounded border">
            </div>
            <div>
                <label class="block text-sm">Short Description</label>
                <textarea name="short_description" class="w-full px-3 py-2 rounded border" rows="3"></textarea>
            </div>
            <div>
                <label class="block text-sm">Description</label>
                <textarea name="description" class="w-full px-3 py-2 rounded border" rows="6"></textarea>
            </div>
            <div>
                <label class="block text-sm">Price</label>
                <div class="flex items-center gap-3">
                    <input type="number" step="0.01" name="price" class="px-3 py-2 rounded border w-40" required>
                    <select name="currency" class="px-3 py-2 rounded border">
                        <option value="USD">USD</option>
                        <option value="PHP">PHP</option>
                        <option value="NGN">NGN</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm">Category</label>
                <select name="category_id" class="w-full px-3 py-2 rounded border">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm">Images (multiple)</label>
                <input type="file" name="images[]" multiple accept="image/*" class="mt-2">
            </div>
            <div class="pt-4">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Create Product</button>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>