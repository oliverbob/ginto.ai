<?php
$orders = $orders ?? [];
$pageKind = $page_kind ?? 'buyer';
$pageTitle = $pageKind === 'seller' ? 'Seller Orders' : ($pageKind === 'delivery' ? 'Delivery Dashboard' : 'My Mall Orders');
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="mall-content-push">
<section style="max-width:1260px;margin:28px auto 72px;padding:0 18px;display:flex;flex-direction:column;gap:18px;">
    <div style="border:1px solid var(--border);background:linear-gradient(140deg, rgba(255,255,255,0.05), rgba(69,122,255,0.1), rgba(255,214,102,0.15));border-radius:26px;padding:26px;display:flex;justify-content:space-between;gap:16px;align-items:flex-end;flex-wrap:wrap;">
        <div>
            <div style="font-size:0.82rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);font-weight:700;">Mall Order Flow</div>
            <h1 style="margin:8px 0 10px;font-size:2rem;line-height:1.05;font-weight:800;"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p style="margin:0;color:var(--muted);font-size:0.92rem;line-height:1.7;max-width:760px;">
                <?= $pageKind === 'seller'
                    ? 'Manage paid orders, prepare parcels, and keep buyers updated while delivery services move the shipment through pickup, transit, and delivery.'
                    : ($pageKind === 'delivery'
                        ? 'Claim ready orders, update transit milestones, and notify buyers automatically whenever the package status changes.'
                        : 'Track every mall purchase from payment confirmation to delivery and completion.') ?>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="/marketplace" class="btn btn-secondary">Back to Mall</a>
            <?php if ($pageKind !== 'buyer'): ?>
            <a href="/mall/orders" class="btn btn-secondary">Buyer Orders</a>
            <?php endif; ?>
            <?php if ($pageKind !== 'seller'): ?>
            <a href="/marketplace/sellers/orders" class="btn btn-secondary">Seller Dashboard</a>
            <?php endif; ?>
            <a href="/mall/delivery" class="btn btn-secondary">📦 Delivery &amp; Tracking</a>
        </div>
    </div>

    <?php if (!empty($orders)): ?>
        <?php foreach ($orders as $order): ?>
        <article style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;display:flex;flex-direction:column;gap:18px;">
            <div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start;">
                <div>
                    <div style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);font-weight:700;">Order <?= htmlspecialchars($order['order_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <h2 style="margin:8px 0 6px;font-size:1.2rem;font-weight:800;line-height:1.2;"><?= htmlspecialchars($order['storefront']['display_name'] ?? ($order['seller']['fullname'] ?? ($order['seller']['username'] ?? 'Seller')), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div style="font-size:0.84rem;color:var(--muted);line-height:1.7;">
                        Status: <strong style="color:var(--text);"><?= htmlspecialchars($order['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                        · Payment: <strong style="color:var(--text);"><?= htmlspecialchars($order['payment_status'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                        · Created: <?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <div style="text-align:right;min-width:190px;">
                    <div style="font-size:0.8rem;color:var(--muted);">Buyer total</div>
                    <div style="font-size:1.5rem;font-weight:800;line-height:1.1;">₱<?= number_format((float)($order['buyer_total_amount'] ?? 0), 2) ?></div>
                    <?php if ($pageKind === 'seller'): ?>
                    <div style="font-size:0.78rem;color:#86efac;margin-top:6px;">Seller net ₱<?= number_format((float)($order['seller_net_amount'] ?? 0), 2) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,0.9fr);gap:18px;align-items:start;">
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach (($order['items'] ?? []) as $item): ?>
                    <div style="display:grid;grid-template-columns:64px 1fr auto;gap:12px;align-items:center;padding:12px;border-radius:16px;background:var(--surface2);border:1px solid var(--border);">
                        <img src="<?= htmlspecialchars($item['image_url'] ?? '/assets/images/placeholder_ceramic.svg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title_snapshot'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="width:64px;height:64px;border-radius:14px;object-fit:cover;border:1px solid var(--border);">
                        <div>
                            <div style="font-size:0.92rem;font-weight:700;"><?= htmlspecialchars($item['title_snapshot'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="font-size:0.78rem;color:var(--muted);margin-top:4px;">Qty <?= (int)($item['quantity'] ?? 0) ?> · <?= htmlspecialchars($item['pricing_model'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.9rem;font-weight:800;">₱<?= number_format((float)($item['line_subtotal'] ?? 0), 2) ?></div>
                            <div style="font-size:0.74rem;color:var(--muted);">Fee ₱<?= number_format((float)($item['platform_fee_amount'] ?? 0), 2) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="padding:14px;border-radius:18px;background:var(--surface2);border:1px solid var(--border);">
                        <div style="font-size:0.76rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);font-weight:700;margin-bottom:8px;">Shipping</div>
                        <div style="font-size:0.88rem;font-weight:700;"><?= htmlspecialchars($order['shipping_address']['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="font-size:0.8rem;color:var(--muted);line-height:1.7;margin-top:4px;">
                            <?= htmlspecialchars($order['shipping_address']['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                            <?= htmlspecialchars($order['shipping_address']['address_line1'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($order['shipping_address']['address_line2'])): ?><br><?= htmlspecialchars($order['shipping_address']['address_line2'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?><br>
                            <?= htmlspecialchars($order['shipping_address']['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($order['shipping_address']['province'] ?? '', ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($order['shipping_address']['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div style="padding:14px;border-radius:18px;background:var(--surface2);border:1px solid var(--border);">
                        <div style="font-size:0.76rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);font-weight:700;margin-bottom:8px;">Timeline</div>
                        <div style="display:flex;flex-direction:column;gap:8px;max-height:220px;overflow:auto;">
                            <?php foreach (($order['history'] ?? []) as $event): ?>
                            <div style="padding:10px 12px;border-radius:14px;background:rgba(255,255,255,0.03);border:1px solid var(--border);">
                                <div style="font-size:0.8rem;font-weight:700;"><?= htmlspecialchars($event['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size:0.72rem;color:var(--muted);margin-top:4px;"><?= htmlspecialchars($event['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($event['actor_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($pageKind === 'delivery' && empty($order['delivery_assignee_user_id'])): ?>
                    <button type="button" class="btn btn-primary delivery-claim-btn" data-order-id="<?= (int)$order['id'] ?>">Claim Delivery</button>
                    <?php endif; ?>
                    <?php if (!empty($order['tracking_token'])): ?>
                    <a href="/mall/delivery/track/<?= htmlspecialchars($order['tracking_token'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">📍 Track Shipment</a>
                    <?php endif; ?>

                    <?php if (in_array($pageKind, ['seller', 'delivery'], true)): ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <select class="pf-input order-status-select" data-order-id="<?= (int)$order['id'] ?>" data-actor-type="<?= htmlspecialchars($pageKind === 'seller' ? 'seller' : 'delivery', ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach (['processing', 'ready_for_pickup', 'in_transit', 'delivered', 'completed', 'cancelled'] as $status): ?>
                            <option value="<?= $status ?>" <?= ($order['status'] ?? '') === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea class="pf-input order-status-message" data-order-id="<?= (int)$order['id'] ?>" rows="2" placeholder="Optional note to buyer"></textarea>
                        <button type="button" class="btn btn-secondary order-status-btn" data-order-id="<?= (int)$order['id'] ?>">Update Status</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    <?php else: ?>
    <div style="padding:22px;border-radius:24px;background:var(--surface);border:1px solid var(--border);color:var(--muted);font-size:0.92rem;">No orders found for this dashboard yet.</div>
    <?php endif; ?>
</section>

<script>
(function () {
    const csrfToken = <?= json_encode($csrf_token ?? '') ?>;

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(Object.assign({ csrf_token: csrfToken }, payload || {})),
        });
        const json = await response.json();
        if (!response.ok || json.success === false) {
            throw new Error(json.message || 'Request failed.');
        }
        return json;
    }

    document.querySelectorAll('.delivery-claim-btn').forEach(function (button) {
        button.addEventListener('click', async function () {
            try {
                await post('/api/mall/delivery/claim', { order_id: button.dataset.orderId });
                window.location.reload();
            } catch (error) {
                window.alert(error.message);
            }
        });
    });

    document.querySelectorAll('.order-status-btn').forEach(function (button) {
        button.addEventListener('click', async function () {
            const orderId = button.dataset.orderId;
            const status = document.querySelector('.order-status-select[data-order-id="' + orderId + '"]');
            const message = document.querySelector('.order-status-message[data-order-id="' + orderId + '"]');
            try {
                await post('/api/mall/orders/status', {
                    order_id: orderId,
                    status: status ? status.value : '',
                    message: message ? message.value : '',
                    actor_type: status ? status.dataset.actorType : 'seller',
                });
                window.location.reload();
            } catch (error) {
                window.alert(error.message);
            }
        });
    });
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>