<?php
$title = 'Logistics Dashboard';
$stats    = $logistics_stats ?? [];
$shipments = $all_shipments   ?? [];
$riders    = $all_riders       ?? [];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/../parts/head.php'; ?>
<style>
.stat-card { background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;text-align:center; }
.stat-num  { font-size:2rem;font-weight:800; }
.stat-lbl  { font-size:0.78rem;color:var(--muted);margin-top:4px; }
.tbl-wrap  { overflow-x:auto; }
table { width:100%;border-collapse:collapse;font-size:0.85rem; }
th,td { padding:10px 14px;text-align:left;border-bottom:1px solid var(--border); }
th { background:var(--surface2);font-weight:700;white-space:nowrap; }
tr:hover td { background:var(--surface2); }
.badge { display:inline-block;padding:3px 10px;border-radius:999px;font-size:0.72rem;font-weight:700; }
.filter-row { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px; }
.filter-row select, .filter-row input { background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:9px 14px;font-size:0.84rem; }
</style>
<body>
<?php include __DIR__ . '/../parts/header.php'; ?>
<div class="container wrapper">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <main class="main-content" style="padding:24px 0;">
        <div style="max-width:1100px;margin:0 auto;padding:0 16px;">
            <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:6px;">📦 Logistics Dashboard</h1>
            <p style="color:var(--muted);font-size:0.87rem;margin-bottom:22px;">Overview of all deliveries, riders, and shipment statuses.</p>

            <!-- Stats row -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:26px;">
                <div class="stat-card">
                    <div class="stat-num" style="color:#f59e0b;"><?= (int)($stats['pending'] ?? 0) ?></div>
                    <div class="stat-lbl">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#3b82f6;"><?= (int)($stats['in_transit'] ?? 0) ?></div>
                    <div class="stat-lbl">In Transit</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#8b5cf6;"><?= (int)($stats['out_for_delivery'] ?? 0) ?></div>
                    <div class="stat-lbl">Out for Delivery</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#22c55e;"><?= (int)($stats['delivered'] ?? 0) ?></div>
                    <div class="stat-lbl">Delivered</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#ef4444;"><?= (int)($stats['failed'] ?? 0) ?></div>
                    <div class="stat-lbl">Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?= (int)($stats['total'] ?? 0) ?></div>
                    <div class="stat-lbl">Total Shipments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#3b82f6;"><?= (int)($stats['active_riders'] ?? 0) ?></div>
                    <div class="stat-lbl">Active Riders</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-row">
                <select id="filterStatus" onchange="applyFilter()">
                    <option value="">All Statuses</option>
                    <?php foreach(['pending','ready_for_pickup','picked_up','in_transit','out_for_delivery','delivered','failed_delivery','returned'] as $st): ?>
                    <option value="<?= $st ?>"><?= ucwords(str_replace('_',' ',$st)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="filterSearch" placeholder="Search order code, token, buyer…" oninput="applyFilter()" style="flex:1;min-width:200px;">
            </div>

            <!-- Shipments table -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;margin-bottom:28px;overflow:hidden;">
                <div style="padding:16px 20px;font-weight:700;font-size:0.95rem;border-bottom:1px solid var(--border);">All Shipments</div>
                <div class="tbl-wrap">
                    <table id="shipmentsTable">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Status</th>
                                <th>Buyer</th>
                                <th>Seller</th>
                                <th>Rider</th>
                                <th>Token</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($shipments)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--muted);">No shipments yet.</td></tr>
                        <?php else: ?>
                        <?php
                        $statusColors = ['pending'=>'#94a3b8','ready_for_pickup'=>'#f59e0b','picked_up'=>'#3b82f6','in_transit'=>'#3b82f6','out_for_delivery'=>'#8b5cf6','delivered'=>'#22c55e','failed_delivery'=>'#ef4444','returned'=>'#f97316'];
                        foreach ($shipments as $s):
                            $sc = $statusColors[$s['status']] ?? '#94a3b8';
                        ?>
                        <tr data-status="<?= htmlspecialchars($s['status'], ENT_QUOTES, 'UTF-8') ?>"
                            data-search="<?= htmlspecialchars(strtolower(($s['order_code']??'') . ' ' . ($s['tracking_token']??'') . ' ' . ($s['buyer_name']??'')), ENT_QUOTES, 'UTF-8') ?>">
                            <td style="font-weight:600;"><?= htmlspecialchars($s['order_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge" style="background:<?= $sc ?>22;color:<?= $sc ?>;border:1px solid <?= $sc ?>55;">
                                    <?= htmlspecialchars(ucwords(str_replace('_',' ',$s['status'])), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($s['buyer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($s['seller_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (!empty($s['rider_name'])): ?>
                                    <?= htmlspecialchars($s['rider_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <select onchange="assignRider(<?= (int)$s['id'] ?>, this.value)" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:5px 10px;font-size:0.8rem;">
                                        <option value="">— Assign Rider —</option>
                                        <?php foreach ($riders as $r): ?>
                                        <option value="<?= (int)$r['user_id'] ?>"><?= htmlspecialchars($r['rider_name'] ?? $r['username'] ?? 'Rider', ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.75rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($s['tracking_token'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="font-size:0.78rem;white-space:nowrap;"><?= htmlspecialchars($s['created_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="white-space:nowrap;">
                                <a href="/mall/delivery/track/<?= htmlspecialchars($s['tracking_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   style="color:var(--accent);font-weight:700;font-size:0.8rem;text-decoration:none;">Track →</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Riders table -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:28px;">
                <div style="padding:16px 20px;font-weight:700;font-size:0.95rem;border-bottom:1px solid var(--border);">Registered Riders</div>
                <div class="tbl-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Vehicle</th>
                                <th>Plate</th>
                                <th>Available</th>
                                <th>Last GPS</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($riders)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--muted);">No riders registered yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($riders as $r): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($r['rider_name'] ?? $r['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['vehicle_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['plate_number'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge" style="background:<?= !empty($r['is_available']) ? '#22c55e22' : '#94a3b822' ?>;color:<?= !empty($r['is_available']) ? '#22c55e' : '#94a3b8' ?>;">
                                    <?= !empty($r['is_available']) ? 'Available' : 'Offline' ?>
                                </span>
                            </td>
                            <td style="font-size:0.78rem;">
                                <?php if (!empty($r['last_lat'])): ?>
                                <?= round((float)$r['last_lat'],5) ?>, <?= round((float)$r['last_lng'],5) ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem;white-space:nowrap;"><?= htmlspecialchars($r['last_seen_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
<?php include __DIR__ . '/../parts/footer.php'; ?>

<script>
const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;

function applyFilter() {
    const status = document.getElementById('filterStatus').value.toLowerCase();
    const search = document.getElementById('filterSearch').value.toLowerCase();
    document.querySelectorAll('#shipmentsTable tbody tr').forEach(row => {
        const matchStatus = !status || row.dataset.status === status;
        const matchSearch = !search || (row.dataset.search || '').includes(search);
        row.style.display = (matchStatus && matchSearch) ? '' : 'none';
    });
}

async function assignRider(shipmentId, riderId) {
    if (!riderId) return;
    const res = await fetch('/api/mall/delivery/assign-rider', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, shipment_id: shipmentId, rider_id: riderId })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message || 'Assignment failed');
}
</script>
</body>
</html>
