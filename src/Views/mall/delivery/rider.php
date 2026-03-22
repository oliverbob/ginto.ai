<?php
$title = 'Rider Dashboard';
$activeShipments = $active_shipments ?? [];
$riderProfile    = $rider_profile ?? [];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/../parts/head.php'; ?>
<style>
.shipment-card { background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;margin-bottom:14px;cursor:pointer;transition:border-color .15s,box-shadow .15s; }
.shipment-card:hover { border-color:var(--accent);box-shadow:0 4px 20px rgba(0,0,0,.15); }
.gps-btn { background:var(--accent);color:#fff;border:none;border-radius:12px;padding:14px 28px;font-weight:800;font-size:1rem;cursor:pointer;width:100%;transition:opacity .2s; }
.gps-btn:disabled { opacity:.5;cursor:default; }
.avail-toggle { display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--surface);border:1px solid var(--border);border-radius:14px;cursor:pointer;width:100%;justify-content:space-between;margin-bottom:14px; }
.toggle-sw { width:48px;height:26px;background:var(--border);border-radius:999px;position:relative;transition:background .2s; }
.toggle-knob { width:20px;height:20px;background:#fff;border-radius:50%;position:absolute;top:3px;left:3px;transition:left .2s; }
.stat-chip { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 18px;text-align:center; }
</style>
<body>
<?php include __DIR__ . '/../parts/header.php'; ?>
<div class="container wrapper">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <main class="main-content" style="padding:24px 0;">
        <div style="max-width:680px;margin:0 auto;padding:0 16px;">
            <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:6px;">🛵 Rider Dashboard</h1>
            <p style="color:var(--muted);font-size:0.88rem;margin-bottom:24px;">Manage your deliveries and share your live GPS location.</p>

            <!-- Availability toggle + GPS -->
            <div id="availToggle" class="avail-toggle" onclick="toggleAvailability()">
                <div>
                    <div style="font-weight:700;font-size:0.95rem;">Availability Status</div>
                    <div id="availLabel" style="font-size:0.82rem;color:var(--muted);"><?= !empty($riderProfile['is_available']) ? '🟢 Available for delivery' : '⚫ Not available' ?></div>
                </div>
                <div class="toggle-sw" id="toggleSw" style="background:<?= !empty($riderProfile['is_available']) ? 'var(--accent)' : 'var(--border)' ?>;">
                    <div class="toggle-knob" id="toggleKnob" style="left:<?= !empty($riderProfile['is_available']) ? '25px' : '3px' ?>;"></div>
                </div>
            </div>

            <!-- GPS sharing -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:20px;">
                <div style="font-weight:700;margin-bottom:6px;font-size:0.95rem;">📡 GPS Tracking</div>
                <div id="gpsStatus" style="font-size:0.84rem;color:var(--muted);margin-bottom:14px;">GPS sharing is off</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                    <div class="stat-chip"><div style="font-size:1.1rem;font-weight:800;" id="gpsLat">—</div><div style="font-size:0.75rem;color:var(--muted);">Latitude</div></div>
                    <div class="stat-chip"><div style="font-size:1.1rem;font-weight:800;" id="gpsLng">—</div><div style="font-size:0.75rem;color:var(--muted);">Longitude</div></div>
                </div>
                <button id="gpsBtn" class="gps-btn" onclick="toggleGps()">▶ Start GPS Sharing</button>
            </div>

            <!-- Active deliveries -->
            <div style="font-weight:700;font-size:1rem;margin-bottom:12px;">📦 Active Deliveries (<?= count($activeShipments) ?>)</div>
            <?php if (empty($activeShipments)): ?>
            <div style="text-align:center;padding:40px 20px;background:var(--surface);border:1px solid var(--border);border-radius:16px;color:var(--muted);font-size:0.9rem;">
                No active deliveries assigned to you yet.
            </div>
            <?php else: ?>
            <?php foreach ($activeShipments as $s): ?>
            <div class="shipment-card" onclick="window.location='/mall/delivery/track/<?= htmlspecialchars($s['tracking_token'], ENT_QUOTES, 'UTF-8') ?>'">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div>
                        <div style="font-weight:700;font-size:0.92rem;"><?= htmlspecialchars($s['order_code'] ?? 'Order #' . $s['order_id'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="color:var(--muted);font-size:0.8rem;margin-top:3px;"><?= htmlspecialchars($s['delivery_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php
                    $clrs = ['pending'=>'#94a3b8','ready_for_pickup'=>'#f59e0b','picked_up'=>'#3b82f6','in_transit'=>'#3b82f6','out_for_delivery'=>'#8b5cf6','delivered'=>'#22c55e','failed_delivery'=>'#ef4444','returned'=>'#f97316'];
                    $sc = $clrs[$s['status']] ?? '#94a3b8';
                    ?>
                    <span style="background:<?= $sc ?>22;color:<?= $sc ?>;border:1px solid <?= $sc ?>55;border-radius:999px;padding:4px 12px;font-size:0.75rem;font-weight:700;white-space:nowrap;">
                        <?= htmlspecialchars(ucwords(str_replace('_',' ',$s['status'])), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div style="margin-top:10px;font-size:0.78rem;color:var(--muted);">Token: <?= htmlspecialchars($s['tracking_token'], ENT_QUOTES, 'UTF-8') ?></div>
                <!-- Per-shipment quick actions -->
                <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;" onclick="event.stopPropagation()">
                    <?php if (in_array($s['status'],['ready_for_pickup','pending'])): ?>
                    <button onclick="setStatus(<?= (int)$s['id'] ?>, 'picked_up')" style="font-size:0.78rem;padding:6px 14px;background:rgba(59,130,246,.15);color:#3b82f6;border:1px solid #3b82f666;border-radius:8px;cursor:pointer;font-weight:700;">Picked Up</button>
                    <?php endif; ?>
                    <?php if (in_array($s['status'],['picked_up','in_transit'])): ?>
                    <button onclick="setStatus(<?= (int)$s['id'] ?>, 'out_for_delivery')" style="font-size:0.78rem;padding:6px 14px;background:rgba(139,92,246,.15);color:#8b5cf6;border:1px solid #8b5cf666;border-radius:8px;cursor:pointer;font-weight:700;">Out for Delivery</button>
                    <?php endif; ?>
                    <?php if (in_array($s['status'],['picked_up','in_transit','out_for_delivery'])): ?>
                    <button onclick="setStatus(<?= (int)$s['id'] ?>, 'delivered')" style="font-size:0.78rem;padding:6px 14px;background:rgba(34,197,94,.15);color:#22c55e;border:1px solid #22c55e66;border-radius:8px;cursor:pointer;font-weight:700;">Delivered</button>
                    <button onclick="setStatus(<?= (int)$s['id'] ?>, 'failed_delivery')" style="font-size:0.78rem;padding:6px 14px;background:rgba(239,68,68,.15);color:#ef4444;border:1px solid #ef444466;border-radius:8px;cursor:pointer;font-weight:700;">Failed</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Rider profile form -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;margin-top:20px;">
                <div style="font-weight:700;margin-bottom:14px;font-size:0.95rem;">👤 Rider Profile</div>
                <form onsubmit="saveProfile(event)">
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.83rem;color:var(--muted);">Vehicle Type</label>
                        <select name="vehicle_type" id="vehType" style="width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:10px 14px;margin-top:4px;font-size:0.88rem;">
                            <?php foreach(['motorcycle','bicycle','car','van','truck','walk'] as $v): ?>
                            <option value="<?= $v ?>" <?= ($riderProfile['vehicle_type']??'')===$v?'selected':'' ?>><?= ucfirst($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="font-size:0.83rem;color:var(--muted);">Plate / ID Number</label>
                        <input type="text" name="plate_number" value="<?= htmlspecialchars($riderProfile['plate_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="e.g. ABC-1234" style="width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:10px 14px;margin-top:4px;font-size:0.88rem;box-sizing:border-box;">
                    </div>
                    <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:10px;padding:11px 24px;font-weight:700;cursor:pointer;font-size:0.88rem;">Save Profile</button>
                </form>
            </div>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../parts/footer.php'; ?>

<script>
const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;
let isAvailable = <?= !empty($riderProfile['is_available']) ? 'true' : 'false' ?>;
let gpsActive   = false;
let gpsWatchId  = null;
let activeShipmentId = <?= json_encode((int)(!empty($activeShipments) ? $activeShipments[0]['id'] : 0)) ?>;

// ── Availability toggle ───────────────────────────────────────────────────
async function toggleAvailability() {
    isAvailable = !isAvailable;
    document.getElementById('toggleSw').style.background   = isAvailable ? 'var(--accent)' : 'var(--border)';
    document.getElementById('toggleKnob').style.left       = isAvailable ? '25px' : '3px';
    document.getElementById('availLabel').textContent      = isAvailable ? '🟢 Available for delivery' : '⚫ Not available';
    await fetch('/api/mall/delivery/rider/profile', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, is_available: isAvailable ? 1 : 0 })
    });
}

// ── GPS sharing ───────────────────────────────────────────────────────────
const WS_PROTO = location.protocol === 'https:' ? 'wss:' : 'ws:';
let ws, wsReady = false;
let sessionToken = (document.cookie.match(/PHPSESSID=([^;]+)/)||[])[1] || '';

function connectWs() {
    ws = new WebSocket(`${WS_PROTO}//${location.host}/mall-notify`);
    ws.onopen = () => {
        wsReady = true;
        ws.send(JSON.stringify({ type:'auth', session_token: sessionToken, device:'rider' }));
    };
    ws.onclose = () => { wsReady = false; setTimeout(connectWs, 4000); };
    ws.onerror = () => ws.close();
}
connectWs();

function toggleGps() {
    if (gpsActive) stopGps();
    else startGps();
}

function startGps() {
    if (!navigator.geolocation) {
        alert('Geolocation not supported on this device.'); return;
    }
    gpsActive = true;
    document.getElementById('gpsBtn').textContent = '⏹ Stop GPS Sharing';
    document.getElementById('gpsBtn').style.background = '#ef4444';
    document.getElementById('gpsStatus').textContent = '📡 Live GPS sharing active…';
    gpsWatchId = navigator.geolocation.watchPosition(onGps, onGpsErr, {
        enableHighAccuracy: true, maximumAge: 3000, timeout: 10000
    });
}

function stopGps() {
    gpsActive = false;
    if (gpsWatchId !== null) { navigator.geolocation.clearWatch(gpsWatchId); gpsWatchId = null; }
    document.getElementById('gpsBtn').textContent = '▶ Start GPS Sharing';
    document.getElementById('gpsBtn').style.background = 'var(--accent)';
    document.getElementById('gpsStatus').textContent = 'GPS sharing is off';
}

function onGps(pos) {
    const {latitude:lat, longitude:lng, accuracy, speed, heading:bearing} = pos.coords;
    document.getElementById('gpsLat').textContent = lat.toFixed(6);
    document.getElementById('gpsLng').textContent = lng.toFixed(6);
    // Send via WS if connected
    if (wsReady && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ type:'gps', lat, lng, accuracy, speed:speed||0, bearing:bearing||0, shipment_id: activeShipmentId }));
    }
    // Also send via REST as backup
    fetch('/api/mall/delivery/gps', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, lat, lng, accuracy, speed:speed||0, bearing:bearing||0, shipment_id: activeShipmentId })
    }).catch(() => {});
}

function onGpsErr(err) {
    document.getElementById('gpsStatus').textContent = '⚠️ GPS error: ' + err.message;
}

// ── Status update ──────────────────────────────────────────────────────────
async function setStatus(shipmentId, status) {
    if (!confirm(`Mark shipment as "${status.replace(/_/g,' ')}"?`)) return;
    const res = await fetch('/api/mall/delivery/status', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, shipment_id: shipmentId, status })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message || 'Update failed');
}

// ── Profile save ───────────────────────────────────────────────────────────
async function saveProfile(e) {
    e.preventDefault();
    const form = e.target;
    const res = await fetch('/api/mall/delivery/rider/profile', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            vehicle_type: form.vehicle_type.value,
            plate_number: form.plate_number.value
        })
    });
    const data = await res.json();
    if (data.success) {
        const btn = form.querySelector('button[type=submit]');
        btn.textContent = '✅ Saved!';
        setTimeout(() => btn.textContent = 'Save Profile', 2000);
    }
}
</script>
</body>
</html>
