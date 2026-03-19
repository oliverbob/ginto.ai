<?php
// Temporary PayMongo QRPH test page — accessible at /paymongo
// Remove this file and its route once testing is complete.
$csrfToken = $csrf_token ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PayMongo QRPH Test</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #111; color: #eee; font-family: sans-serif; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 40px 16px; }
    .card { background: #1e1e1e; border: 1px solid #333; border-radius: 12px; padding: 32px; max-width: 420px; width: 100%; }
    h1 { font-size: 1.3rem; margin-bottom: 24px; color: #f97316; }
    label { display: block; font-size: 0.85rem; color: #aaa; margin-bottom: 4px; }
    input { width: 100%; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 6px; color: #fff; font-size: 1rem; margin-bottom: 16px; }
    button { width: 100%; padding: 12px; background: #f97316; border: none; border-radius: 6px; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    #status { margin-top: 20px; text-align: center; font-size: 0.9rem; color: #aaa; min-height: 24px; }
    #qr-section { display: none; text-align: center; margin-top: 20px; }
    #qr-section img { width: 200px; height: 200px; background: #fff; padding: 8px; border-radius: 8px; }
    #qr-amount { font-size: 1.4rem; font-weight: 700; color: #f97316; margin-top: 10px; }
    #poll-banner { margin-top: 10px; padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; background: rgba(249,115,22,0.15); display: inline-block; }
    #confirmed-section { display: none; text-align: center; margin-top: 20px; padding: 20px; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.4); border-radius: 8px; color: #4ade80; font-weight: 600; }
    #error-section { display: none; text-align: center; margin-top: 20px; padding: 12px; background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); border-radius: 8px; color: #f87171; font-size: 0.9rem; }
    .retry-btn { background: transparent; border: 1px solid #f97316; color: #f97316; padding: 8px 20px; border-radius: 6px; font-size: 0.85rem; cursor: pointer; margin-top: 12px; width: auto; }
  </style>
</head>
<body>
<div class="card">
  <h1>&#x1F4F1; PayMongo QRPH Test</h1>

  <div id="form-section">
    <label for="test-amount">Amount (PHP)</label>
    <input type="number" id="test-amount" value="100" min="1" step="1">

    <label for="test-email">Email</label>
    <input type="email" id="test-email" value="test@example.com">

    <label for="test-name">Name</label>
    <input type="text" id="test-name" value="Test User">

    <label for="test-phone">Phone (optional)</label>
    <input type="text" id="test-phone" value="">

    <button id="generate-btn" onclick="generateQr()">Generate QR Code</button>
  </div>

  <div id="status"></div>
  <div id="error-section">
    <span id="error-msg">Something went wrong.</span><br>
    <button class="retry-btn" onclick="reset()">Try Again</button>
  </div>

  <div id="qr-section">
    <img id="qr-image" src="" alt="QRPH QR Code">
    <div id="qr-amount"></div>
    <div id="poll-banner">&#x23F3; Waiting for payment... (polling every 3s)</div>
  </div>

  <div id="confirmed-section">
    &#x2705; Payment Confirmed!<br>
    <small style="color:#86efac; font-weight:400;">The QR was paid successfully.</small>
  </div>
</div>

<script>
(function() {
  'use strict';

  var csrfToken  = <?= json_encode($csrfToken) ?>;
  var currentPiId = null;
  var pollingTimer = null;
  var POLL_INTERVAL = 3000;

  window.generateQr = function() {
    var amount = parseInt(document.getElementById('test-amount').value, 10);
    var email  = document.getElementById('test-email').value.trim();
    var name   = document.getElementById('test-name').value.trim();
    var phone  = document.getElementById('test-phone').value.trim();

    if (!amount || amount < 1) { setStatus('Please enter a valid amount.', 'red'); return; }
    if (!email)                 { setStatus('Please enter an email.', 'red'); return; }

    setStatus('Generating QR code...', 'orange');
    document.getElementById('generate-btn').disabled = true;
    hide('error-section');
    hide('qr-section');
    hide('confirmed-section');

    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('amount', amount);
    fd.append('email', email);
    fd.append('name', name || email);
    fd.append('phone', phone);
    fd.append('tier', 'Test');

    fetch('/api/payments/paymongo-qrph-init', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      console.log('Init response:', data);
      if (!data.success) {
        showError(data.message || 'Failed to generate QR.');
        return;
      }

      currentPiId = data.pi_id;

      var qrImg = document.getElementById('qr-image');
      if (data.qr_image) {
        qrImg.src = data.qr_image;
      } else {
        qrImg.alt = 'QR string: ' + (data.qr_string || '(none)');
        console.warn('No qr_image returned:', data);
      }

      document.getElementById('qr-amount').textContent = '₱' + amount.toLocaleString();
      show('qr-section');
      setStatus('QR generated. PI ID: ' + currentPiId, 'gray');

      startPolling(currentPiId);
    })
    .catch(function(err) {
      console.error('Init error:', err);
      showError('Network error: ' + err.message);
    });
  };

  window.reset = function() {
    stopPolling();
    currentPiId = null;
    hide('error-section');
    hide('qr-section');
    hide('confirmed-section');
    setStatus('', 'gray');
    document.getElementById('generate-btn').disabled = false;
  };

  function startPolling(piId) {
    stopPolling();
    pollingTimer = setInterval(function() {
      fetch('/api/payments/paymongo-qrph-status?pi_id=' + encodeURIComponent(piId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        console.log('Poll:', data);
        document.getElementById('poll-banner').textContent = '⏳ Status: ' + (data.status || '?') + ' — polling every 3s';
        if (data.paid) {
          stopPolling();
          hide('qr-section');
          show('confirmed-section');
          setStatus('Payment confirmed!', 'green');
        }
      })
      .catch(function(e) { console.warn('Poll error:', e); });
    }, POLL_INTERVAL);
  }

  function stopPolling() {
    if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
  }

  function showError(msg) {
    document.getElementById('error-msg').textContent = msg;
    show('error-section');
    setStatus('', 'gray');
    document.getElementById('generate-btn').disabled = false;
  }

  function setStatus(msg, color) {
    var el = document.getElementById('status');
    el.textContent = msg;
    el.style.color = color === 'orange' ? '#f97316' : color === 'green' ? '#4ade80' : color === 'red' ? '#f87171' : '#888';
  }

  function show(id) { document.getElementById(id).style.display = ''; }
  function hide(id) { document.getElementById(id).style.display = 'none'; }
})();
</script>
</body>
</html>
