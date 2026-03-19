<?php
$wallet = $wallet ?? [];
$paypalClientId = trim((string)($paypal_client_id ?? ''));
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<?php if ($paypalClientId !== ''): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypalClientId, ENT_QUOTES, 'UTF-8') ?>&currency=PHP&intent=capture&components=buttons"></script>
<?php endif; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<section style="max-width:1360px;margin:28px auto 72px;padding:0 18px;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(340px,0.8fr);gap:20px;align-items:start;">
    <div style="display:flex;flex-direction:column;gap:18px;">
        <div style="border:1px solid var(--border);background:linear-gradient(135deg, rgba(255,255,255,0.04), rgba(80,146,255,0.08) 55%, rgba(255,214,102,0.14));border-radius:28px;padding:26px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:0.82rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);font-weight:700;">Mall Checkout</div>
                    <h1 style="margin:8px 0 10px;font-size:2rem;line-height:1.05;font-weight:800;">Finish your order</h1>
                    <p style="margin:0;max-width:720px;color:var(--muted);font-size:0.95rem;line-height:1.7;">This checkout uses the same processors already configured for registration. Ginto Pay supports QR and card through PayMongo, PayPal is available as a direct option, and Ginto Wallet can be used when your balance is sufficient.</p>
                </div>
                <a href="/marketplace" class="btn btn-secondary">Back to Mall</a>
            </div>
        </div>

        <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
            <h2 style="margin:0 0 14px;font-size:1.05rem;font-weight:800;">Shipping Details</h2>
            <form id="checkoutShippingForm" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">Full Name</span>
                    <input id="shipFullName" type="text" class="pf-input" placeholder="Juan Dela Cruz" autocomplete="name">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">Phone</span>
                    <input id="shipPhone" type="text" class="pf-input" placeholder="09xx xxx xxxx" autocomplete="tel">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;grid-column:1 / -1;">
                    <span style="font-size:0.82rem;font-weight:700;">Address Line 1</span>
                    <input id="shipAddress1" type="text" class="pf-input" placeholder="House number, street, barangay" autocomplete="address-line1">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;grid-column:1 / -1;">
                    <span style="font-size:0.82rem;font-weight:700;">Address Line 2</span>
                    <input id="shipAddress2" type="text" class="pf-input" placeholder="Apartment, building, landmark" autocomplete="address-line2">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">City / Municipality</span>
                    <input id="shipCity" type="text" class="pf-input" placeholder="Quezon City" autocomplete="address-level2">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">Province</span>
                    <input id="shipProvince" type="text" class="pf-input" placeholder="Metro Manila" autocomplete="address-level1">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">Postal Code</span>
                    <input id="shipPostalCode" type="text" class="pf-input" placeholder="1100" autocomplete="postal-code">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.82rem;font-weight:700;">Country</span>
                    <input id="shipCountry" type="text" class="pf-input" value="PH" autocomplete="country-name">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;grid-column:1 / -1;">
                    <span style="font-size:0.82rem;font-weight:700;">Buyer Notes</span>
                    <textarea id="shipBuyerNotes" class="pf-input" rows="3" placeholder="Special instructions for delivery or landmark notes"></textarea>
                </label>
            </form>
        </div>

        <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.05rem;font-weight:800;">Payment Method</h2>
                <div style="font-size:0.82rem;color:var(--muted);">Available: Ginto Pay, PayPal, and Ginto Wallet</div>
            </div>

            <div id="paymentMethodGrid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                <button type="button" class="checkout-method-card is-selected" data-method="ginto_pay_qr" style="text-align:left;border:2px solid #d6b44b;background:linear-gradient(110deg, rgba(255,255,255,0.94), rgba(255,240,188,0.88));border-radius:28px;padding:22px 24px;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 0 0 1px rgba(214,180,75,0.08), 0 18px 40px rgba(214,180,75,0.08);">
                    <span style="width:22px;height:22px;border-radius:50%;border:2px solid rgba(43,57,91,0.4);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="checkout-method-radio" style="width:10px;height:10px;border-radius:50%;background:#304da3;display:block;"></span>
                    </span>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <img src="/assets/images/ginto.png" alt="Ginto Pay" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid rgba(0,0,0,0.08);">
                        <div>
                            <div style="font-size:1.55rem;font-weight:800;line-height:1.1;">Ginto Pay</div>
                            <div style="font-size:0.95rem;color:#3b537a;font-weight:600;">QR checkout via InstaPay / PESONet</div>
                        </div>
                    </div>
                </button>

                <button type="button" class="checkout-method-card" data-method="ginto_pay_card" style="text-align:left;border:2px solid rgba(48,77,163,0.2);background:linear-gradient(110deg, rgba(255,255,255,0.96), rgba(104,178,255,0.16));border-radius:28px;padding:22px 24px;display:flex;align-items:center;gap:16px;cursor:pointer;">
                    <span style="width:22px;height:22px;border-radius:50%;border:2px solid rgba(43,57,91,0.4);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="checkout-method-radio" style="width:10px;height:10px;border-radius:50%;background:transparent;display:block;"></span>
                    </span>
                    <div>
                        <div style="font-size:1.55rem;font-weight:800;line-height:1.1;">Ginto Pay - Card</div>
                        <div style="font-size:0.95rem;color:#3b537a;font-weight:600;">Hosted credit or debit card checkout</div>
                    </div>
                </button>

                <button type="button" class="checkout-method-card" data-method="paypal" style="text-align:left;border:2px solid rgba(48,77,163,0.2);background:linear-gradient(110deg, rgba(255,255,255,0.96), rgba(104,178,255,0.16));border-radius:28px;padding:22px 24px;display:flex;align-items:center;gap:16px;cursor:pointer;">
                    <span style="width:22px;height:22px;border-radius:50%;border:2px solid rgba(43,57,91,0.4);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="checkout-method-radio" style="width:10px;height:10px;border-radius:50%;background:transparent;display:block;"></span>
                    </span>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <strong style="font-size:1.7rem;font-style:italic;color:#123a8f;line-height:1;">PayPal</strong>
                        <div style="font-size:0.95rem;color:#3b537a;font-weight:600;">Global checkout and card backup</div>
                    </div>
                </button>

                <button type="button" class="checkout-method-card" data-method="wallet" style="text-align:left;border:2px solid rgba(48,77,163,0.2);background:linear-gradient(110deg, rgba(255,255,255,0.96), rgba(104,178,255,0.16));border-radius:28px;padding:22px 24px;display:flex;align-items:center;gap:16px;cursor:pointer;">
                    <span style="width:22px;height:22px;border-radius:50%;border:2px solid rgba(43,57,91,0.4);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="checkout-method-radio" style="width:10px;height:10px;border-radius:50%;background:transparent;display:block;"></span>
                    </span>
                    <div>
                        <div style="font-size:1.55rem;font-weight:800;line-height:1.1;">Ginto Wallet</div>
                        <div style="font-size:0.95rem;color:#3b537a;font-weight:600;">Balance: ₱<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></div>
                    </div>
                </button>
            </div>

            <div id="checkoutError" style="display:none;margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fecaca;"></div>
            <div id="checkoutInfo" style="display:none;margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);color:#bfdbfe;"></div>

            <div id="paypalButtonsWrap" style="display:none;margin-top:18px;">
                <div id="paypalButtonsContainer"></div>
            </div>

            <div id="qrWrap" style="display:none;margin-top:18px;border:1px dashed var(--border);border-radius:22px;padding:20px;background:var(--surface2);text-align:center;">
                <h3 style="margin:0 0 10px;font-size:1rem;font-weight:800;">Scan to complete Ginto Pay</h3>
                <img id="qrImage" src="" alt="Ginto Pay QR" style="max-width:320px;width:min(100%, 320px);border-radius:16px;border:1px solid var(--border);background:#fff;padding:12px;display:none;margin:0 auto 12px;">
                <div id="qrFallback" style="font-size:0.8rem;color:var(--muted);display:none;"></div>
                <div id="qrStatus" style="font-size:0.86rem;color:var(--muted);">Waiting for payment confirmation…</div>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
                <button type="button" id="startCheckoutBtn" class="btn btn-primary" style="padding:12px 28px;">Start Payment</button>
                <a href="/mall/wallet" class="btn btn-secondary">Top Up Wallet</a>
            </div>
        </div>
    </div>

    <aside style="display:flex;flex-direction:column;gap:18px;position:sticky;top:92px;">
        <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
            <h2 style="margin:0 0 14px;font-size:1.05rem;font-weight:800;">Order Summary</h2>
            <div id="checkoutItems" style="display:flex;flex-direction:column;gap:12px;"></div>
            <div style="height:1px;background:var(--border);margin:16px 0;"></div>
            <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:var(--muted);margin-bottom:8px;">
                <span>Items total</span>
                <span id="checkoutSubtotal">₱0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:var(--muted);margin-bottom:8px;">
                <span>Stores in this checkout</span>
                <span id="checkoutStoreCount">0</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:10px;">
                <div>
                    <div style="font-size:0.78rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);font-weight:700;">Total due</div>
                    <div id="checkoutTotal" style="font-size:2rem;font-weight:800;line-height:1;">₱0.00</div>
                </div>
                <div style="font-size:0.8rem;color:var(--muted);text-align:right;max-width:180px;">Platform fees and markups are already reflected in the checkout total according to the seller’s chosen plan.</div>
            </div>
        </div>

        <div style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:22px;">
            <h2 style="margin:0 0 12px;font-size:1rem;font-weight:800;">What happens next</h2>
            <ol style="margin:0;padding-left:18px;color:var(--muted);font-size:0.88rem;line-height:1.8;">
                <li>We split your cart into seller-specific orders.</li>
                <li>Successful payment notifies each seller by email and mall notification.</li>
                <li>Seller and delivery crew update the order timeline like Lazada or Shopee.</li>
                <li>You receive in-app mall notifications whenever delivery status changes.</li>
            </ol>
        </div>
    </aside>
