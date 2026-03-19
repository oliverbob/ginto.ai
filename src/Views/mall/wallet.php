<?php
$wallet = $wallet ?? [];
$walletTransactions = $wallet_transactions ?? [];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<style>
.gw-card {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: 30px 28px 28px;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #312e81 75%, #4338ca 100%);
    border: 1px solid rgba(99,102,241,0.35);
    box-shadow: 0 12px 52px rgba(67,56,202,0.35), 0 2px 8px rgba(0,0,0,0.5);
}
.gw-card::before {
    content: '';
    position: absolute;
    top: -60px; right: -50px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(167,139,250,0.2) 0%, transparent 70%);
    pointer-events: none;
}
.gw-card::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -50px;
    width: 300px; height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(250,204,21,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.gw-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    font-weight: 800;
    color: rgba(199,210,254,0.75);
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    padding: 4px 10px;
}
.gw-balance {
    font-size: 2.8rem;
    font-weight: 900;
    line-height: 1;
    color: #fff;
    letter-spacing: -0.04em;
    margin: 14px 0 4px;
}
.gw-balance-sub {
    font-size: 0.76rem;
    color: rgba(199,210,254,0.55);
    font-weight: 600;
    letter-spacing: 0.04em;
    margin-bottom: 22px;
}
.gw-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 12px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    border: none;
    letter-spacing: 0.01em;
    transition: all 0.18s ease;
    white-space: nowrap;
}
.gw-btn-ghost {
    background: rgba(255,255,255,0.09);
    color: rgba(255,255,255,0.88);
    border: 1px solid rgba(255,255,255,0.14);
}
.gw-btn-ghost:hover {
    background: rgba(255,255,255,0.16);
    border-color: rgba(255,255,255,0.24);
    color: #fff;
    transform: translateY(-1px);
}
.gw-btn-gold {
    background: linear-gradient(135deg, #facc15, #f59e0b);
    color: #0f172a;
    font-weight: 800;
    box-shadow: 0 4px 16px rgba(245,158,11,0.4);
}
.gw-btn-gold:hover {
    background: linear-gradient(135deg, #fde047, #facc15);
    transform: translateY(-1px);
    box-shadow: 0 6px 22px rgba(245,158,11,0.5);
}
.gw-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: 20px 0;
}
.topup-panel {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 24px;
    padding: 22px 24px;
    overflow: hidden;
}
.topup-method-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 13px 8px;
    border-radius: 14px;
    background: var(--surface2);
    border: 2px solid var(--border);
    color: var(--text);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
    line-height: 1.2;
}
.topup-method-btn .sub {
    font-size: 0.67rem;
    font-weight: 500;
    opacity: 0.6;
}
.topup-method-btn.is-selected {
    background: rgba(99,102,241,0.12);
    border-color: rgba(99,102,241,0.6);
    color: #a5b4fc;
}
.topup-method-btn:hover:not(.is-selected) {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.18);
}
.pf-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1.5px solid rgba(255,255,255,0.16);
    background: #101a2f;
    color: #e9efff;
    font-size: 0.92rem;
    box-sizing: border-box;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.pf-input::placeholder { color: rgba(233,239,255,0.56); }
