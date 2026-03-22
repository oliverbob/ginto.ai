<?php
$title = $title ?? 'Track Shipment';
$isLoggedIn = !empty($_SESSION['user_id']);
$shipment = $shipment ?? [];
$role = $role ?? 'guest';
$trackingToken = $tracking_token ?? '';
$gpsHistory = $gps_history ?? [];
$statusHistory = $status_history ?? [];
$isAdmin = $is_admin ?? false;
$viewerUserId = $viewer_user_id ?? 0;
$order  = $shipment['order'] ?? [];
$buyer  = $shipment['buyer'] ?? [];
$seller = $shipment['seller'] ?? [];
$rider  = $shipment['rider'] ?? [];
$items  = $shipment['items'] ?? [];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/../parts/head.php'; ?>
<!-- Leaflet.js for map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
<style>
.track-map { width:100%; height:380px; border-radius:16px; overflow:hidden; margin-bottom:20px; border:1px solid var(--border); }
.status-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:999px; font-size:0.8rem; font-weight:700; }
.timeline { display:flex; flex-direction:column; gap:0; }
.tl-item { display:flex; gap:14px; }
.tl-dot  { display:flex; flex-direction:column; align-items:center; }
.tl-dot-circle { width:14px; height:14px; border-radius:50%; background:var(--accent); flex-shrink:0; margin-top:4px; }
.tl-dot-line   { width:2px; flex:1; background:var(--border); margin:4px 0; }
.tl-content { padding-bottom:16px; }
.info-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:20px; margin-bottom:16px; }
.info-row  { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--border); font-size:0.88rem; }
.info-row:last-child { border-bottom:none; }
.info-label { color:var(--muted); }
.info-val   { font-weight:600; text-align:right; }
</style>
<body>
<?php include __DIR__ . '/../parts/header.php'; ?>
<div class="container wrapper">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <main class="main-content" style="padding:24px 0;">
        <div style="max-width:820px;margin:0 auto;padding:0 16px;">

            <!-- Back + status -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <div>
                    <a href="/mall/delivery" style="color:var(--muted);font-size:0.84rem;text-decoration:none;">← Back to Delivery</a>
                    <h1 style="font-size:1.4rem;font-weight:800;margin-top:6px;">
                        📦 Order <?= htmlspecialchars($order['order_code'] ?? $trackingToken, ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                </div>
                <?php
                $statusColors = [
                    'pending'          => '#94a3b8',
                    'ready_for_pickup' => '#f59e0b',
                    'picked_up'        => '#3b82f6',
                    'in_transit'       => '#3b82f6',
                    'out_for_delivery' => '#8b5cf6',
                    'delivered'        => '#22c55e',
                    'failed_delivery'  => '#ef4444',
                    'returned'         => '#f97316',
                ];
                $curStatus = $shipment['status'] ?? 'pending';
                $statusColor = $statusColors[$curStatus] ?? '#94a3b8';
                ?>
                <span class="status-pill" id="statusPill" style="background:<?= $statusColor ?>22;color:<?= $statusColor ?>;border:1px solid <?= $statusColor ?>55;">
                    <span id="statusDot" style="width:8px;height:8px;border-radius:50%;background:<?= $statusColor ?>;"></span>
                    <span id="statusText"><?= htmlspecialchars(ucwords(str_replace('_',' ',$curStatus)), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>

            <!-- Live Map -->
            <div id="trackMap" class="track-map"></div>
            <div id="noGpsMsg" style="display:none;text-align:center;padding:16px;color:var(--muted);font-size:0.9rem;background:var(--surface);border-radius:12px;margin-bottom:16px;">
                📍 GPS tracking will appear once the rider begins moving.
            </div>

            <!-- Role actions -->
            <?php if ($role === 'seller'): ?>
            <div class="info-card" style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:12px;">Seller Actions</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($curStatus === 'pending'): ?>
                    <button onclick="updateStatus('ready_for_pickup')" class="action-status-btn"
                        style="background:rgba(245,158,11,.15);color:#f59e0b;border:1px solid #f59e0b66;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                        ✅ Mark Ready for Pickup
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($role === 'rider'): ?>
            <div class="info-card" style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:12px;">Rider Actions</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($curStatus === 'ready_for_pickup' || $curStatus === 'pending'): ?>
                    <button onclick="updateStatus('picked_up')" class="action-status-btn"
                        style="background:rgba(59,130,246,.15);color:#3b82f6;border:1px solid #3b82f666;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                        📦 Mark Picked Up
                    </button>
                    <?php endif; ?>
                    <?php if ($curStatus === 'picked_up' || $curStatus === 'ready_for_pickup'): ?>
                    <button onclick="updateStatus('in_transit')" class="action-status-btn"
                        style="background:rgba(139,92,246,.15);color:#8b5cf6;border:1px solid #8b5cf666;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                        🛵 In Transit
                    </button>
                    <?php endif; ?>
                    <?php if (in_array($curStatus,['in_transit','out_for_delivery','picked_up'])): ?>
                    <button onclick="updateStatus('out_for_delivery')" class="action-status-btn"
                        style="background:rgba(139,92,246,.15);color:#8b5cf6;border:1px solid #8b5cf666;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                        📍 Out for Delivery
                    </button>
                    <button onclick="updateStatus('delivered')" class="action-status-btn"
                        style="background:rgba(34,197,94,.15);color:#22c55e;border:1px solid #22c55e66;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                        ✅ Mark Delivered
                    </button>
                    <button onclick="updateStatus('failed_delivery')" class="action-status-btn"
                        style="background:rgba(239,68,68,.15);color:#ef4444;border:1px solid #ef444466;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                        ❌ Failed Delivery
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($role === 'admin'): ?>
            <div class="info-card" style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:12px;">Admin Actions</div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <select id="adminStatusSel" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:10px 14px;font-size:0.85rem;">
                        <?php foreach (['pending','ready_for_pickup','picked_up','in_transit','out_for_delivery','delivered','failed_delivery','returned'] as $st): ?>
                        <option value="<?= $st ?>" <?= $st===$curStatus?'selected':'' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input id="adminNote" type="text" placeholder="Optional note…"
                        style="flex:1;min-width:160px;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:10px 14px;font-size:0.85rem;">
                    <button onclick="adminUpdateStatus()" style="background:var(--accent);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:700;cursor:pointer;font-size:0.85rem;">Update</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div class="info-card">
                    <div style="font-weight:700;margin-bottom:10px;font-size:0.9rem;">📋 Order Info</div>
                    <div class="info-row"><span class="info-label">Order Code</span><span class="info-val"><?= htmlspecialchars($order['order_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="info-row"><span class="info-label">Tracking #</span><span class="info-val" style="font-size:0.78rem;"><?= htmlspecialchars($trackingToken, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="info-row"><span class="info-label">Total</span><span class="info-val"><?= htmlspecialchars($order['currency'] ?? 'PHP', ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)($order['buyer_total_amount'] ?? 0), 2) ?></span></div>
                    <div class="info-row"><span class="info-label">Placed</span><span class="info-val"><?= htmlspecialchars($order['created_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php if (!empty($order['delivered_at'])): ?>
                    <div class="info-row"><span class="info-label">Delivered</span><span class="info-val"><?= htmlspecialchars($order['delivered_at'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <div style="font-weight:700;margin-bottom:10px;font-size:0.9rem;">👤 Parties</div>
                    <?php if (in_array($role, ['admin','seller','rider'])): ?>
                    <div class="info-row"><span class="info-label">Buyer</span><span class="info-val"><?= htmlspecialchars($buyer['fullname'] ?? $buyer['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                    <?php if (in_array($role, ['admin','buyer','rider'])): ?>
                    <div class="info-row"><span class="info-label">Seller</span><span class="info-val"><?= htmlspecialchars($seller['fullname'] ?? $seller['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Rider</span>
                        <span class="info-val" id="riderName"><?= $rider ? htmlspecialchars($rider['fullname'] ?? $rider['username'] ?? 'Assigned', ENT_QUOTES, 'UTF-8') : '<span style="color:var(--muted)">Not yet assigned</span>' ?></span>
                    </div>
                </div>
            </div>

            <!-- Delivery address -->
            <?php
            $shippingJson = is_string($order['shipping_address_json'] ?? null) ? json_decode($order['shipping_address_json'], true) : ($order['shipping_address_json'] ?? []);
            if ($shippingJson && in_array($role, ['admin','rider','seller'])):
            ?>
            <div class="info-card" style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:10px;font-size:0.9rem;">📍 Delivery Address</div>
                <div style="font-size:0.88rem;line-height:1.6;">
                    <?= htmlspecialchars($shippingJson['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                    <?= htmlspecialchars($shippingJson['address1'] ?? '', ENT_QUOTES, 'UTF-8') ?><?= !empty($shippingJson['address2']) ? ', ' . htmlspecialchars($shippingJson['address2'], ENT_QUOTES, 'UTF-8') : '' ?><br>
                    <?= htmlspecialchars(($shippingJson['city'] ?? '') . ', ' . ($shippingJson['province'] ?? '') . ' ' . ($shippingJson['zip'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                    <?php if (!empty($shippingJson['phone'])): ?>
                    📞 <?= htmlspecialchars($shippingJson['phone'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Items -->
            <?php if (!empty($items)): ?>
            <div class="info-card" style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:12px;font-size:0.9rem;">🛒 Items</div>
                <?php foreach ($items as $item): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:0.88rem;">
                    <div>
                        <div style="font-weight:600;"><?= htmlspecialchars($item['product_title'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="color:var(--muted);font-size:0.78rem;">Qty: <?= (int)($item['quantity'] ?? 1) ?></div>
                    </div>
                    <div style="font-weight:700;">₱<?= number_format((float)($item['subtotal'] ?? $item['unit_price'] ?? 0), 2) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Status Timeline -->
            <?php if (!empty($statusHistory)): ?>
            <div class="info-card" style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:14px;font-size:0.9rem;">📋 Status Timeline</div>
                <div class="timeline" id="statusTimeline">
                    <?php foreach ($statusHistory as $i => $evt): ?>
                    <div class="tl-item">
                        <div class="tl-dot">
                            <div class="tl-dot-circle" style="background:<?= $statusColors[$evt['new_status']] ?? 'var(--accent)' ?>;"></div>
                            <?php if ($i < count($statusHistory)-1): ?>
                            <div class="tl-dot-line"></div>
                            <?php endif; ?>
                        </div>
                        <div class="tl-content">
                            <div style="font-weight:700;font-size:0.88rem;"><?= htmlspecialchars(ucwords(str_replace('_',' ',$evt['new_status'])), ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="color:var(--muted);font-size:0.76rem;margin-top:2px;">
                                by <?= htmlspecialchars($evt['actor_name'] ?? $evt['actor_role'] ?? 'System', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($evt['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($evt['note'])): ?>
                            <div style="margin-top:4px;font-size:0.82rem;color:var(--muted);"><?= htmlspecialchars($evt['note'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>
<?php include __DIR__ . '/../parts/footer.php'; ?>

<script>
const TRACKING_TOKEN = <?= json_encode($trackingToken) ?>;
const SHIPMENT_ID    = <?= json_encode((int)($shipment['id'] ?? 0)) ?>;
const ROLE           = <?= json_encode($role) ?>;
const CSRF_TOKEN     = <?= json_encode($csrf_token ?? '') ?>;
const GPS_HISTORY    = <?= json_encode(array_values(array_map(function($g){
    return [(float)$g['lat'], (float)$g['lng'], (float)($g['bearing']??0)];
}, $gpsHistory))) ?>;

// ── Leaflet map ──────────────────────────────────────────────────────────────
let map, riderMarker, routeLine;
const mapEl = document.getElementById('trackMap');
const noGps = document.getElementById('noGpsMsg');

function initMap(lat, lng) {
    if (map) return;
    map = L.map('trackMap').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors', maxZoom: 19
    }).addTo(map);
}

function riderIcon() {
    return L.divIcon({
        html: '<div style="font-size:28px;line-height:1;">🛵</div>',
        iconSize: [32, 32], iconAnchor: [16, 16], className: ''
    });
}

function updateRiderMarker(lat, lng, bearing) {
    if (!map) initMap(lat, lng);
    if (!riderMarker) {
        riderMarker = L.marker([lat, lng], { icon: riderIcon() }).addTo(map).bindPopup('Rider is here');
    } else {
        riderMarker.setLatLng([lat, lng]);
    }
    map.panTo([lat, lng]);
}

// Draw GPS history
(function drawHistory() {
    if (!GPS_HISTORY.length) {
        mapEl.style.display = 'none';
        noGps.style.display = 'block';
        return;
    }
    const last = GPS_HISTORY[GPS_HISTORY.length - 1];
    initMap(last[0], last[1]);
    const pts = GPS_HISTORY.map(g => [g[0], g[1]]);
    routeLine = L.polyline(pts, { color: '#3b82f6', weight: 4, opacity: 0.7 }).addTo(map);
    updateRiderMarker(last[0], last[1], last[2]);
    map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });
})();

// ── WebSocket live updates ────────────────────────────────────────────────────
const WS_PROTO = location.protocol === 'https:' ? 'wss:' : 'ws:';
const WS_HOST  = location.host;
let ws, wsReady = false, sessionToken = '';

// Get PHP session id from cookie
(function() {
    const m = document.cookie.match(/PHPSESSID=([^;]+)/);
    if (m) sessionToken = m[1];
})();

function connectWs() {
    ws = new WebSocket(`${WS_PROTO}//${WS_HOST}/mall-notify`);
    ws.onopen = () => {
        wsReady = true;
        ws.send(JSON.stringify({ type: 'auth', session_token: sessionToken, device: 'desktop' }));
    };
    ws.onmessage = (e) => {
        try {
            const msg = JSON.parse(e.data);
            if (msg.type === 'auth_result' && msg.success) {
                // Start watching
                ws.send(JSON.stringify({ type: 'watch_shipment', shipment_id: SHIPMENT_ID, token: TRACKING_TOKEN }));
            } else if (msg.type === 'gps_update' && msg.shipment_id == SHIPMENT_ID) {
                const {lat, lng, bearing} = msg;
                if (!map) { mapEl.style.display = ''; noGps.style.display = 'none'; }
                updateRiderMarker(lat, lng, bearing || 0);
                if (routeLine) {
                    const pts = routeLine.getLatLngs();
                    pts.push(L.latLng(lat, lng));
                    routeLine.setLatLngs(pts);
                } else if (map) {
                    routeLine = L.polyline([[lat, lng]], { color: '#3b82f6', weight: 4 }).addTo(map);
                }
            } else if (msg.type === 'shipment_status' && msg.shipment_id == SHIPMENT_ID) {
                updateStatusUI(msg.status, msg.message);
            }
        } catch (err) {}
    };
    ws.onclose = () => { wsReady = false; setTimeout(connectWs, 5000); };
    ws.onerror = () => ws.close();
}

if (SHIPMENT_ID > 0) connectWs();

function updateStatusUI(status, message) {
    const colors = {
        pending:'#94a3b8', ready_for_pickup:'#f59e0b', picked_up:'#3b82f6',
        in_transit:'#3b82f6', out_for_delivery:'#8b5cf6', delivered:'#22c55e',
        failed_delivery:'#ef4444', returned:'#f97316'
    };
    const color = colors[status] || '#94a3b8';
    const text  = status.replace(/_/g,' ').replace(/\b./g, c => c.toUpperCase());
    const pill  = document.getElementById('statusPill');
    const dot   = document.getElementById('statusDot');
    const stxt  = document.getElementById('statusText');
    if (pill) { pill.style.background = color + '22'; pill.style.color = color; pill.style.borderColor = color + '55'; }
    if (dot)  dot.style.background = color;
    if (stxt) stxt.textContent = text;
    // Show toast
    showToast('🔔 Status updated: ' + text, color);
}

function showToast(msg, color) {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:20px;right:20px;background:${color};color:#fff;padding:12px 20px;border-radius:12px;font-weight:700;font-size:0.9rem;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.3);`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ── Status update API calls ───────────────────────────────────────────────────
async function updateStatus(status, note) {
    if (!confirm(`Mark as "${status.replace(/_/g,' ')}"?`)) return;
    const res = await fetch('/api/mall/delivery/status', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, shipment_id: SHIPMENT_ID, status, note: note||'' })
    });
    const data = await res.json();
    if (data.success) {
        updateStatusUI(status, '');
        showToast('✅ Status updated!', '#22c55e');
    } else {
        alert(data.message || 'Update failed');
    }
}

function adminUpdateStatus() {
    const sel  = document.getElementById('adminStatusSel');
    const note = document.getElementById('adminNote');
    if (sel) updateStatus(sel.value, note?.value || '');
}
</script>
</body>
</html>
