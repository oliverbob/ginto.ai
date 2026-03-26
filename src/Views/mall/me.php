<?php
/** @var string $csrf_token */
/** @var array $orders */
/** @var array $seller_orders */
/** @var bool $is_seller */
/** @var int $mall_unread_notifications */
/** @var float $mall_wallet_balance */
$unread = $mall_unread_notifications ?? 0;
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<style>
.me-shell { max-width:900px; margin:0 auto 100px; padding:16px; }
.me-section { margin-bottom:32px; }
.me-section-title { font-size:1.2rem; font-weight:700; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.me-badge { background:var(--accent); color:#fff; font-size:.7rem; padding:2px 8px; border-radius:10px; }
.me-order-card { background:var(--card-bg,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:16px; margin-bottom:14px; }
.me-status { display:inline-block; padding:3px 10px; border-radius:8px; font-size:.78rem; font-weight:600; }
.me-status.paid { background:#dbeafe; color:#1d4ed8; }
.me-status.processing { background:#fef3c7; color:#92400e; }
.me-status.in_transit { background:#d1fae5; color:#065f46; }
.me-status.delivered { background:#a7f3d0; color:#047857; }
.me-status.completed { background:#c7d2fe; color:#3730a3; }
.me-status.cancelled { background:#fecaca; color:#991b1b; }
.me-status.pending_payment { background:#fde68a; color:#78350f; }
.me-items { margin:8px 0; font-size:.88rem; color:var(--muted); }
.me-meta { font-size:.82rem; color:var(--muted); margin-top:6px; }
.me-actions { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.me-btn { padding:6px 14px; border-radius:8px; font-size:.82rem; cursor:pointer; border:1px solid var(--border); background:var(--card-bg); color:var(--text); transition:all .2s; }
.me-btn:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
.me-btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.me-proof-grid { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0; }
.me-proof-thumb { width:60px; height:60px; border-radius:6px; object-fit:cover; border:1px solid var(--border); cursor:pointer; }
/* Rating modal */
.me-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
.me-modal-bg.show { display:flex; }
.me-modal { background:var(--card-bg); border-radius:14px; padding:24px; max-width:420px; width:92%; max-height:90vh; overflow-y:auto; }
.me-stars { display:flex; gap:4px; }
.me-stars span { font-size:1.6rem; cursor:pointer; opacity:.4; transition:opacity .2s; }
.me-stars span.active { opacity:1; }
.me-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:16px; }
.me-tab { padding:10px 18px; font-size:.9rem; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; color:var(--muted); transition:all .2s; }
.me-tab.active { color:var(--accent); border-bottom-color:var(--accent); font-weight:600; }
.me-tab-content { display:none; }
.me-tab-content.active { display:block; }
.seller-ship-btns { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
.seller-ship-btn { padding:5px 12px; border-radius:6px; font-size:.78rem; cursor:pointer; border:1px solid var(--border); background:var(--card-bg); }
.seller-ship-btn:hover { background:var(--accent); color:#fff; }
.seller-ship-btn.active-status { background:var(--accent); color:#fff; }
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="me-shell">
    <h1 style="font-size:1.5rem;margin-bottom:4px;">👤 Me</h1>
    <p style="color:var(--muted);margin-bottom:20px;">Your deliveries, orders & shipment tracking</p>

    <!-- Tabs -->
    <div class="me-tabs">
        <div class="me-tab active" data-tab="my-orders">🛒 My Orders</div>
        <div class="me-tab" data-tab="delivery-status">🚚 Delivery Status</div>
        <?php if ($is_seller): ?>
        <div class="me-tab" data-tab="seller-orders">📦 Seller Orders</div>
        <?php endif; ?>
    </div>

    <!-- Tab: My Orders -->
    <div class="me-tab-content active" id="tab-my-orders">
        <div class="me-section">
            <?php if (empty($orders)): ?>
                <p style="color:var(--muted);text-align:center;padding:40px 0;">No orders yet. <a href="/mall">Start shopping!</a></p>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                <div class="me-order-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <strong><?= htmlspecialchars((string)$o['order_code']) ?></strong>
                        <span class="me-status <?= htmlspecialchars(str_replace(' ', '_', (string)($o['status'] ?? ''))) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($o['status'] ?? '')))) ?></span>
                    </div>
                    <div class="me-items">
                        <?php foreach (($o['items'] ?? []) as $item): ?>
                            <div><?= htmlspecialchars((string)($item['title_snapshot'] ?? '')) ?> × <?= (int)($item['quantity'] ?? 1) ?> — ₱<?= number_format((float)($item['line_subtotal'] ?? 0), 2) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="me-meta">
                        Total: ₱<?= number_format((float)($o['buyer_total_amount'] ?? 0), 2) ?> · 
                        <?= htmlspecialchars(date('M j, Y', strtotime((string)($o['created_at'] ?? 'now')))) ?>
                    </div>

                    <!-- Delivery Proofs -->
                    <?php if (!empty($o['proofs'])): ?>
                    <div style="margin-top:8px;">
                        <strong style="font-size:.82rem;">📸 Delivery Proofs:</strong>
                        <div class="me-proof-grid">
                            <?php foreach ($o['proofs'] as $proof): ?>
                                <img src="<?= htmlspecialchars((string)$proof['photo_url']) ?>" class="me-proof-thumb"
                                    alt="Proof" onclick="window.open(this.src,'_blank')">
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="me-actions">
                        <?php $status = (string)($o['status'] ?? ''); ?>
                        <?php if (in_array($status, ['delivered', 'completed'])): ?>
                            <button class="me-btn" onclick="openProofUpload(<?= (int)$o['id'] ?>)">📸 Upload Photo</button>
                            <?php
                            $hasRating = !empty($o['ratings']);
                            ?>
                            <?php if (!$hasRating): ?>
                            <button class="me-btn me-btn-primary" onclick="openRatingModal(<?= (int)$o['id'] ?>, <?= json_encode(array_map(fn($i) => ['id'=>(int)$i['product_id'],'title'=>$i['title_snapshot']??''], $o['items'] ?? [])) ?>)">⭐ Rate</button>
                            <?php else: ?>
                            <span style="font-size:.82rem;color:var(--muted);">✅ Rated</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Delivery Status -->
    <div class="me-tab-content" id="tab-delivery-status">
        <div class="me-section">
            <div class="me-section-title">📍 Delivery Tracking</div>
            <?php
            $deliveryOrders = array_filter($orders, fn($o) => in_array($o['status'] ?? '', ['paid','processing','ready_for_pickup','in_transit','out_for_delivery','delivered']));
            ?>
            <?php if (empty($deliveryOrders)): ?>
                <p style="color:var(--muted);text-align:center;padding:30px 0;">No active deliveries.</p>
            <?php else: ?>
                <?php foreach ($deliveryOrders as $o): ?>
                <div class="me-order-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <strong><?= htmlspecialchars((string)$o['order_code']) ?></strong>
                        <span class="me-status <?= htmlspecialchars(str_replace(' ', '_', (string)($o['status'] ?? ''))) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($o['status'] ?? '')))) ?></span>
                    </div>
                    <div class="me-items">
                        <?php foreach (($o['items'] ?? []) as $item): ?>
                            <div><?= htmlspecialchars((string)($item['title_snapshot'] ?? '')) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="me-meta">
                        <!-- Timeline -->
                        <div style="margin-top:8px;">
                            <?php
                            $timeline = [
                                'paid' => ['icon'=>'💰','label'=>'Paid'],
                                'processing' => ['icon'=>'📋','label'=>'Packing'],
                                'ready_for_pickup' => ['icon'=>'📦','label'=>'Ready'],
                                'in_transit' => ['icon'=>'🛵','label'=>'Shipping'],
                                'out_for_delivery' => ['icon'=>'📍','label'=>'Arriving'],
                                'delivered' => ['icon'=>'✅','label'=>'Delivered'],
                            ];
                            $currentStatus = (string)($o['status'] ?? '');
                            $reached = true;
                            ?>
                            <div style="display:flex;gap:4px;font-size:.76rem;">
                                <?php foreach ($timeline as $key => $step): ?>
                                    <?php
                                    $isActive = ($key === $currentStatus);
                                    $isPast = $reached && !$isActive;
                                    if ($isActive) $reached = false;
                                    $color = $isActive ? 'var(--accent)' : ($isPast ? 'var(--muted)' : '#ccc');
                                    ?>
                                    <div style="text-align:center;flex:1;">
                                        <div style="font-size:1.1rem;"><?= $step['icon'] ?></div>
                                        <div style="color:<?= $color ?>;font-weight:<?= $isActive ? '700' : '400' ?>"><?= $step['label'] ?></div>
                                        <?php if (!$isActive && $isPast): ?><div style="color:var(--muted);">✓</div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_seller): ?>
    <!-- Tab: Seller Orders -->
    <div class="me-tab-content" id="tab-seller-orders">
        <div class="me-section">
            <div class="me-section-title">📦 Orders to Fulfill</div>
            <?php if (empty($seller_orders)): ?>
                <p style="color:var(--muted);text-align:center;padding:30px 0;">No seller orders yet.</p>
            <?php else: ?>
                <?php foreach ($seller_orders as $so): ?>
                <div class="me-order-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <strong><?= htmlspecialchars((string)$so['order_code']) ?></strong>
                        <span class="me-status <?= htmlspecialchars(str_replace(' ', '_', (string)($so['status'] ?? ''))) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($so['status'] ?? '')))) ?></span>
                    </div>
                    <div class="me-items">
                        <?php foreach (($so['items'] ?? []) as $item): ?>
                            <div><?= htmlspecialchars((string)($item['title_snapshot'] ?? '')) ?> × <?= (int)($item['quantity'] ?? 1) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="me-meta">
                        Buyer: <?= htmlspecialchars(($so['buyer']['fullname'] ?? '') ?: ($so['buyer']['username'] ?? 'Buyer')) ?>
                        · ₱<?= number_format((float)($so['seller_net_amount'] ?? 0), 2) ?> net
                    </div>
                    <?php if (!empty($so['shipping_address'])): ?>
                    <div class="me-meta">
                        📍 Ship to: <?= htmlspecialchars(($so['shipping_address']['address_line1'] ?? '') . ', ' . ($so['shipping_address']['city'] ?? '')) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Seller shipment status buttons -->
                    <div class="seller-ship-btns" data-order="<?= (int)$so['id'] ?>">
                        <?php
                        $curStatus = (string)($so['status'] ?? '');
                        $sellerStatuses = [
                            'processing' => '📋 Packing',
                            'ready_for_pickup' => '📦 Ready for Pickup',
                            'in_transit' => '🚚 On the Way',
                            'delivered' => '✅ Delivered',
                        ];
                        foreach ($sellerStatuses as $sKey => $sLabel):
                            $isActive = ($curStatus === $sKey);
                        ?>
                            <button class="seller-ship-btn <?= $isActive ? 'active-status' : '' ?>"
                                onclick="updateSellerOrderStatus(<?= (int)$so['id'] ?>, '<?= $sKey ?>')"
                                <?= $isActive ? 'disabled' : '' ?>>
                                <?= $sLabel ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="me-actions" style="margin-top:8px;">
                        <button class="me-btn" onclick="openProofUpload(<?= (int)$so['id'] ?>)">📸 Upload Proof Photo</button>
                    </div>

                    <?php if ($curStatus === 'delivered'): ?>
                    <div style="margin-top:10px;padding:10px;background:#ecfdf5;border-radius:8px;font-size:.84rem;color:#065f46;">
                        💰 Payment of ₱<?= number_format((float)($so['seller_net_amount'] ?? 0), 2) ?> will be deposited to your account within 7-12 business days.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Photo upload modal -->
<div class="me-modal-bg" id="proof-upload-modal">
    <div class="me-modal">
        <h3>📸 Upload Delivery Photo</h3>
        <form id="proof-upload-form" enctype="multipart/form-data" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="order_id" id="proof-order-id" value="">
            <div style="margin-bottom:12px;">
                <label style="font-size:.88rem;font-weight:600;">Photo Type:</label><br>
                <select name="photo_type" style="padding:6px 10px;border-radius:6px;border:1px solid var(--border);width:100%;margin-top:4px;">
                    <option value="product_arrival">Product Arrival</option>
                    <option value="selfie_with_customer">Selfie with Customer</option>
                    <option value="product_photo">Product Photo</option>
                    <option value="damage_report">Damage Report</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:.88rem;font-weight:600;">Condition:</label><br>
                <select name="condition_rating" style="padding:6px 10px;border-radius:6px;border:1px solid var(--border);width:100%;margin-top:4px;">
                    <option value="good">Good Condition</option>
                    <option value="minor_damage">Minor Damage</option>
                    <option value="major_damage">Major Damage</option>
                    <option value="wrong_item">Wrong Item</option>
                    <option value="missing_parts">Missing Parts</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:.88rem;font-weight:600;">Photo:</label><br>
                <input type="file" name="photo" accept="image/*" capture="environment" required
                    style="margin-top:4px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:.88rem;font-weight:600;">Notes (optional):</label><br>
                <textarea name="notes" rows="2" maxlength="500" style="width:100%;border-radius:6px;border:1px solid var(--border);padding:6px;margin-top:4px;resize:vertical;"></textarea>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="me-btn me-btn-primary" style="flex:1;">Upload</button>
                <button type="button" class="me-btn" onclick="closeModal('proof-upload-modal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Rating modal -->
<div class="me-modal-bg" id="rating-modal">
    <div class="me-modal">
        <h3>⭐ Rate Your Purchase</h3>
        <form id="rating-form" style="margin-top:12px;">
            <input type="hidden" id="rating-order-id" value="">
            <div id="rating-items-container"></div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" class="me-btn me-btn-primary" style="flex:1;">Submit Rating</button>
                <button type="button" class="me-btn" onclick="closeModal('rating-modal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/_footer_marketplace.php'; ?>

<!-- Bottom Navigation Bar -->
<?php include __DIR__ . '/parts/bottom_nav.php'; ?>

<script>
const CSRF = <?= json_encode($csrf_token) ?>;

// Tabs
document.querySelectorAll('.me-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.me-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.me-tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function openProofUpload(orderId) {
    document.getElementById('proof-order-id').value = orderId;
    document.getElementById('proof-upload-modal').classList.add('show');
    // Try to get GPS
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const form = document.getElementById('proof-upload-form');
            let latInput = form.querySelector('[name="lat"]');
            let lngInput = form.querySelector('[name="lng"]');
            if (!latInput) { latInput = document.createElement('input'); latInput.type='hidden'; latInput.name='lat'; form.appendChild(latInput); }
            if (!lngInput) { lngInput = document.createElement('input'); lngInput.type='hidden'; lngInput.name='lng'; form.appendChild(lngInput); }
            latInput.value = pos.coords.latitude;
            lngInput.value = pos.coords.longitude;
        }, () => {});
    }
}

document.getElementById('proof-upload-form').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    try {
        const res = await fetch('/api/mall/delivery/proof/upload', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            alert('Photo uploaded successfully!');
            closeModal('proof-upload-modal');
            location.reload();
        } else {
            alert(data.message || 'Upload failed.');
        }
    } catch(err) { alert('Upload failed: ' + err.message); }
});

function openRatingModal(orderId, items) {
    document.getElementById('rating-order-id').value = orderId;
    const container = document.getElementById('rating-items-container');
    container.innerHTML = '';
    items.forEach(item => {
        const div = document.createElement('div');
        div.style.marginBottom = '16px';
        div.innerHTML = `
            <strong style="font-size:.9rem;">${item.title}</strong>
            <input type="hidden" class="rating-product-id" value="${item.id}">
            <div style="margin-top:6px;">
                <label style="font-size:.82rem;">Product Rating:</label>
                <div class="me-stars" data-field="product_rating_${item.id}">
                    ${[1,2,3,4,5].map(n => `<span data-val="${n}" onclick="setStars(this)">⭐</span>`).join('')}
                </div>
                <input type="hidden" class="product-rating-val" value="5">
            </div>
            <div style="margin-top:6px;">
                <label style="font-size:.82rem;">Seller Rating:</label>
                <div class="me-stars" data-field="seller_rating_${item.id}">
                    ${[1,2,3,4,5].map(n => `<span data-val="${n}" onclick="setStars(this)">⭐</span>`).join('')}
                </div>
                <input type="hidden" class="seller-rating-val" value="5">
            </div>
            <div style="margin-top:6px;">
                <label style="font-size:.82rem;">Review (optional):</label>
                <textarea class="review-text" rows="2" maxlength="1000" style="width:100%;border-radius:6px;border:1px solid var(--border);padding:6px;resize:vertical;margin-top:4px;" placeholder="Share your experience..."></textarea>
            </div>
        `;
        container.appendChild(div);
    });
    // Init stars
    container.querySelectorAll('.me-stars').forEach(group => {
        const spans = group.querySelectorAll('span');
        spans.forEach((s, i) => { if (i < 5) s.classList.add('active'); });
    });
    document.getElementById('rating-modal').classList.add('show');
}

function setStars(el) {
    const parent = el.parentElement;
    const val = parseInt(el.dataset.val);
    const spans = parent.querySelectorAll('span');
    spans.forEach((s, i) => s.classList.toggle('active', i < val));
    // Update hidden input
    const group = parent.closest('div[style]');
    const hiddenInput = parent.nextElementSibling;
    if (hiddenInput && hiddenInput.type === 'hidden') hiddenInput.value = val;
}

document.getElementById('rating-form').addEventListener('submit', async e => {
    e.preventDefault();
    const orderId = document.getElementById('rating-order-id').value;
    const items = document.querySelectorAll('#rating-items-container > div');
    for (const item of items) {
        const productId = item.querySelector('.rating-product-id').value;
        const productRating = item.querySelector('.product-rating-val').value;
        const sellerRating = item.querySelector('.seller-rating-val').value;
        const reviewText = item.querySelector('.review-text').value;
        try {
            const res = await fetch('/api/mall/rating/submit', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                    csrf_token: CSRF,
                    order_id: parseInt(orderId),
                    product_id: parseInt(productId),
                    product_rating: parseInt(productRating),
                    seller_rating: parseInt(sellerRating),
                    review_text: reviewText
                })
            });
            const data = await res.json();
            if (!data.success) console.error('Rating failed:', data.message);
        } catch(err) { console.error(err); }
    }
    alert('Thank you for your review!');
    closeModal('rating-modal');
    location.reload();
});

function updateSellerOrderStatus(orderId, status) {
    if (!confirm('Update order status to "' + status.replace(/_/g,' ') + '"?')) return;
    fetch('/api/mall/orders/status', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ csrf_token: CSRF, order_id: orderId, status: status, actor_type: 'seller' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else { alert(data.message || 'Failed to update.'); }
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>
</body>
</html>
