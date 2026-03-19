<?php
$wallet = $wallet ?? [];
$walletTransactions = $wallet_transactions ?? [];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<section style="max-width:1200px;margin:28px auto 72px;padding:0 18px;display:grid;grid-template-columns:minmax(320px,0.9fr) minmax(0,1.1fr);gap:20px;">
    <div style="display:flex;flex-direction:column;gap:18px;">
        <div style="border:1px solid var(--border);background:linear-gradient(140deg, rgba(255,255,255,0.05), rgba(69,122,255,0.12), rgba(255,214,102,0.16));border-radius:26px;padding:26px;">
            <div style="font-size:0.82rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);font-weight:700;">Ginto Wallet</div>
            <h1 style="margin:8px 0 12px;font-size:2rem;line-height:1.05;font-weight:800;">₱<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></h1>
            <p style="margin:0;color:var(--muted);font-size:0.92rem;line-height:1.7;">Use your wallet for mall purchases once you have sufficient balance. Top-ups use the same Ginto Pay or PayPal processors already configured in the system.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                <a href="/mall/checkout" class="btn btn-secondary">Go to Checkout</a>
                <a href="/mall/orders" class="btn btn-secondary">My Orders</a>
            </div>
        </div>

        <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
            <h2 style="margin:0 0 12px;font-size:1.02rem;font-weight:800;">Top Up Wallet</h2>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">Amount</span>
                    <input id="walletTopupAmount" type="number" min="1" step="0.01" class="pf-input" placeholder="500.00">
                </label>
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                    <button type="button" class="wallet-method-card is-selected btn btn-secondary" data-method="ginto_pay_qr">Ginto Pay QR</button>
                    <button type="button" class="wallet-method-card btn btn-secondary" data-method="ginto_pay_card">Ginto Pay Card</button>
                    <button type="button" class="wallet-method-card btn btn-secondary" data-method="paypal">PayPal</button>
                </div>
                <div id="walletTopupError" style="display:none;padding:12px 14px;border-radius:14px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fecaca;"></div>
                <div id="walletTopupInfo" style="display:none;padding:12px 14px;border-radius:14px;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);color:#bfdbfe;"></div>
                <div id="walletTopupQr" style="display:none;text-align:center;padding:18px;border-radius:20px;border:1px dashed var(--border);background:var(--surface2);"></div>
                <button type="button" id="walletTopupBtn" class="btn btn-primary">Start Top Up</button>
            </div>
        </div>
    </div>

    <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <h2 style="margin:0;font-size:1.02rem;font-weight:800;">Recent Wallet Activity</h2>
            <span style="font-size:0.8rem;color:var(--muted);">Newest first</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php if (!empty($walletTransactions)): ?>
                <?php foreach ($walletTransactions as $row): ?>
                <div style="display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:14px;border-radius:18px;background:var(--surface2);border:1px solid var(--border);">
                    <div style="width:38px;height:38px;border-radius:12px;background:<?= ($row['direction'] ?? '') === 'credit' ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)' ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                        <?= ($row['direction'] ?? '') === 'credit' ? '↗' : '↘' ?>
                    </div>
                    <div>
                        <div style="font-size:0.9rem;font-weight:700;"><?= htmlspecialchars($row['description'] ?? ucfirst((string)($row['type'] ?? 'Transaction')), ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="font-size:0.76rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.92rem;font-weight:800;color:<?= ($row['direction'] ?? '') === 'credit' ? '#86efac' : '#fca5a5' ?>;">
                            <?= ($row['direction'] ?? '') === 'credit' ? '+' : '-' ?>₱<?= number_format((float)($row['amount'] ?? 0), 2) ?>
                        </div>
                        <div style="font-size:0.74rem;color:var(--muted);">Balance ₱<?= number_format((float)($row['balance_after'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="padding:18px;border-radius:18px;background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:0.88rem;">No wallet transactions yet.</div>
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
    const methodButtons = Array.from(document.querySelectorAll('.wallet-method-card'));
    let selectedMethod = 'ginto_pay_qr';
    let currentSessionRef = '';

    function setError(message) {
        errorBox.style.display = message ? 'block' : 'none';
        errorBox.textContent = message || '';
    }

    function setInfo(message) {
        infoBox.style.display = message ? 'block' : 'none';
        infoBox.textContent = message || '';
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
                setInfo('Scan the QR code and keep this page open until the top-up posts to your wallet.');
            } else if (selectedMethod === 'ginto_pay_card') {
                const card = await api('/api/mall/checkout/paymongo-card-init', { session_ref: currentSessionRef });
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
        });
    });

    topupBtn.addEventListener('click', startTopup);
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>