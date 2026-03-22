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
?>
<style>
.notif-page { max-width:720px; margin:32px auto 80px; padding:0 18px; }
.notif-page-header {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    margin-bottom:24px; flex-wrap:wrap;
}
.notif-page-title { font-size:1.5rem; font-weight:800; }
.notif-unread-pill {
    padding:4px 12px; border-radius:999px;
    background:rgba(99,102,241,0.12); color:var(--accent);
    font-size:0.78rem; font-weight:700;
}
.notif-mark-btn {
    padding:8px 18px; border-radius:10px; border:1px solid var(--border);
    background:var(--surface2); color:var(--text); font-size:0.82rem;
    font-weight:600; cursor:pointer; transition:background .15s;
}
.notif-mark-btn:hover { background:var(--border); }
.notif-list { display:flex; flex-direction:column; gap:10px; }
.notif-item {
    display:flex; gap:14px; align-items:flex-start;
    padding:14px 16px; border-radius:14px;
    background:var(--surface); border:1px solid var(--border);
    transition:background .15s; position:relative;
}
.notif-item.unread { background:rgba(99,102,241,0.06); border-color:rgba(99,102,241,0.25); }
.notif-item.unread::before {
    content:''; position:absolute; left:0; top:50%; transform:translateY(-50%);
    width:4px; height:60%; background:var(--accent); border-radius:0 4px 4px 0;
}
.notif-icon {
    width:38px; height:38px; border-radius:10px; flex-shrink:0;
    background:rgba(99,102,241,0.10); display:flex; align-items:center;
    justify-content:center; font-size:1.1rem;
}
.notif-body { flex:1; min-width:0; }
.notif-msg { font-size:0.875rem; font-weight:500; line-height:1.55; }
.notif-time { font-size:0.72rem; color:var(--muted); margin-top:4px; }
.notif-action { font-size:0.75rem; color:var(--accent); text-decoration:none; font-weight:600; margin-top:6px; display:inline-block; }
.notif-empty { text-align:center; padding:64px 20px; color:var(--muted); }
.notif-empty-icon { font-size:3rem; margin-bottom:14px; }
.notif-load-more {
    margin-top:18px; width:100%; padding:12px;
    border-radius:12px; border:1px solid var(--border);
    background:var(--surface2); color:var(--text); font-size:0.875rem;
    font-weight:600; cursor:pointer; transition:background .15s;
}
.notif-load-more:hover { background:var(--border); }
</style>

<div class="notif-page">
    <div class="notif-page-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <h1 class="notif-page-title">Notifications</h1>
            <?php if ($unreadCount > 0): ?>
            <span class="notif-unread-pill"><?= $unreadCount > 99 ? '99+' : $unreadCount ?> unread</span>
            <?php endif; ?>
        </div>
        <?php if ($unreadCount > 0): ?>
        <button class="notif-mark-btn" id="markAllReadBtn">Mark all as read</button>
        <?php endif; ?>
    </div>

    <div class="notif-list" id="notifList">
        <?php if (empty($notifications)): ?>
        <div class="notif-empty">
            <div class="notif-empty-icon">🔔</div>
            <p>No notifications yet.</p>
            <p style="font-size:0.82rem;margin-top:8px;">You'll see order updates, payment confirmations, and delivery alerts here.</p>
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
            $payload  = [];
            if (!empty($n['payload'])) {
                $payload = json_decode($n['payload'], true) ?: [];
            }
            $link = $payload['link'] ?? null;
        ?>
        <div class="notif-item <?= $isUnread ? 'unread' : '' ?>" data-id="<?= (int)$n['id'] ?>">
            <div class="notif-icon"><?= $icon ?></div>
            <div class="notif-body">
                <div class="notif-msg"><?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="notif-time"><?= htmlspecialchars($n['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($link): ?>
                <a class="notif-action" href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>">View details →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($hasMore): ?>
    <button class="notif-load-more" id="notifLoadMore" data-page="<?= $page + 1 ?>">Load more notifications</button>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;

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
                const pill = document.querySelector('.notif-unread-pill');
                if (pill) pill.remove();
                markAllBtn.remove();
                // Update header badge
                const badge = document.getElementById('mallNotifyBadge');
                if (badge) { badge.style.display = 'none'; badge.textContent = ''; }
            }).catch(function () {});
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
                        const div = document.createElement('div');
                        div.className = 'notif-item' + (n.is_read ? '' : ' unread');
                        div.dataset.id = n.id;
                        div.innerHTML = '<div class="notif-icon">' + icon + '</div>'
                            + '<div class="notif-body">'
                            + '<div class="notif-msg">' + escHtml(n.message || '') + '</div>'
                            + '<div class="notif-time">' + escHtml(n.created_at || '') + '</div>'
                            + (n.link ? '<a class="notif-action" href="' + escHtml(n.link) + '">View details →</a>' : '')
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
}());
</script>