.pf-input:focus { outline: none; border-color: rgba(214,180,75,0.75); box-shadow: 0 0 0 3px rgba(214,180,75,0.14); }
.pf-input:-webkit-autofill,
.pf-input:-webkit-autofill:hover,
.pf-input:-webkit-autofill:focus {
    -webkit-text-fill-color: #e9efff;
    -webkit-box-shadow: 0 0 0 1000px #101a2f inset;
    transition: background-color 5000s ease-in-out 0s;
}
.txn-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 13px 15px;
    border-radius: 16px;
    background: var(--surface2);
    border: 1px solid var(--border);
    transition: background 0.15s;
}
.txn-row:hover { background: rgba(255,255,255,0.04); }
.txn-icon {
    width: 40px; height: 40px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.txn-credit .txn-icon { background: rgba(34,197,94,0.12); color: #4ade80; }
.txn-debit .txn-icon { background: rgba(239,68,68,0.12); color: #f87171; }
</style>

<section style="max-width:1200px;margin:30px auto 72px;padding:0 18px;display:grid;grid-template-columns:minmax(320px,0.9fr) minmax(0,1.1fr);gap:20px;align-items:start;">
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Premium Wallet Card -->
        <div class="gw-card">
            <div style="position:relative;z-index:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="gw-chip">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#a5b4fc" opacity="0.7"/></svg>
                        Ginto Wallet
                    </span>
                    <span style="font-size:0.68rem;color:rgba(199,210,254,0.45);font-weight:600;letter-spacing:0.06em;">ACTIVE</span>
                </div>
                <div class="gw-balance">₱<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></div>
                <div class="gw-balance-sub">Available balance</div>
                <div class="gw-divider"></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
                    <a href="/mall/checkout" class="gw-btn gw-btn-ghost">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Checkout
                    </a>
                    <a href="/mall/orders" class="gw-btn gw-btn-ghost">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        My Orders
                    </a>
                    <button type="button" class="gw-btn gw-btn-gold" id="toggleTopupBtn">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 5v14M5 12h14"/></svg>
                        Top Up
                    </button>
                </div>
            </div>
        </div>

        <!-- Top Up Panel (toggle) -->
        <div class="topup-panel" id="topupPanel" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    <h2 style="margin:0 0 2px;font-size:1rem;font-weight:800;">Add Funds</h2>
                    <p style="margin:0;font-size:0.76rem;color:var(--muted);">Choose amount and payment method.</p>
                </div>
                <button type="button" id="closeTopupBtn" style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--muted);cursor:pointer;font-size:0.85rem;line-height:1;">✕</button>
            </div>
            <div style="margin-bottom:14px;padding:11px 12px;border-radius:12px;background:rgba(214,180,75,0.08);border:1px solid rgba(214,180,75,0.24);font-size:0.78rem;color:#f3ddb0;line-height:1.55;">
                PayMongo top-ups (QR or card) include a fixed fee of ₱25.00 per transaction. Wallet funds are purchase-only and cannot be withdrawn.
            </div>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;">Amount (₱)</span>
                    <input id="walletTopupAmount" type="number" min="1" step="0.01" class="pf-input" placeholder="500.00">
                </label>
                <div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:9px;">Payment Method</div>
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;">
                        <button type="button" class="topup-method-btn is-selected wallet-method-card" data-method="ginto_pay_qr">
                            GCash / QR
                            <span class="sub">Ginto Pay</span>
                        </button>
                        <button type="button" class="topup-method-btn wallet-method-card" data-method="ginto_pay_card">
                            Credit / Debit
                            <span class="sub">via PayMongo</span>
                        </button>
                        <button type="button" class="topup-method-btn wallet-method-card" data-method="paypal">
                            PayPal
                            <span class="sub">International</span>
                        </button>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid var(--border);font-size:0.76rem;">
                    <div>
                        <div style="color:var(--muted);">You pay</div>
                        <div id="topupGross" style="font-weight:800;color:var(--text);margin-top:3px;">₱0.00</div>
                    </div>
                    <div>
                        <div style="color:var(--muted);">Fee</div>
                        <div id="topupFee" style="font-weight:800;color:#fca5a5;margin-top:3px;">₱0.00</div>
                    </div>
                    <div>
                        <div style="color:var(--muted);">Wallet credit</div>
                        <div id="topupCredit" style="font-weight:800;color:#86efac;margin-top:3px;">₱0.00</div>
                    </div>
                </div>
                <div id="walletTopupError" style="display:none;padding:12px 14px;border-radius:13px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fecaca;font-size:0.85rem;"></div>
                <div id="walletTopupInfo" style="display:none;padding:12px 14px;border-radius:13px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);color:#c7d2fe;font-size:0.85rem;"></div>
                <div id="walletTopupQr" style="display:none;text-align:center;padding:20px;border-radius:18px;border:1px dashed var(--border);background:var(--surface2);"></div>
                <button type="button" id="walletTopupBtn" class="btn btn-primary" style="border-radius:14px;font-size:0.9rem;font-weight:800;padding:12px 18px;">Confirm Top Up</button>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
            <div>
                <h2 style="margin:0 0 2px;font-size:1rem;font-weight:800;">Wallet Ledger</h2>
                <p style="margin:0;font-size:0.77rem;color:var(--muted);">All wallet activity</p>
            </div>
            <span style="font-size:0.73rem;color:var(--muted);background:var(--surface2);padding:5px 11px;border-radius:9px;border:1px solid var(--border);font-weight:600;">Newest first</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php if (!empty($walletTransactions)): ?>
                <?php foreach ($walletTransactions as $row): ?>
                <?php $isCredit = ($row['direction'] ?? '') === 'credit'; ?>
                <div class="txn-row txn-<?= $isCredit ? 'credit' : 'debit' ?>">
                    <div class="txn-icon"><?= $isCredit ? '↑' : '↓' ?></div>
                    <div>
                        <div style="font-size:0.88rem;font-weight:700;"><?= htmlspecialchars($row['description'] ?? ucfirst((string)($row['type'] ?? 'Transaction')), ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="font-size:0.73rem;color:var(--muted);margin-top:3px;"><?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <span style="text-transform:capitalize;"><?= htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.92rem;font-weight:800;color:<?= $isCredit ? '#4ade80' : '#f87171' ?>;"><?= $isCredit ? '+' : '-' ?>₱<?= number_format((float)($row['amount'] ?? 0), 2) ?></div>
                        <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">bal ₱<?= number_format((float)($row['balance_after'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="padding:48px 18px;border-radius:18px;background:var(--surface2);border:1px dashed var(--border);text-align:center;">
                <div style="font-size:2.2rem;margin-bottom:10px;opacity:0.3;">₱</div>
                <div style="font-size:0.88rem;color:var(--muted);font-weight:600;">No transactions yet</div>
                <div style="font-size:0.77rem;color:var(--muted);margin-top:4px;opacity:0.7;">Top up your wallet to get started.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    const csrfToken = <?= json_encode($csrf_token ?? '') ?>;
    const amountInput = document.getElementById('walletTopupAmount');
    const topupBtn = document.getElementById('walletTopupBtn');
    const errorBox = document.getElementById('walletTopupError');
    const infoBox = document.getElementById('walletTopupInfo');
    const qrWrap = document.getElementById('walletTopupQr');
    const topupGrossEl = document.getElementById('topupGross');
    const topupFeeEl = document.getElementById('topupFee');
    const topupCreditEl = document.getElementById('topupCredit');
    const topupPanel = document.getElementById('topupPanel');
    const toggleTopupBtn = document.getElementById('toggleTopupBtn');
    const closeTopupBtn = document.getElementById('closeTopupBtn');
    const methodButtons = Array.from(document.querySelectorAll('.wallet-method-card'));
    let selectedMethod = 'ginto_pay_qr';
    let currentSessionRef = '';
    let statusPoll = null;

    toggleTopupBtn.addEventListener('click', function () {
        const isOpen = topupPanel.style.display !== 'none';
        topupPanel.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            setTimeout(function () { topupPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 50);
        }
    });

    closeTopupBtn.addEventListener('click', function () {
        topupPanel.style.display = 'none';
    });

    function setError(message) {
        errorBox.style.display = message ? 'block' : 'none';
        errorBox.textContent = message || '';
    }

    function setInfo(message) {
        infoBox.style.display = message ? 'block' : 'none';
        infoBox.textContent = message || '';
    }

    function formatPrice(value) {
        return '₱' + Number(value || 0).toFixed(2);
    }

    function isPayMongoMethod(method) {
        return method === 'ginto_pay_qr' || method === 'ginto_pay_card';
    }

    function updateTopupBreakdown() {
        const gross = Math.max(0, Number(amountInput.value || 0));
        const fee = isPayMongoMethod(selectedMethod) && gross > 0 ? 25 : 0;
        const credit = Math.max(0, gross - fee);
        topupGrossEl.textContent = formatPrice(gross);
        topupFeeEl.textContent = formatPrice(fee);
        topupCreditEl.textContent = formatPrice(credit);
    }

    function beginStatusPoll() {
        if (!currentSessionRef) return;
        if (statusPoll) window.clearInterval(statusPoll);
        statusPoll = window.setInterval(async function () {
            try {
                const response = await fetch('/api/mall/checkout/status?session_ref=' + encodeURIComponent(currentSessionRef), {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await response.json();
                if (json.status === 'completed') {
                    window.clearInterval(statusPoll);
                    setInfo('Top-up confirmed and posted to your wallet. Refreshing ledger...');
                    window.location.reload();
                }
            } catch (_) {}
        }, 5000);
    }

    async function api(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(Object.assign({ csrf_token: csrfToken }, body || {})),
        });
        const json = await response.json();
        if (!response.ok || json.success === false) {
            throw new Error(json.message || 'Request failed.');
        }
        return json;
    }

    async function startTopup() {
        setError('');
        setInfo('');
        qrWrap.style.display = 'none';
        topupBtn.disabled = true;
        try {
            const amount = Number(amountInput.value || 0);
            const create = await api('/api/mall/wallet/topup/create', { amount: amount, payment_method: selectedMethod });
            currentSessionRef = create.session_ref;
            if (selectedMethod === 'ginto_pay_qr') {
                const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
                qrWrap.style.display = 'block';
                qrWrap.innerHTML = '<h3 style="margin:0 0 10px;font-size:1rem;font-weight:800;">Scan to top up</h3>'
                    + (qr.qr_image ? '<img src="' + qr.qr_image + '" alt="Wallet top-up QR" style="max-width:320px;width:min(100%,320px);border-radius:16px;border:1px solid var(--border);background:#fff;padding:12px;">' : '<div style="color:var(--muted);font-size:0.84rem;">' + (qr.qr_string || 'QR code ready.') + '</div>');
                setInfo('Scan the QR code and keep this page open. You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
                beginStatusPoll();
            } else if (selectedMethod === 'ginto_pay_card') {
                const card = await api('/api/mall/checkout/paymongo-card-init', { session_ref: currentSessionRef });
                setInfo('Redirecting to PayMongo secure card page. You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
                window.location.href = card.redirect_url;
                return;
            } else {
                const paypal = await api('/api/mall/checkout/paypal-order', { session_ref: currentSessionRef });
                setInfo('PayPal order created: ' + paypal.paypal_order_id + '. Use the checkout page if you want to complete it there.');
            }
        } catch (error) {
            setError(error.message);
        } finally {
            topupBtn.disabled = false;
        }
    }

    methodButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            selectedMethod = button.dataset.method;
            methodButtons.forEach(function (item) {
                const active = item === button;
                item.classList.toggle('is-selected', active);
                item.style.borderColor = active ? '#d6b44b' : '';
            });
            updateTopupBreakdown();
        });
    });

    amountInput.addEventListener('input', updateTopupBreakdown);
    updateTopupBreakdown();

    topupBtn.addEventListener('click', startTopup);
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>