<?php
$accounts = $payout_accounts ?? [];
$csrfToken = $csrf_token ?? '';
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<style>
.wpage-wrap { max-width: 900px; margin: 28px auto 60px; padding: 0 16px; }
.wpage-head { display:flex; align-items:center; gap:14px; margin-bottom:24px; flex-wrap:wrap; }
.wpage-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wpage-back { display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--muted); font-weight:600; text-decoration:none; padding:6px 12px; border:1px solid var(--border); border-radius:8px; transition:all var(--trans); margin-bottom:16px; }
.wpage-back:hover { background:var(--surface); color:var(--text); }
.wpage-title { font-size:1.35rem; font-weight:800; }
.wpage-sub { font-size:0.8rem; color:var(--muted); margin-top:2px; }
.acct-card { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:18px 20px; display:flex; align-items:center; gap:16px; transition:all var(--trans); }
.acct-card.is-default { border-color:rgba(99,102,241,0.45); background:rgba(99,102,241,0.05); }
.acct-icon { width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.4rem; background:var(--surface2); border:1px solid var(--border); }
.acct-info { flex:1; min-width:0; }
.acct-name { font-weight:700; font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.acct-detail { font-size:0.78rem; color:var(--muted); margin-top:3px; }
.acct-badge-default { font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; padding:3px 8px; border-radius:6px; background:rgba(99,102,241,0.18); color:#a5b4fc; border:1px solid rgba(99,102,241,0.3); }
.acct-actions { display:flex; gap:8px; flex-shrink:0; }
.btn-acct { border:1px solid var(--border); border-radius:9px; padding:6px 12px; font-size:0.76rem; font-weight:700; cursor:pointer; transition:all var(--trans); background:var(--surface2); color:var(--text); }
.btn-acct:hover { background:var(--surface); border-color:var(--muted); }
.btn-acct-danger:hover { border-color:#f87171; color:#f87171; background:rgba(239,68,68,0.07); }
.btn-acct-primary { border-color:rgba(99,102,241,0.3); color:#a5b4fc; }
.btn-acct-primary:hover { background:rgba(99,102,241,0.1); border-color:#a5b4fc; }
.add-acct-section { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:22px 22px 24px; margin-top:12px; }
.section-title { font-size:0.95rem; font-weight:800; margin:0 0 4px; }
.section-sub { font-size:0.77rem; color:var(--muted); margin:0 0 18px; }
.pf-input { background:var(--surface2); border:1px solid var(--border); border-radius:11px; padding:10px 13px; color:var(--text); font-size:0.88rem; width:100%; box-sizing:border-box; transition:border var(--trans); }
.pf-input:focus { outline:none; border-color:rgba(99,102,241,0.55); }
.payout-type-btn { flex:1; padding:9px 14px; border-radius:11px; border:1px solid var(--border); background:var(--surface2); color:var(--muted); font-size:0.82rem; font-weight:700; cursor:pointer; transition:all var(--trans); }
.payout-type-btn.active { border-color:rgba(99,102,241,0.45); background:rgba(99,102,241,0.1); color:var(--text); }
.form-label-text { font-size:0.73rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.06em; display:block; margin-bottom:6px; }
.info-note { background:rgba(59,130,246,0.07); border:1px solid rgba(59,130,246,0.2); border-radius:12px; padding:12px 16px; font-size:0.79rem; color:#93c5fd; margin-bottom:20px; line-height:1.55; }
.feedback-msg { display:none; padding:11px 14px; border-radius:12px; font-size:0.82rem; margin-top:10px; }
.feedback-ok { background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.25); color:#86efac; }
.feedback-err { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); color:#fecaca; }
</style>

<div class="wpage-wrap">
    <a class="wpage-back" href="/wallet">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Wallet
    </a>
    <div class="wpage-head">
        <div class="wpage-icon" style="background:rgba(59,130,246,0.10);color:#60a5fa;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
        </div>
        <div>
            <div class="wpage-title">Payout Accounts</div>
            <div class="wpage-sub">Manage bank accounts and e-wallets for automatic payouts</div>
        </div>
    </div>

    <div class="info-note">
        <strong>Auto-Payout:</strong> Your <strong>default account</strong> receives scheduled earnings automatically — no manual withdrawal needed. This complies with BSP regulations.
        Set any account as default below.
    </div>

    <!-- Existing accounts -->
    <?php if (!empty($accounts)): ?>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px;" id="accountsList">
        <?php foreach ($accounts as $acct): ?>
        <?php $isPrimary = !empty($acct['is_primary']); ?>
        <div class="acct-card <?= $isPrimary ? 'is-default' : '' ?>" id="acct-<?= (int)$acct['id'] ?>">
            <div class="acct-icon">
                <?php if (stripos($acct['institution_name'] ?? '', 'GCash') !== false || stripos($acct['institution_name'] ?? '', 'Maya') !== false || stripos($acct['institution_name'] ?? '', 'PayMongo') !== false || ($acct['account_type'] ?? '') === 'ewallet'): ?>
                📱
                <?php else: ?>
                🏦
                <?php endif; ?>
            </div>
            <div class="acct-info">
                <div class="acct-name"><?= htmlspecialchars($acct['institution_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="acct-detail">
                    <?= htmlspecialchars($acct['account_holder_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars($acct['account_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if ($isPrimary): ?>
                <div style="margin-top:6px;">
                    <span class="acct-badge-default">
                        <svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display:inline-block;vertical-align:middle;margin-right:3px;"><polyline points="20 6 9 17 4 12"/></svg>
                        Default
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <div class="acct-actions">
                <?php if (!$isPrimary): ?>
                <button class="btn-acct btn-acct-primary" onclick="setDefault(<?= (int)$acct['id'] ?>)">Set Default</button>
                <?php endif; ?>
                <button class="btn-acct btn-acct-danger" onclick="deleteAccount(<?= (int)$acct['id'] ?>, this)">Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:40px 20px;background:var(--surface);border:1px dashed var(--border);border-radius:18px;color:var(--muted);margin-bottom:28px;">
        <div style="font-size:2.2rem;margin-bottom:10px;opacity:0.3;">🏦</div>
        <div style="font-weight:700;margin-bottom:4px;">No accounts yet</div>
        <div style="font-size:0.82rem;">Add a bank account or e-wallet below to receive payouts.</div>
    </div>
    <?php endif; ?>

    <!-- Add New Account -->
    <div class="add-acct-section">
        <h2 class="section-title">Add New Account</h2>
        <p class="section-sub">Bank accounts and e-wallets listed here are regulated by the Bangko Sentral ng Pilipinas (BSP).</p>

        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <button type="button" class="payout-type-btn active" data-type="bank" id="ptypeBank">🏦 Bank Account</button>
            <button type="button" class="payout-type-btn" data-type="ewallet" id="ptypeEwallet">📱 E-Wallet</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <label>
                <span class="form-label-text">Institution</span>
                <select id="newPayoutInstitution" class="pf-input">
                    <option value="">— Select institution —</option>
                    <optgroup label="Universal / Commercial Banks" id="og-bank">
                        <option>Asia United Bank Corporation</option>
                        <option>Bank of China (HK) Limited – Manila Branch</option>
                        <option>Bank of Commerce</option>
                        <option>Bank of the Philippine Islands (BPI)</option>
                        <option>BDO Unibank, Inc.</option>
                        <option>China Banking Corporation (China Bank)</option>
                        <option>CIMB Bank Philippines, Inc.</option>
                        <option>CTBC Bank (Philippines) Corporation</option>
                        <option>Development Bank of the Philippines (DBP)</option>
                        <option>East West Banking Corporation</option>
                        <option>Land Bank of the Philippines</option>
                        <option>Maybank Philippines, Inc.</option>
                        <option>Metropolitan Bank and Trust Company (Metrobank)</option>
                        <option>Philippine Bank of Communications (PBCom)</option>
                        <option>Philippine National Bank (PNB)</option>
                        <option>Philippine Trust Company (Philtrust)</option>
                        <option>Philippine Veterans Bank</option>
                        <option>Rizal Commercial Banking Corporation (RCBC)</option>
                        <option>Security Bank Corporation</option>
                        <option>Standard Chartered Bank</option>
                        <option>The Hongkong and Shanghai Banking Corporation (HSBC)</option>
                        <option>Union Bank of the Philippines</option>
                    </optgroup>
                    <optgroup label="Thrift Banks" id="og-thrift">
                        <option>AllBank (A Thrift Bank), Inc.</option>
                        <option>BDO Network Bank, Inc.</option>
                        <option>BPI Direct BanKo, Inc., A Savings Bank</option>
                        <option>Card SME Bank Inc., A Thrift Bank</option>
                        <option>China Bank Savings, Inc.</option>
                        <option>City Savings Bank, Inc.</option>
                        <option>Equicom Savings Bank, Inc.</option>
                        <option>ISLA Bank (A Thrift Bank), Inc.</option>
                        <option>Legazpi Savings Bank, Inc.</option>
                        <option>Luzon Development Bank</option>
                        <option>Malayan Savings Bank, Inc.</option>
                        <option>Pacific Ace Savings Bank, Inc.</option>
                        <option>Philippine Business Bank, Inc., A Savings Bank</option>
                        <option>Philippine Savings Bank (PSBank)</option>
                        <option>Producers Savings Bank Corporation</option>
                        <option>Queen City Development Bank (Queenbank), A Thrift Bank</option>
                        <option>Sterling Bank of Asia, Inc. (A Savings Bank)</option>
                        <option>Sun Savings Bank, Inc.</option>
                        <option>UCPB Savings Bank</option>
                        <option>Wealth Development Bank Corporation</option>
                    </optgroup>
                    <optgroup label="Rural / Cooperative Banks" id="og-rural">
                        <option>Bangko Mabuhay (A Rural Bank), Inc.</option>
                        <option>Camalig Bank, Inc. (A Rural Bank)</option>
                        <option>Cantilan Bank, Inc. (A Rural Bank)</option>
                        <option>Card Bank, Inc. (A Microfinance-Oriented Rural Bank)</option>
                        <option>CARD MRI Rizal Bank, Inc.</option>
                        <option>Cebuana Lhuillier Rural Bank, Inc.</option>
                        <option>Dungganon Bank (A Microfinance Rural Bank), Inc.</option>
                        <option>East West Rural Bank, Inc.</option>
                        <option>Entrepreneur Rural Bank, Inc.</option>
                        <option>MariBank Philippines Inc. (A Rural Bank)</option>
                        <option>Mindanao Consolidated Cooperative Bank</option>
                        <option>Netbank (A Rural Bank), Inc.</option>
                        <option>Own Bank, The Rural Bank of Cavite City, Inc.</option>
                        <option>Partner Rural Bank (Cotabato), Inc.</option>
                        <option>Quezon Capital Rural Bank, Inc.</option>
                        <option>Rang-Ay Bank, Inc. (A Rural Bank)</option>
                        <option>Rural Bank of Guinobatan, Inc.</option>
                        <option>Vigan Banco Rural, Incorporada (VBRI)</option>
                    </optgroup>
                    <optgroup label="Digital Banks" id="og-digital">
                        <option>GoTyme Bank Corporation</option>
                        <option>Maya Bank, Inc.</option>
                        <option>Tonik Digital Bank, Inc.</option>
                        <option>Union Digital Bank</option>
                        <option>UNObank, Inc.</option>
                    </optgroup>
                    <optgroup label="E-Wallets / EMI-NBFIs" id="og-ewallet">
                        <option>Alipay Philippines, Inc.</option>
                        <option>CIS Bayad Center, Inc.</option>
                        <option>DCPAY Philippines, Inc.</option>
                        <option>Easypay Global EMI Corporation</option>
                        <option>Ecashpay Asia, Inc.</option>
                        <option>G-Xchange, Inc. (GCash)</option>
                        <option>Gpay Network PH, Inc. (GrabPay)</option>
                        <option>I-Remit, Inc.</option>
                        <option>Infoserve, Inc.</option>
                        <option>MarcoPay, Inc.</option>
                        <option>Maya Philippines, Inc.</option>
                        <option>OmniPay, Inc.</option>
                        <option>PayMongo Payments, Inc.</option>
                        <option>Paynamics Technologies, Inc.</option>
                        <option>Peppermint Bizmoto Inc.</option>
                        <option>Philippine Digital Asset Exchange, Inc.</option>
                        <option>PPS-PEPP Financial Services Corp. (PalawanPay)</option>
                        <option>ShopeePay Philippines, Inc.</option>
                        <option>SpeedyPay, Inc.</option>
                        <option>StarPay Corporation</option>
                        <option>TayoCash, Inc.</option>
                        <option>Toktokwallet, Inc.</option>
                        <option>TopJuan Tech Corporation</option>
                        <option>Toyota Financial Services Philippines Corporation</option>
                        <option>Traxion Pay, Inc.</option>
                        <option>USSC Money Services, Inc.</option>
                        <option>Wise Pilipinas, Inc.</option>
                        <option>Zybi Tech, Inc.</option>
                    </optgroup>
                </select>
            </label>
            <label>
                <span class="form-label-text">Account Holder Name</span>
                <input id="newPayoutHolderName" type="text" class="pf-input" placeholder="Full name on account">
            </label>
            <label>
                <span class="form-label-text">Account Number / Mobile Number</span>
                <input id="newPayoutAccountNumber" type="text" class="pf-input" placeholder="e.g. 09XX XXX XXXX or account number">
            </label>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <input type="checkbox" id="newPayoutIsDefault" style="width:16px;height:16px;accent-color:var(--accent);">
                <span style="font-size:0.82rem;font-weight:600;">Set as default payout account</span>
            </label>
            <div id="addAcctMsg" class="feedback-msg"></div>
            <button type="button" id="addAcctBtn" class="btn btn-primary" style="border-radius:14px;font-size:0.88rem;font-weight:800;padding:11px 18px;">Add Account</button>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = <?= json_encode($csrfToken) ?>;

    // Bank / E-wallet type filter
    const ogBank    = document.getElementById('og-bank');
    const ogThrift  = document.getElementById('og-thrift');
    const ogRural   = document.getElementById('og-rural');
    const ogDigital = document.getElementById('og-digital');
    const ogEwallet = document.getElementById('og-ewallet');
    function filterInstitutions(type) {
        const bankGroups  = [ogBank, ogThrift, ogRural, ogDigital];
        const allGroups   = [...bankGroups, ogEwallet];
        if (type === 'bank') {
            bankGroups.forEach(og => { og.disabled = false; });
            ogEwallet.disabled = true;
        } else {
            bankGroups.forEach(og => { og.disabled = true; });
            ogEwallet.disabled = false;
        }
        document.getElementById('newPayoutInstitution').value = '';
    }
    document.getElementById('ptypeBank').addEventListener('click', function () {
        document.querySelectorAll('.payout-type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterInstitutions('bank');
    });
    document.getElementById('ptypeEwallet').addEventListener('click', function () {
        document.querySelectorAll('.payout-type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterInstitutions('ewallet');
    });

    // Add account
    document.getElementById('addAcctBtn').addEventListener('click', async function () {
        const institution = document.getElementById('newPayoutInstitution').value.trim();
        const holder      = document.getElementById('newPayoutHolderName').value.trim();
        const number      = document.getElementById('newPayoutAccountNumber').value.trim();
        const isDefault   = document.getElementById('newPayoutIsDefault').checked;
        const msgEl       = document.getElementById('addAcctMsg');

        if (!institution || !holder || !number) {
            showMsg(msgEl, 'Please fill in all fields.', false);
            return;
        }
        this.disabled = true; this.textContent = 'Saving…';
        try {
            const r = await fetch('/api/mall/wallet/payout-account', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrf, institution_name: institution, account_holder_name: holder, account_number: number, is_primary: isDefault ? 1 : 0 })
            });
            const d = await r.json();
            if (d.success) {
                showMsg(msgEl, 'Account saved successfully. Reloading…', true);
                setTimeout(() => location.reload(), 1200);
            } else {
                showMsg(msgEl, d.message || 'Could not save. Please try again.', false);
            }
        } catch(e) {
            showMsg(msgEl, 'Network error. Please try again.', false);
        }
        this.disabled = false; this.textContent = 'Add Account';
    });

    function showMsg(el, msg, ok) {
        el.textContent = msg;
        el.className = 'feedback-msg ' + (ok ? 'feedback-ok' : 'feedback-err');
        el.style.display = 'block';
    }

    // Set default
    window.setDefault = async function(id) {
        try {
            const r = await fetch('/api/mall/wallet/payout-account/set-default', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrf, account_id: id })
            });
            const d = await r.json();
            if (d.success) location.reload();
            else alert(d.message || 'Could not update. Please try again.');
        } catch(e) {
            alert('Network error. Please try again.');
        }
    };

    // Delete account
    window.deleteAccount = async function(id, btn) {
        if (!confirm('Delete this payout account? This cannot be undone.')) return;
        btn.disabled = true; btn.textContent = '…';
        try {
            const r = await fetch('/api/mall/wallet/payout-account/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrf, account_id: id })
            });
            const d = await r.json();
            if (d.success) {
                const card = document.getElementById('acct-' + id);
                if (card) card.remove();
            } else {
                alert(d.message || 'Could not delete. Please try again.');
                btn.disabled = false; btn.textContent = 'Delete';
            }
        } catch(e) {
            alert('Network error. Please try again.');
            btn.disabled = false; btn.textContent = 'Delete';
        }
    };
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
