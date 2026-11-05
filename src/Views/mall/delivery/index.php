<?php
$title = $title ?? 'Delivery Tracking — Ginto Mall';
$isLoggedIn = !empty($_SESSION['user_id']);
$myShipments = $my_shipments ?? [];
$isSeller = $is_seller ?? false;
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/../parts/head.php'; ?>
<body>
<?php include __DIR__ . '/../parts/header.php'; ?>
<div class="mall-content-push">
    <main style="max-width:860px;margin:32px auto 72px;padding:0 18px;">

            <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:8px;">📦 Delivery Tracking</h1>
            <p style="color:var(--muted);margin-bottom:28px;">Track your Ginto Mall shipments in real time.</p>

            <!-- Track by token form -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:28px;">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;">🔍 Track a Shipment</h2>
                <form id="trackForm" onsubmit="doTrack(event)" style="display:flex;gap:10px;flex-wrap:wrap;">
                    <input id="trackTokenInput" type="text"
                        placeholder="Enter tracking number or order code…"
                        style="flex:1;min-width:200px;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:12px;padding:12px 16px;font-size:0.9rem;"
                        autocomplete="off" required>
                    <button type="submit"
                        style="background:var(--accent);color:#fff;border:none;border-radius:12px;padding:12px 24px;font-weight:700;cursor:pointer;font-size:0.9rem;">
                        Track
                    </button>
                </form>
            </div>

            <?php if ($isLoggedIn && !empty($myShipments)): ?>
            <!-- My recent shipments -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:28px;">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:16px;">🚚 My Shipments</h2>
                <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($myShipments as $s): ?>
                    <a href="/mall/delivery/track/<?= htmlspecialchars($s['tracking_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--surface2);border-radius:14px;text-decoration:none;color:var(--text);border:1px solid var(--border);transition:border-color .15s;"
                       onmouseenter="this.style.borderColor='var(--accent)'" onmouseleave="this.style.borderColor='var(--border)'">
                        <div>
                            <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($s['order_code'] ?? 'Order', ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="color:var(--muted);font-size:0.78rem;margin-top:2px;"><?= htmlspecialchars($s['tracking_token'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="font-size:0.8rem;font-weight:600;padding:4px 12px;border-radius:999px;background:<?= $s['status']==='delivered' ? 'rgba(34,197,94,0.15)' : 'rgba(59,130,246,0.15)' ?>;color:<?= $s['status']==='delivered' ? '#22c55e' : 'var(--accent)' ?>;">
                                <?= htmlspecialchars(ucwords(str_replace('_',' ',$s['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                        </div>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && $isSeller): ?>
            <!-- Seller: link to pending deliveries -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:20px 24px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-weight:700;margin-bottom:4px;">📬 Seller Delivery Dashboard</div>
                        <div style="color:var(--muted);font-size:0.84rem;">Manage your orders ready for pickup.</div>
                    </div>
                    <a href="/marketplace/sellers/orders" style="background:var(--accent);color:#fff;padding:10px 20px;border-radius:12px;font-weight:700;font-size:0.85rem;text-decoration:none;">View Orders</a>
                </div>
            </div>
            <?php elseif (!$isLoggedIn): ?>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:20px 24px;text-align:center;">
                <p style="color:var(--muted);margin-bottom:12px;">Log in to view your delivery history.</p>
                <a href="/login" style="background:var(--accent);color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;text-decoration:none;font-size:0.9rem;">Log In</a>
            </div>
            <?php endif; ?>

    </main>
</div>
<?php include __DIR__ . '/../parts/footer.php'; ?>
<script>
function doTrack(e) {
    e.preventDefault();
    const val = document.getElementById('trackTokenInput').value.trim();
    if (!val) return;
    window.location = '/mall/delivery/track/' + encodeURIComponent(val);
}
</script>
</body>
</html>
