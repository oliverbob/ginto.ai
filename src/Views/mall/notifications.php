<?php
/**
 * notifications.php — Mall notifications full page
 * Variables: $notifications (array), $unreadCount (int), $page (int), $hasMore (bool)
 */
$notifications      = $notifications ?? [];
$unreadCount        = (int)($unreadCount ?? 0);
$page               = (int)($page ?? 1);
$hasMore            = (bool)($hasMore ?? false);
$isLoggedIn         = !empty($_SESSION['user_id']);

$cleanLink = static function ($v): ?string {
    if (!is_string($v)) return null;
    $v = trim($v);
    if ($v === '') return null;
    $l = strtolower($v);
    if (in_array($l, ['null', 'undefined', '/null', '/undefined', '#'], true)) return null;
    if (str_starts_with($l, 'javascript:') || str_starts_with($l, 'data:')) return null;
    return $v;
};
?>
<style>
.notif-page {
    width:100%;
    max-width:860px;
    margin:0 auto;
    padding:12px 12px 90px;
}
.notif-top {
    position:sticky;
    top:0;
    z-index:20;
    margin:0 -12px 12px;
    padding:12px;
    backdrop-filter: blur(12px);
    background:linear-gradient(180deg, rgba(9,16,36,0.96), rgba(9,16,36,0.88));
    border-bottom:1px solid var(--border);
}
.notif-top-head {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
}
.notif-page-title {
    font-size:1.2rem;
    line-height:1.2;
    font-weight:800;
    letter-spacing:0.01em;
}
.notif-subtitle {
    margin-top:4px;
    font-size:0.78rem;
    color:var(--muted);
}
.notif-unread-pill {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:28px;
    height:28px;
    padding:0 10px;
    border-radius:999px;
    font-size:0.78rem;
    font-weight:800;
    color:#dbeafe;
    background:rgba(37,99,235,0.28);
    border:1px solid rgba(96,165,250,0.35);
}
.notif-actions {
    margin-top:10px;
    display:flex;
    gap:8px;
}
.notif-action-btn {
    border:1px solid var(--border);
    background:var(--surface2);
    color:var(--text);
    height:34px;
    border-radius:10px;
    padding:0 12px;
    font-size:0.78rem;
    font-weight:700;
    cursor:pointer;
    transition:background .15s ease, border-color .15s ease;
}
.notif-action-btn:hover {
    background:rgba(59,130,246,0.12);
    border-color:rgba(96,165,250,0.45);
}
.notif-quick-links {
    margin-top:10px;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:8px;
}
.notif-quick-link {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    text-decoration:none;
    color:var(--text);
    border:1px solid var(--border);
    border-radius:11px;
    background:var(--surface2);
    min-height:38px;
    font-size:0.77rem;
    font-weight:700;
}
.notif-list {
    display:flex;
    flex-direction:column;
    gap:9px;
}
.notif-item {
    position:relative;
    display:flex;
    gap:10px;
    align-items:flex-start;
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface);
    padding:12px 12px;
}
.notif-item.unread {
    border-color:rgba(96,165,250,0.4);
    background:linear-gradient(135deg, rgba(37,99,235,0.08), rgba(59,130,246,0.03));
}
.notif-item.unread::before {
    content:'';
    position:absolute;
    left:0;
    top:10px;
    bottom:10px;
    width:3px;
    border-radius:0 4px 4px 0;
    background:#60a5fa;
}
.notif-icon {
    width:34px;
    height:34px;
    border-radius:10px;
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(30,41,59,0.75);
    border:1px solid rgba(148,163,184,0.22);
    font-size:1rem;
}
.notif-body {
    min-width:0;
    flex:1;
}
.notif-msg {
    font-size:0.84rem;
    line-height:1.45;
    font-weight:600;
    color:var(--text);
}
.notif-meta {
    margin-top:5px;
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}
.notif-time {
    font-size:0.72rem;
    color:var(--muted);
}
.notif-state-pill {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:20px;
    padding:0 8px;
    border-radius:999px;
    font-size:0.64rem;
    font-weight:800;
    letter-spacing:0.02em;
    border:1px solid transparent;
}
.notif-state-pill.live {
    color:#bfdbfe;
    background:rgba(37,99,235,0.28);
    border-color:rgba(96,165,250,0.42);
}
.notif-state-pill.read {
    color:#cbd5e1;
    background:rgba(71,85,105,0.25);
    border-color:rgba(100,116,139,0.34);
}
.notif-action {
    display:inline-flex;
    align-items:center;
    font-size:0.72rem;
    font-weight:700;
    color:var(--accent);
    text-decoration:none;
}
.notif-actions-row {
    margin-top:7px;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.notif-mini-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:0 9px;
    border-radius:8px;
    border:1px solid var(--border);
    background:var(--surface2);
    color:var(--text);
    text-decoration:none;
    font-size:0.7rem;
    font-weight:700;
}
.notif-mini-btn:hover {
    border-color:rgba(96,165,250,0.45);
    background:rgba(59,130,246,0.1);
}
.notif-empty {
    border:1px dashed var(--border);
    border-radius:16px;
    text-align:center;
    color:var(--muted);
    padding:48px 18px;
    background:rgba(15,23,42,0.45);
}
.notif-empty-icon {
    font-size:2.2rem;
    margin-bottom:10px;
}
.notif-load-wrap {
    margin-top:14px;
}
.notif-load-more {
    width:100%;
    height:42px;
    border-radius:12px;
    border:1px solid var(--border);
    background:var(--surface2);
    color:var(--text);
    font-size:0.84rem;
    font-weight:700;
    cursor:pointer;
}
@media (min-width: 720px) {
    .notif-page {
        padding:20px 20px 90px;
    }
    .notif-top {
        margin:0 0 14px;
        border:1px solid var(--border);
        border-radius:16px;
        top:10px;
    }
    .notif-page-title {
        font-size:1.45rem;
    }
    .notif-subtitle {
        font-size:0.84rem;
    }
    .notif-msg {
        font-size:0.9rem;
    }
    .notif-quick-links {
        grid-template-columns:repeat(4, minmax(0, 1fr));
    }
}
</style>

