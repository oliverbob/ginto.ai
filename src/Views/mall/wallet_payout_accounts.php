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
/* Institution autocomplete */
.inst-autocomplete { position: relative; }
.inst-dropdown {
    position: absolute; top: calc(100% + 3px); left: 0; right: 0;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; z-index: 1000; max-height: 230px;
    overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    display: none;
}
.inst-option {
    padding: 9px 14px; cursor: pointer; font-size: 0.84rem; color: var(--text);
}
.inst-option:hover, .inst-option.ac-focused { background: rgba(99,102,241,0.14); }
.inst-group-label {
    padding: 7px 14px 3px; font-size: 0.67rem; font-weight: 800;
    color: var(--muted); text-transform: uppercase; letter-spacing: 0.09em;
    pointer-events: none; border-top: 1px solid var(--border); margin-top: 4px;
}
.inst-group-label:first-child { margin-top: 0; border-top: none; }
.inst-no-results { padding: 12px 14px; font-size: 0.82rem; color: var(--muted); text-align: center; }
</style>

<div class="wpage-wrap">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
    <a class="wpage-back" style="margin-bottom:0;" href="/wallet">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Wallet
    </a>
    <a class="wpage-back" style="margin-bottom:0;" href="/wallet/storefront">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        My Storefront
    </a>
    </div>
    <div class="wpage-head">
        <div class="wpage-icon" style="background:rgba(59,130,246,0.10);color:#60a5fa;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
        </div>
        <div>
            <div class="wpage-title">Settlement Accounts (External)</div>
            <div class="wpage-sub">Manage bank accounts and e-wallets where your payment provider sends your sales proceeds</div>
        </div>
    </div>

    <div class="info-note">
        <strong>Settlement Notice:</strong> Your <strong>default account</strong> is where your payment provider routes your sales proceeds. Ginto Mall does not hold or process user funds — all payments and settlements are handled by licensed payment providers.
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
        <p class="section-sub">Add the bank account or e-wallet where your payment provider should send your sales proceeds.</p>

        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <button type="button" class="payout-type-btn active" data-type="bank" id="ptypeBank">🏦 Bank Account</button>
            <button type="button" class="payout-type-btn" data-type="ewallet" id="ptypeEwallet">📱 E-Wallet</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <label>
                <span class="form-label-text">Institution</span>
                <div class="inst-autocomplete">
                    <input type="text" id="newPayoutInstitution_search" class="pf-input" placeholder="Search institution…" autocomplete="off" spellcheck="false">
                    <input type="hidden" id="newPayoutInstitution">
                    <div class="inst-dropdown" id="newPayoutInstitution_list"></div>
                </div>
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
                <span style="font-size:0.82rem;font-weight:600;">Set as default settlement account</span>
            </label>
            <div id="addAcctMsg" class="feedback-msg"></div>
            <button type="button" id="addAcctBtn" class="btn btn-primary" style="border-radius:14px;font-size:0.88rem;font-weight:800;padding:11px 18px;">Add Settlement Account</button>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = <?= json_encode($csrfToken) ?>;

    // ── Institution autocomplete data ────────────────────────────────────────
    const INST = {
        bank: {
            'Universal / Commercial Banks': [
                'Asia United Bank Corporation','Bank of China (HK) Limited \u2013 Manila Branch',
                'Bank of Commerce','Bank of the Philippine Islands (BPI)','BDO Unibank, Inc.',
                'China Banking Corporation (China Bank)','CIMB Bank Philippines, Inc.',
                'CTBC Bank (Philippines) Corporation','Development Bank of the Philippines (DBP)',
                'East West Banking Corporation','Land Bank of the Philippines',
                'Maybank Philippines, Inc.','Metropolitan Bank and Trust Company (Metrobank)',
                'Philippine Bank of Communications (PBCom)','Philippine National Bank (PNB)',
                'Philippine Trust Company (Philtrust)','Philippine Veterans Bank',
                'Rizal Commercial Banking Corporation (RCBC)','Security Bank Corporation',
                'Standard Chartered Bank','The Hongkong and Shanghai Banking Corporation (HSBC)',
                'Union Bank of the Philippines'
            ],
            'Thrift Banks': [
                'AllBank (A Thrift Bank), Inc.','BDO Network Bank, Inc.',
                'BPI Direct BanKo, Inc., A Savings Bank','Card SME Bank Inc., A Thrift Bank',
                'China Bank Savings, Inc.','City Savings Bank, Inc.',
                'Equicom Savings Bank, Inc.','ISLA Bank (A Thrift Bank), Inc.',
                'Legazpi Savings Bank, Inc.','Luzon Development Bank',
                'Malayan Savings Bank, Inc.','Pacific Ace Savings Bank, Inc.',
                'Philippine Business Bank, Inc., A Savings Bank','Philippine Savings Bank (PSBank)',
                'Producers Savings Bank Corporation',
                'Queen City Development Bank (Queenbank), A Thrift Bank',
                'Sterling Bank of Asia, Inc. (A Savings Bank)','Sun Savings Bank, Inc.',
                'UCPB Savings Bank','Wealth Development Bank Corporation'
            ],
            'Rural / Cooperative Banks': [
                'Bangko Mabuhay (A Rural Bank), Inc.','Camalig Bank, Inc. (A Rural Bank)',
                'Cantilan Bank, Inc. (A Rural Bank)',
                'Card Bank, Inc. (A Microfinance-Oriented Rural Bank)',
                'CARD MRI Rizal Bank, Inc.','Cebuana Lhuillier Rural Bank, Inc.',
                'Dungganon Bank (A Microfinance Rural Bank), Inc.',
                'East West Rural Bank, Inc.','Entrepreneur Rural Bank, Inc.',
                'MariBank Philippines Inc. (A Rural Bank)',
                'Mindanao Consolidated Cooperative Bank','Netbank (A Rural Bank), Inc.',
                'Own Bank, The Rural Bank of Cavite City, Inc.',
                'Partner Rural Bank (Cotabato), Inc.','Quezon Capital Rural Bank, Inc.',
                'Rang-Ay Bank, Inc. (A Rural Bank)','Rural Bank of Guinobatan, Inc.',
                'Vigan Banco Rural, Incorporada (VBRI)'
            ],
            'Digital Banks': [
                'GoTyme Bank Corporation','Maya Bank, Inc.','Tonik Digital Bank, Inc.',
                'Union Digital Bank','UNObank, Inc.'
            ]
        },
        ewallet: {
            'E-Wallets / EMI-NBFIs': [
                'Alipay Philippines, Inc.','CIS Bayad Center, Inc.',
                'DCPAY Philippines, Inc.','Easypay Global EMI Corporation',
                'Ecashpay Asia, Inc.','G-Xchange, Inc. (GCash)',
                'Gpay Network PH, Inc. (GrabPay)','I-Remit, Inc.','Infoserve, Inc.',
                'MarcoPay, Inc.','Maya Philippines, Inc.','OmniPay, Inc.',
                'PayMongo Payments, Inc.','Paynamics Technologies, Inc.',
                'Peppermint Bizmoto Inc.','Philippine Digital Asset Exchange, Inc.',
                'PPS-PEPP Financial Services Corp. (PalawanPay)',
                'ShopeePay Philippines, Inc.','SpeedyPay, Inc.','StarPay Corporation',
                'TayoCash, Inc.','Toktokwallet, Inc.','TopJuan Tech Corporation',
                'Toyota Financial Services Philippines Corporation',
                'Traxion Pay, Inc.','USSC Money Services, Inc.',
                'Wise Pilipinas, Inc.','Zybi Tech, Inc.'
            ]
        }
    };

    // ── Autocomplete ─────────────────────────────────────────────────────────
    const acInput  = document.getElementById('newPayoutInstitution_search');
    const acHidden = document.getElementById('newPayoutInstitution');
    const acList   = document.getElementById('newPayoutInstitution_list');
    let acType = 'bank';
    let acFocusIdx = -1;

    function acGetOptions(query) {
        const q = (query || '').toLowerCase().trim();
        const groups = acType === 'bank'
            ? Object.entries(INST.bank)
            : Object.entries(INST.ewallet);
        const out = [];
        for (const [grp, items] of groups) {
            const filtered = q ? items.filter(i => i.toLowerCase().includes(q)) : items;
            if (filtered.length) {
                out.push({ t: 'g', label: grp });
                filtered.forEach(v => out.push({ t: 'i', value: v }));
            }
        }
        return out;
    }

    function acRender(query) {
        const opts = acGetOptions(query);
        acList.innerHTML = '';
        acFocusIdx = -1;
        if (!opts.length) {
            const el = document.createElement('div');
            el.className = 'inst-no-results';
            el.textContent = query ? 'No matches found.' : 'No institutions available.';
            acList.appendChild(el);
        } else {
            opts.forEach(function (opt) {
                const el = document.createElement('div');
                if (opt.t === 'g') {
                    el.className = 'inst-group-label';
                    el.textContent = opt.label;
                } else {
                    el.className = 'inst-option';
                    el.textContent = opt.value;
                    el.dataset.value = opt.value;
                    el.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        acSelect(opt.value);
                    });
                }
                acList.appendChild(el);
            });
        }
        acList.style.display = 'block';
    }

    function acSelect(value) {
        acInput.value = value;
        acHidden.value = value;
        acList.style.display = 'none';
        acFocusIdx = -1;
    }

    acInput.addEventListener('input', function () {
        acHidden.value = '';
        acRender(this.value);
    });
    acInput.addEventListener('focus', function () { acRender(this.value); });
    acInput.addEventListener('blur', function () {
        setTimeout(function () { acList.style.display = 'none'; }, 200);
    });
    acInput.addEventListener('keydown', function (e) {
        const focusable = [...acList.querySelectorAll('.inst-option')];
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            acFocusIdx = Math.min(acFocusIdx + 1, focusable.length - 1);
            focusable.forEach((o, i) => o.classList.toggle('ac-focused', i === acFocusIdx));
            if (focusable[acFocusIdx]) focusable[acFocusIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            acFocusIdx = Math.max(acFocusIdx - 1, 0);
            focusable.forEach((o, i) => o.classList.toggle('ac-focused', i === acFocusIdx));
        } else if (e.key === 'Enter' && acFocusIdx >= 0 && focusable[acFocusIdx]) {
            e.preventDefault();
            acSelect(focusable[acFocusIdx].dataset.value);
        } else if (e.key === 'Escape') {
            acList.style.display = 'none';
        }
    });

    // Bank / E-wallet type filter
    function filterInstitutions(type) {
        acType = type;
        acInput.value = '';
        acHidden.value = '';
        acList.style.display = 'none';
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
        const institution = acHidden.value.trim();
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
