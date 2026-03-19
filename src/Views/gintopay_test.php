<?php
$csrfToken = $csrf_token ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GintoPay Standalone Test</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0d1117; color: #e6edf3; font-family: sans-serif; padding: 28px 14px; }
    .wrap { max-width: 560px; margin: 0 auto; }
    .card { background: #161b22; border: 1px solid #30363d; border-radius: 14px; padding: 20px; }
    h1 { font-size: 1.4rem; margin-bottom: 8px; }
    p.sub { font-size: 0.9rem; color: #8b949e; margin-bottom: 16px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .field { margin-bottom: 10px; }
    label { display: block; font-size: 0.8rem; color: #8b949e; margin-bottom: 4px; }
    input, select { width: 100%; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; border-radius: 8px; padding: 10px; }
    .row { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; }
    button { width: 100%; padding: 11px; border: 0; border-radius: 8px; background: #238636; color: #fff; font-weight: 700; cursor: pointer; }
    button:disabled { opacity: 0.6; cursor: not-allowed; }
    .status { margin-top: 12px; font-size: 0.9rem; color: #8b949e; min-height: 22px; }
    .ok { color: #3fb950; }
    .bad { color: #f85149; }
    .warn { color: #d29922; }
    .box { margin-top: 12px; border: 1px solid #30363d; border-radius: 8px; padding: 10px; }
    .box a { color: #58a6ff; text-decoration: underline; }
    pre { margin-top: 10px; background: #010409; border: 1px solid #30363d; border-radius: 8px; padding: 10px; max-height: 220px; overflow: auto; font-size: 0.75rem; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>GintoPay Standalone (Card + Webhook)</h1>
    <p class="sub">Isolated path for debugging at /gintopay. No hosted checkout session redirect.</p>

    <div class="grid">
      <div class="field">
        <label>Username</label>
        <input id="username" value="ginto_test_user">
      </div>
      <div class="field">
        <label>Email</label>
        <input id="email" type="email" value="test@example.com">
      </div>
    </div>

    <div class="grid">
      <div class="field">
        <label>Password</label>
        <input id="password" type="password" value="Test1234!">
      </div>
      <div class="field">
        <label>Phone</label>
        <input id="phone" value="09171234567">
      </div>
    </div>

    <div class="grid">
      <div class="field">
        <label>First Name</label>
        <input id="firstname" value="Test">
      </div>
      <div class="field">
        <label>Last Name</label>
        <input id="lastname" value="User">
      </div>
    </div>

    <div class="grid">
      <div class="field">
        <label>Country</label>
        <input id="country" value="PH">
      </div>
      <div class="field">
        <label>Tier</label>
        <input id="tier" value="Go">
      </div>
    </div>

    <div class="grid">
      <div class="field">
        <label>Package</label>
        <input id="package" value="go">
      </div>
      <div class="field">
        <label>Amount (PHP)</label>
        <input id="amount" type="number" value="100" min="1">
      </div>
    </div>

    <div class="field">
      <label>Card Number</label>
      <input id="card_number" placeholder="4120000000000007">
    </div>

    <div class="row">
      <div class="field">
        <label>CVC</label>
        <input id="cvc" placeholder="123">
      </div>
      <div class="field">
        <label>Exp Month</label>
        <input id="exp_month" placeholder="12">
      </div>
      <div class="field">
        <label>Exp Year</label>
        <input id="exp_year" placeholder="2030">
      </div>
    </div>

    <button id="pay-btn">Pay Card (Webhook Flow)</button>

    <div id="status" class="status"></div>
    <div id="action-box" class="box" style="display:none;"></div>
    <pre id="debug">(waiting)</pre>
  </div>
</div>

<script>
(function() {
  'use strict';

  const csrf = <?= json_encode($csrfToken) ?>;
  let pollTimer = null;

  function byId(id) { return document.getElementById(id); }
  function setStatus(msg, cls) {
    const el = byId('status');
    el.className = 'status ' + (cls || '');
    el.textContent = msg;
  }
  function setDebug(obj) {
    byId('debug').textContent = (typeof obj === 'string') ? obj : JSON.stringify(obj, null, 2);
  }
  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }
  function startPoll(piId) {
    stopPoll();
    pollTimer = setInterval(function() {
      fetch('/api/payments/gintopay-status?pi_id=' + encodeURIComponent(piId))
        .then(r => r.json())
        .then(data => {
          if (data.processed) {
            stopPoll();
            setStatus('Webhook completed. Redirecting...', 'ok');
            setDebug(data);
            setTimeout(function() { window.location.href = data.redirect || '/chat'; }, 1200);
          } else if (data.failed) {
            stopPoll();
            setStatus(data.message || 'Webhook failed.', 'bad');
            setDebug(data);
          } else {
            setStatus('Waiting for webhook confirmation...', 'warn');
          }
        })
        .catch(function(err) {
          setStatus('Polling error: ' + err.message, 'bad');
        });
    }, 3000);
  }

  byId('pay-btn').addEventListener('click', function() {
    const btn = byId('pay-btn');
    btn.disabled = true;
    byId('action-box').style.display = 'none';

    const fd = new FormData();
    fd.append('csrf_token', csrf);
    ['username','email','password','phone','firstname','lastname','country','tier','package','amount','card_number','cvc','exp_month','exp_year'].forEach(function(k){
      fd.append(k, byId(k).value || '');
    });
    fd.append('duration', '1m');

    setStatus('Initializing card payment...', 'warn');

    fetch('/api/payments/gintopay-init', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(r => r.json())
    .then(data => {
      setDebug(data);
      if (!data.success) {
        btn.disabled = false;
        setStatus(data.message || 'Failed to initialize payment.', 'bad');
        return;
      }

      if (data.requires_action && data.next_action_url) {
        byId('action-box').style.display = 'block';
        byId('action-box').innerHTML = 'Card requires additional action: <a href="' + data.next_action_url + '" target="_blank" rel="noopener">Open verification link</a>';
      }

      setStatus('Payment initialized. Waiting for webhook...', 'warn');
      startPoll(data.pi_id);
    })
    .catch(function(err) {
      btn.disabled = false;
      setStatus('Network error: ' + err.message, 'bad');
    });
  });
})();
</script>
</body>
</html>