</section>

<script>
(function () {
    const csrfToken = <?= json_encode($csrf_token ?? '') ?>;
    const walletBalance = <?= json_encode((float)($wallet['balance'] ?? 0)) ?>;
    const cartKey = 'epower_cart';
    const query = new URLSearchParams(window.location.search);
    let selectedMethod = 'ginto_pay_qr';
    let currentSessionRef = query.get('session_ref') || '';
    let currentPayPalOrderId = '';
    let statusPoll = null;

    const methods = Array.from(document.querySelectorAll('.checkout-method-card'));
    const startBtn = document.getElementById('startCheckoutBtn');
    const errorBox = document.getElementById('checkoutError');
    const infoBox = document.getElementById('checkoutInfo');
    const paypalWrap = document.getElementById('paypalButtonsWrap');
    const paypalButtonsContainer = document.getElementById('paypalButtonsContainer');
    const qrWrap = document.getElementById('qrWrap');
    const qrImage = document.getElementById('qrImage');
    const qrFallback = document.getElementById('qrFallback');
    const qrStatus = document.getElementById('qrStatus');
    const checkoutItems = document.getElementById('checkoutItems');
    const checkoutSubtotal = document.getElementById('checkoutSubtotal');
    const checkoutTotal = document.getElementById('checkoutTotal');
    const checkoutStoreCount = document.getElementById('checkoutStoreCount');

    function readCart() {
        try {
            return JSON.parse(localStorage.getItem(cartKey) || '[]');
        } catch (_) {
            return [];
        }
    }

    function clearCart() {
        localStorage.removeItem(cartKey);
    }

    function formatPrice(value, currency) {
        const map = { PHP: '₱', USD: '$', EUR: '€', NGN: '₦' };
        return (map[currency] || (currency + ' ')) + Number(value || 0).toFixed(2);
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' })[ch];
        });
    }

    function cartSummary() {
        const cart = readCart();
        const stores = new Set();
        let total = 0;
        let currency = 'PHP';
        cart.forEach(function (item) {
            const qty = Number(item.qty || item.quantity || 1);
            total += Number(item.price || 0) * qty;
            currency = item.currency || currency;
            if (item.seller_id) stores.add(String(item.seller_id));
        });
        return { cart, total, currency, stores: stores.size };
    }

    function renderSummary() {
        const summary = cartSummary();
        if (!summary.cart.length) {
            checkoutItems.innerHTML = '<div style="padding:14px;border-radius:14px;background:var(--surface2);border:1px solid var(--border);color:var(--muted);">Your cart is empty. Return to the mall and add products first.</div>';
            checkoutSubtotal.textContent = formatPrice(0, 'PHP');
            checkoutTotal.textContent = formatPrice(0, 'PHP');
            checkoutStoreCount.textContent = '0';
            startBtn.disabled = true;
            return;
        }
        startBtn.disabled = false;
        checkoutItems.innerHTML = summary.cart.map(function (item) {
            const qty = Number(item.qty || item.quantity || 1);
            return '<div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:16px;background:var(--surface2);border:1px solid var(--border);">'
                + '<img src="' + esc(item.img || '/assets/images/placeholder_ceramic.svg') + '" alt="' + esc(item.title) + '" style="width:58px;height:58px;border-radius:14px;object-fit:cover;border:1px solid var(--border);">'
                + '<div style="flex:1;min-width:0;">'
                + '<div style="font-weight:700;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(item.title) + '</div>'
                + '<div style="font-size:0.78rem;color:var(--muted);">Qty ' + qty + '</div>'
                + '</div>'
                + '<div style="font-weight:700;font-size:0.88rem;">' + esc(formatPrice(Number(item.price || 0) * qty, item.currency || 'PHP')) + '</div>'
                + '</div>';
        }).join('');
        checkoutSubtotal.textContent = formatPrice(summary.total, summary.currency);
        checkoutTotal.textContent = formatPrice(summary.total, summary.currency);
        checkoutStoreCount.textContent = String(summary.stores || 1);
    }

    function shippingPayload() {
        return {
            full_name: document.getElementById('shipFullName').value,
            phone: document.getElementById('shipPhone').value,
            address_line1: document.getElementById('shipAddress1').value,
            address_line2: document.getElementById('shipAddress2').value,
            city: document.getElementById('shipCity').value,
            province: document.getElementById('shipProvince').value,
            postal_code: document.getElementById('shipPostalCode').value,
            country: document.getElementById('shipCountry').value,
            buyer_notes: document.getElementById('shipBuyerNotes').value,
        };
    }

    function setError(message) {
        if (!message) {
            errorBox.style.display = 'none';
            errorBox.textContent = '';
            return;
        }
        errorBox.style.display = 'block';
        errorBox.textContent = message;
    }

    function setInfo(message) {
        if (!message) {
            infoBox.style.display = 'none';
            infoBox.textContent = '';
            return;
        }
        infoBox.style.display = 'block';
        infoBox.textContent = message;
    }

    async function api(url, body, method) {
        const response = await fetch(url, {
            method: method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: body ? JSON.stringify(Object.assign({ csrf_token: csrfToken }, body)) : undefined,
        });
        const json = await response.json();
        if (!response.ok || json.success === false) {
            throw new Error(json.message || json.error || 'Request failed.');
        }
        return json;
    }

    async function createSession() {
        return api('/api/mall/checkout/create', {
            payment_method: selectedMethod,
            cart: readCart(),
            shipping: shippingPayload(),
        });
    }

    async function startPayment() {
        setError('');
        setInfo('');
        qrWrap.style.display = 'none';
        paypalWrap.style.display = 'none';
        startBtn.disabled = true;
        try {
            const session = await createSession();
            currentSessionRef = session.session_ref;
            if (selectedMethod === 'wallet') {
                clearCart();
                window.location.href = '/mall/orders';
                return;
            }
            if (selectedMethod === 'ginto_pay_qr') {
                const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
                qrWrap.style.display = 'block';
                qrStatus.textContent = 'Waiting for payment confirmation...';
                if (qr.qr_image) {
                    qrImage.style.display = 'block';
                    qrImage.src = qr.qr_image;
                    qrFallback.style.display = 'none';
                } else {
                    qrImage.style.display = 'none';
                    qrFallback.style.display = 'block';
                    qrFallback.textContent = qr.qr_string || 'QR code ready. Use your banking app to complete the payment.';
                }
                setInfo('Scan the QR code using your banking app. This page will update automatically once payment succeeds.');
                beginStatusPoll();
                return;
            }
            if (selectedMethod === 'ginto_pay_card') {
                const card = await api('/api/mall/checkout/paymongo-card-init', { session_ref: currentSessionRef });
                window.location.href = card.redirect_url;
                return;
            }
            if (selectedMethod === 'paypal') {
                const paypal = await api('/api/mall/checkout/paypal-order', { session_ref: currentSessionRef });
                currentPayPalOrderId = paypal.paypal_order_id;
                renderPayPalButtons();
                paypalWrap.style.display = 'block';
                setInfo('Approve the payment using the PayPal buttons below.');
                return;
            }
        } catch (error) {
            setError(error.message);
        } finally {
            startBtn.disabled = false;
        }
    }

    function beginStatusPoll() {
        if (statusPoll) window.clearInterval(statusPoll);
        statusPoll = window.setInterval(async function () {
            try {
                const status = await fetch('/api/mall/checkout/status?session_ref=' + encodeURIComponent(currentSessionRef), {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await status.json();
                if (json.status === 'completed') {
                    clearCart();
                    window.clearInterval(statusPoll);
                    qrStatus.textContent = 'Payment confirmed. Redirecting to your orders...';
                    window.location.href = '/mall/orders';
                }
            } catch (_) {}
        }, 5000);
    }

    function renderPayPalButtons() {
        if (!window.paypal || !paypalButtonsContainer) {
            setError('PayPal is not available on this page right now.');
            return;
        }
        paypalButtonsContainer.innerHTML = '';
        window.paypal.Buttons({
            createOrder: function () {
                return currentPayPalOrderId;
            },
            onApprove: async function () {
                try {
                    await api('/api/mall/checkout/paypal-capture', {
                        session_ref: currentSessionRef,
                        paypal_order_id: currentPayPalOrderId,
                    });
                    clearCart();
                    window.location.href = '/mall/orders';
                } catch (error) {
                    setError(error.message);
                }
            },
            onError: function () {
                setError('PayPal payment failed.');
            },
        }).render('#paypalButtonsContainer');
    }

    methods.forEach(function (button) {
        button.addEventListener('click', function () {
            selectedMethod = button.dataset.method;
            methods.forEach(function (item) {
                const active = item === button;
                item.classList.toggle('is-selected', active);
                item.style.borderColor = active ? '#d6b44b' : 'rgba(48,77,163,0.2)';
                const dot = item.querySelector('.checkout-method-radio');
                if (dot) dot.style.background = active ? '#304da3' : 'transparent';
            });
            setError('');
            setInfo(selectedMethod === 'wallet' && walletBalance <= 0 ? 'Your wallet balance is currently empty.' : '');
            if (selectedMethod !== 'paypal') {
                paypalWrap.style.display = 'none';
                paypalButtonsContainer.innerHTML = '';
            }
            if (selectedMethod !== 'ginto_pay_qr') {
                qrWrap.style.display = 'none';
            }
        });
    });

    startBtn.addEventListener('click', startPayment);

    renderSummary();

    if (query.get('status') === 'success' && currentSessionRef) {
        setInfo('We are finalizing your payment. This usually takes a few seconds.');
        beginStatusPoll();
    } else if (query.get('status') === 'cancelled') {
        setError('Payment was cancelled before completion.');
    }
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>