<?php
/** @var array $products */
/** @var array $drafts */
/** @var string $csrf_token */
?>
<?php
$title = $title ?? 'My Products';
$isLoggedIn = $isLoggedIn ?? (!empty($_SESSION['user_id']));
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-semibold">My Products</h2>
            <div class="text-sm text-gray-400">Manage your products, drafts, and publish status.</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/marketplace/sellers/products/new" class="px-4 py-2 bg-green-600 text-white rounded inline-flex items-center gap-2"><i class="fas fa-plus"></i> Add Product</a>
            <a href="/marketplace/sellers/kyc" class="px-3 py-2 bg-gray-700 text-white rounded inline-flex items-center gap-2"><i class="fas fa-id-card"></i> KYC</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Sidebar -->
        <aside class="col-span-1 bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
            <h3 class="font-semibold mb-2">Seller Dashboard</h3>
            <p class="text-sm text-gray-500 mb-3">KYC: <strong><?= htmlspecialchars($kyc_status ?? 'pending') ?></strong></p>
            <p class="text-sm text-gray-500 mb-3">Subscription: <strong><?= htmlspecialchars($subscription_status ?? 'inactive') ?></strong></p>
            <?php if (!empty($next_billing_at)): ?>
                <p class="text-sm text-gray-400">Next billing: <?= htmlspecialchars($next_billing_at) ?></p>
            <?php endif; ?>

            <div class="mt-4">
                <a class="block px-3 py-2 rounded hover:bg-gray-100" href="/marketplace/sellers/products/new"><i class="fas fa-plus-circle mr-2"></i> New Product</a>
                <a class="block px-3 py-2 rounded hover:bg-gray-100" href="/marketplace/sellers/kyc"><i class="fas fa-id-card mr-2"></i> View KYC</a>
                <a class="block px-3 py-2 rounded hover:bg-gray-100" href="/marketplace/sellers/subscription"><i class="fas fa-credit-card mr-2"></i> Subscription</a>
            </div>
        </aside>

        <!-- Product Grid -->
        <section class="col-span-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($products as $p): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow flex flex-col">
                        <div class="flex items-start gap-4">
                            <div class="w-1/3">
                                <?php if (!empty($p['images'])): $imgs = json_decode($p['images'], true); if (!empty($imgs)): ?>
                                    <img src="<?= htmlspecialchars($imgs[0]) ?>" alt="" class="w-full h-36 object-cover rounded">
                                <?php else: ?>
                                    <div class="w-full h-36 bg-gray-100 rounded"></div>
                                <?php endif; endif; ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold mb-1"><?= htmlspecialchars($p['title']) ?></h3>
                                <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($p['short_description'] ?? '') ?></p>
                                <div class="flex items-center gap-3">
                                    <div class="font-semibold"><?= htmlspecialchars($p['currency']) ?> <?= htmlspecialchars(number_format($p['price'],2)) ?></div>
                                    <div class="text-sm text-gray-400">Qty: <?= htmlspecialchars($p['quantity'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <a href="/marketplace/sellers/products/edit/<?= htmlspecialchars($p['id']) ?>" class="px-3 py-1 bg-blue-600 text-white rounded text-sm inline-flex items-center gap-2"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" action="/marketplace/sellers/products/publish" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded text-sm inline-flex items-center gap-2"><i class="fas fa-upload"></i> Publish</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($drafts)): ?>
                <h3 class="mt-8 text-lg font-semibold">Drafts</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <?php foreach ($drafts as $d): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <h3 class="font-bold mb-2"><?= htmlspecialchars($d['title']) ?></h3>
                            <p class="mt-2 text-sm text-gray-600"><?= htmlspecialchars($d['short_description'] ?? '') ?></p>
                            <p class="mt-2 text-sm text-gray-500">Status: <?= htmlspecialchars($d['status']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>