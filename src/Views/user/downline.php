<?php
/** @var string $title */
/** @var array $referrals */
/** @var int $current_user_id */

// Use the shared layout pieces for consistent header, sidebar and theming
include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';

// set page title used by `topbar.php`
$title = $title ?? 'My Direct Referrals';
include __DIR__ . '/../layout/topbar.php';
?>

<!-- Scoped theme helpers for this view -->
<style>
/* Themed helper classes used by this view. Provide clear dark-mode
    overrides so the right-pane cards, borders and text read well.
*/
.themed-card { background: #ffffff; color: #111827; }
.themed-border { border: 1px solid #e5e7eb; }
.themed-text { color: #111827; }
.themed-text-secondary { color: #6b7280; }
.themed-hover:hover { background: #f8fafc; }

/* Dark mode overrides */
html.dark .themed-card { background: #111827; background: linear-gradient(180deg,#1f2937 0,#111827 100%); color: #E6EEF8; }
html.dark .themed-border { border-color: rgba(255,255,255,0.06); }
html.dark .themed-text { color: #E6EEF8; }
html.dark .themed-text-secondary { color: #9CA3AF; }
html.dark .themed-hover:hover { background: rgba(255,255,255,0.02); }

/* Slightly lift the highlighted item for clearer contrast */
.themed-hover { transition: background .12s ease, transform .12s ease; }
html.dark .themed-hover { background: transparent; }
</style>
    <div class="p-6">
        <div class="bg-white themed-card rounded-lg shadow border themed-border p-6">
            <!-- Title is already rendered in the topbar; removed duplicate heading here -->
            <p class="text-sm themed-text-secondary mb-2">Users registered directly under your referral ID: <span class="font-semibold themed-text"><?= htmlspecialchars($current_user_id) ?></span></p>

            <?php $directCount = isset($direct_referral_count) ? intval($direct_referral_count) : count($referrals); ?>
            <?php $totalCount = isset($total_downline_count) ? intval($total_downline_count) : $directCount; ?>
            <p class="text-sm mb-4">Summary: <span class="font-semibold themed-text"><?= $directCount ?></span> direct referrals — <span class="font-semibold themed-text"><?= $totalCount ?></span> total downlines</p>

            <?php if (empty($referrals)): ?>
                <div class="p-4 bg-yellow-50 text-yellow-800 rounded-lg border border-yellow-100">
                    You currently have no direct referrals. Share your referral link to grow your downline!
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($referrals as $user): ?>
                        <div class="flex justify-between items-center p-4 bg-transparent border rounded-lg themed-border themed-hover transition duration-150">
                            <div>
                                <p class="text-lg font-semibold themed-text">
                                    <?= htmlspecialchars($user['fullname'] ?? $user['username']) ?>
                                </p>
                                <p class="text-sm themed-text-secondary">
                                    Username: <?= htmlspecialchars($user['username']) ?> &nbsp;|&nbsp;
                                    Ginto Level: <span class="font-medium text-green-600"><?= htmlspecialchars($user['ginto_level']) ?></span>
                                </p>
                            </div>
                            <div class="text-right themed-text-secondary">
                                <div class="text-xs">Joined</div>
                                <div class="text-sm themed-text">
                                    <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="mt-6 text-md font-semibold themed-text border-t themed-border pt-4">Total Direct Referrals: <?= count($referrals) ?></p>
            <?php endif; ?>

            <div class="mt-8">
                <a href="/dashboard" class="text-blue-600 hover:text-blue-800 font-medium">&larr; Go back to Dashboard</a>
            </div>
        </div>
    </div>

<?php include __DIR__ . '/../layout/footer.php'; ?>