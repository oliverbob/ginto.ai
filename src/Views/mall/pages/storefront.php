<?php
$storefront = $storefront ?? [];
$seller = $storefront['seller'] ?? [];
?>
<section style="max-width:1400px;margin:28px auto 0;padding:0 18px;">
    <div style="border:1px solid var(--border);background:linear-gradient(135deg, rgba(48,77,163,0.14), rgba(72,183,255,0.08) 50%, rgba(255,214,102,0.16));border-radius:28px;padding:28px 28px 24px;position:relative;overflow:hidden;">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:18px;position:relative;z-index:1;">
            <div style="max-width:760px;display:flex;align-items:center;gap:18px;">
                <div style="width:74px;height:74px;border-radius:22px;background:rgba(255,255,255,0.86);border:1px solid rgba(255,255,255,0.65);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;color:#234;box-shadow:0 12px 24px rgba(0,0,0,0.08);">
                    <?= htmlspecialchars(strtoupper(substr((string)($storefront['display_name'] ?? 'S'), 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div>
                    <div style="font-size:0.78rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);font-weight:700;">Official Seller Storefront</div>
                    <h1 style="font-size:2rem;line-height:1.1;margin:6px 0 8px;font-weight:800;"><?= htmlspecialchars($storefront['display_name'] ?? 'Storefront', ENT_QUOTES, 'UTF-8') ?></h1>
                    <p style="font-size:0.95rem;line-height:1.7;color:var(--muted);margin:0;max-width:680px;"><?= htmlspecialchars($storefront['description'] ?? 'Browse products sold directly by this merchant on Ginto Mall.', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="/marketplace" class="btn btn-secondary">Back to Mall</a>
                <?php if (!empty($seller['username'])): ?>
                <a href="/user/profile/<?= htmlspecialchars($seller['username'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Seller Profile</a>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;position:relative;z-index:1;">
            <div style="padding:8px 12px;border-radius:999px;background:rgba(255,255,255,0.72);border:1px solid rgba(255,255,255,0.66);font-size:0.82rem;font-weight:600;">
                Slug: /mall/<?= htmlspecialchars($storefront['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="padding:8px 12px;border-radius:999px;background:rgba(255,255,255,0.72);border:1px solid rgba(255,255,255,0.66);font-size:0.82rem;font-weight:600;">
                Seller: <?= htmlspecialchars($seller['fullname'] ?? ($seller['username'] ?? 'Unknown seller'), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <div style="position:absolute;right:-36px;top:-36px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle, rgba(255,255,255,0.7), rgba(255,255,255,0));"></div>
    </div>
</section>

<?php include __DIR__ . '/home.php'; ?>