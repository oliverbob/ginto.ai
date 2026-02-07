<?php
/** @var array $kyc */
/** @var string $csrf_token */
?>
<?php
$title = $title ?? 'Seller KYC';
$isLoggedIn = $isLoggedIn ?? (!empty($_SESSION['user_id']));
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="max-w-3xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">Seller KYC</h2>

    <?php if (!empty($kyc) && isset($kyc['status'])): ?>
        <div class="mb-4 p-3 rounded border bg-gray-50 dark:bg-gray-900">
            <p><strong>Status:</strong> <?= htmlspecialchars($kyc['status']) ?></p>
            <p><strong>Submitted:</strong> <?= htmlspecialchars($kyc['submitted_at'] ?? '') ?></p>
            <?php if (!empty($kyc['documents'])): $docs = json_decode($kyc['documents'], true); ?>
                <p class="mt-2">Documents:</p>
                <ul class="list-disc ml-5">
                    <?php foreach ($docs as $d): ?>
                        <li><a href="<?= htmlspecialchars($d) ?>" target="_blank" class="text-blue-600">View</a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($kyc['review_notes'])): ?>
                <p class="mt-2"><strong>Notes:</strong> <?= htmlspecialchars($kyc['review_notes']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/marketplace/sellers/kyc/submit" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm">First Name</label>
                <input type="text" name="first_name" class="w-full px-3 py-2 rounded border" required>
            </div>
            <div>
                <label class="block text-sm">Last Name</label>
                <input type="text" name="last_name" class="w-full px-3 py-2 rounded border" required>
            </div>
            <div>
                <label class="block text-sm">Date of Birth</label>
                <input type="date" name="dob" class="w-full px-3 py-2 rounded border">
            </div>
            <div>
                <label class="block text-sm">Country</label>
                <input type="text" name="country" class="w-full px-3 py-2 rounded border">
            </div>
            <div>
                <label class="block text-sm">Government ID / Identifier</label>
                <input type="text" name="identifier" class="w-full px-3 py-2 rounded border">
            </div>
            <div>
                <label class="block text-sm">Upload Documents (ID front/back, proof of address) — JPG/PNG/PDF</label>
                <input type="file" name="documents[]" multiple accept="image/*,.pdf" class="mt-2">
            </div>
            <div class="pt-4">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Submit KYC</button>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>