<div class="notif-page">
    <div class="notif-top">
        <div class="notif-top-head">
            <div>
                <h1 class="notif-page-title">Mall Notifications</h1>
                <div class="notif-subtitle">Realtime alerts for orders, payouts, inventory and buyer activity.</div>
            </div>
            <?php if ($unreadCount > 0): ?>
            <span class="notif-unread-pill" id="notifUnreadPill"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            <?php endif; ?>
        </div>
        <div class="notif-actions">
            <?php if ($unreadCount > 0): ?>
            <button class="notif-action-btn" id="markAllReadBtn">Mark all read</button>
            <?php endif; ?>
            <button class="notif-action-btn" id="refreshNotifBtn">Refresh</button>
        </div>
        <div class="notif-quick-links">
            <a class="notif-quick-link" href="/wallet/products">🏷 My Products</a>
            <a class="notif-quick-link" href="/mall/orders">📦 My Orders</a>
            <a class="notif-quick-link" href="/marketplace/sellers/orders">🧾 Seller Orders</a>
            <a class="notif-quick-link" href="/wallet">💳 Wallet</a>
        </div>
    </div>

    <div class="notif-list" id="notifList">
        <?php if (empty($notifications)): ?>
        <div class="notif-empty">
            <div class="notif-empty-icon">🔔</div>
            <p>No notifications yet.</p>
            <p style="font-size:0.8rem;margin-top:8px;">As soon as an event happens, it shows up here.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $n): ?>
        <?php
            $type  = $n['type'] ?? '';
            $icon  = '🔔';
            if (str_contains($type, 'order')) $icon = '📦';
            elseif (str_contains($type, 'payment')) $icon = '💳';
            elseif (str_contains($type, 'delivery')) $icon = '🚚';
            elseif (str_contains($type, 'product_listed')) $icon = '🏷️';
            elseif (str_contains($type, 'wallet')) $icon = '💰';
            $isUnread = empty($n['is_read']);
            $ctx = [];
            foreach (['context_json', 'payload', 'meta'] as $field) {
                if (!empty($n[$field])) {
                    $decoded = json_decode((string)$n[$field], true) ?: [];
                    if (is_array($decoded)) $ctx = array_merge($ctx, $decoded);
                }
            }
            $link = $cleanLink($ctx['link'] ?? $ctx['url'] ?? null);
            $buyerLink = $cleanLink($ctx['buyer_link'] ?? null);
            $productLink = $cleanLink($ctx['product_link'] ?? null);
        ?>
        <div class="notif-item <?= $isUnread ? 'unread' : '' ?>" data-id="<?= (int)$n['id'] ?>">
            <div class="notif-icon"><?= $icon ?></div>
            <div class="notif-body">
                <div class="notif-msg"><?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="notif-meta">
                    <div class="notif-time"><?= htmlspecialchars($n['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="notif-state-pill <?= $isUnread ? 'live' : 'read' ?>"><?= $isUnread ? 'LIVE' : 'READ' ?></span>
                    <?php if ($link): ?>
                    <a class="notif-action" href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>">Open</a>
                    <?php endif; ?>
                </div>
                <?php if ($buyerLink || $productLink): ?>
                <div class="notif-actions-row">
                    <?php if ($buyerLink): ?><a class="notif-mini-btn" href="<?= htmlspecialchars($buyerLink, ENT_QUOTES, 'UTF-8') ?>">View Buyer</a><?php endif; ?>
                    <?php if ($productLink): ?><a class="notif-mini-btn" href="<?= htmlspecialchars($productLink, ENT_QUOTES, 'UTF-8') ?>">View Product</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($hasMore): ?>
    <div class="notif-load-wrap">
        <button class="notif-load-more" id="notifLoadMore" data-page="<?= $page + 1 ?>">Load more notifications</button>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;
    const refreshBtn = document.getElementById('refreshNotifBtn');

    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch('/api/mall/notifications/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({all: true, csrf_token: CSRF_TOKEN})
            }).then(function (r) { return r.json(); }).then(function () {
                document.querySelectorAll('.notif-item.unread').forEach(function (el) {
                    el.classList.remove('unread');
                });
                const pill = document.getElementById('notifUnreadPill');
                if (pill) pill.remove();
                markAllBtn.remove();
                // Update header badge
                const badge = document.getElementById('mallNotifyBadge');
                if (badge) { badge.style.display = 'none'; badge.textContent = ''; }
            }).catch(function () {});
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            refreshBtn.disabled = true;
            refreshBtn.textContent = 'Refreshing...';
            fetch('/api/mall/notifications?page=1', {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.success !== true) return;
                const list = document.getElementById('notifList');
                if (!list) return;
                if (!Array.isArray(data.notifications) || data.notifications.length === 0) {
                    list.innerHTML = '<div class="notif-empty"><div class="notif-empty-icon">🔔</div><p>No notifications yet.</p><p style="font-size:0.8rem;margin-top:8px;">As soon as an event happens, it shows up here.</p></div>';
                    return;
                }
                list.innerHTML = '';
                data.notifications.forEach(function (n) {
                    const iconMap = {order:'📦', payment:'💳', delivery:'🚚', product_listed:'🏷️', wallet:'💰'};
                    let icon = '🔔';
                    for (const k in iconMap) { if ((n.type || '').includes(k)) { icon = iconMap[k]; break; } }
                    const isUnread = !!(n.is_unread || (!n.is_read && n.is_read !== 1));
                    const state = isUnread
                        ? '<span class="notif-state-pill live">LIVE</span>'
                        : '<span class="notif-state-pill read">READ</span>';
                    const actions = actionButtonsHtml(n);
                    const div = document.createElement('div');
                    div.className = 'notif-item' + (isUnread ? ' unread' : '');
                    div.dataset.id = n.id;
                    div.innerHTML = '<div class="notif-icon">' + icon + '</div>'
                        + '<div class="notif-body">'
                        + '<div class="notif-msg">' + escHtml(n.message || '') + '</div>'
                        + '<div class="notif-meta"><div class="notif-time">' + escHtml(n.created_at || '') + '</div>'
                        + state
                        + (n.link ? '<a class="notif-action" href="' + escHtml(n.link) + '">Open</a>' : '')
                        + '</div>'
                        + actions
                        + '</div></div>';
                    list.appendChild(div);
                });
                const badge = document.getElementById('mallNotifyBadge');
                if (badge) {
                    const count = parseInt(data.count || 0, 10) || 0;
                    if (count > 0) {
                        badge.style.display = 'flex';
                        badge.textContent = count > 99 ? '99+' : String(count);
                    } else {
                        badge.style.display = 'none';
                        badge.textContent = '';
                    }
                }
            })
            .finally(function () {
                refreshBtn.disabled = false;
                refreshBtn.textContent = 'Refresh';
            });
        });
    }

    const loadMoreBtn = document.getElementById('notifLoadMore');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            const page = parseInt(loadMoreBtn.dataset.page, 10);
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'Loading…';
            fetch('/api/mall/notifications?page=' + page)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const list = document.getElementById('notifList');
                    (data.notifications || []).forEach(function (n) {
                        const iconMap = {order:'📦', payment:'💳', delivery:'🚚', product_listed:'🏷️', wallet:'💰'};
                        let icon = '🔔';
                        for (const k in iconMap) { if ((n.type || '').includes(k)) { icon = iconMap[k]; break; } }
                        const isUnread = !!(n.is_unread || (!n.is_read && n.is_read !== 1));
                        const state = isUnread
                            ? '<span class="notif-state-pill live">LIVE</span>'
                            : '<span class="notif-state-pill read">READ</span>';
                        const actions = actionButtonsHtml(n);
                        const div = document.createElement('div');
                        div.className = 'notif-item' + (isUnread ? ' unread' : '');
                        div.dataset.id = n.id;
                        div.innerHTML = '<div class="notif-icon">' + icon + '</div>'
                            + '<div class="notif-body">'
                            + '<div class="notif-msg">' + escHtml(n.message || '') + '</div>'
                            + '<div class="notif-meta"><div class="notif-time">' + escHtml(n.created_at || '') + '</div>' + state
                            + (n.link ? '<a class="notif-action" href="' + escHtml(n.link) + '">Open</a>' : '') + '</div>'
                            + actions
                            + '</div>';
                        list.appendChild(div);
                    });
                    if (data.has_more) {
                        loadMoreBtn.dataset.page = page + 1;
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = 'Load more notifications';
                    } else {
                        loadMoreBtn.remove();
                    }
                }).catch(function () {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'Load more notifications';
                });
        });
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function actionButtonsHtml(n) {
        function validActionLink(v) {
            if (typeof v !== 'string') return null;
            const t = v.trim();
            if (!t) return null;
            const l = t.toLowerCase();
            if (l === 'null' || l === 'undefined' || l === '/null' || l === '/undefined' || l === '#') return null;
            if (l.startsWith('javascript:') || l.startsWith('data:')) return null;
            return t;
        }

        const links = [];
        const buyerLink = validActionLink(n && n.buyer_link);
        const productLink = validActionLink(n && n.product_link);
        if (buyerLink) {
            links.push('<a class="notif-mini-btn" href="' + escHtml(String(buyerLink)) + '">View Buyer</a>');
        }
        if (productLink) {
            links.push('<a class="notif-mini-btn" href="' + escHtml(String(productLink)) + '">View Product</a>');
        }
        return links.length ? ('<div class="notif-actions-row">' + links.join('') + '</div>') : '';
    }
}());
</script>
