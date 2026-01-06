<?php
/**
 * Minimal commissions view. Expects `$data` to be passed with precomputed JSON.
 * If `$data` is not provided the client will call the API.
 */
$pre = $data ?? null;
// include shared layout pieces
include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
// set page title for topbar
$title = 'My Commissions';
include __DIR__ . '/../layout/topbar.php';
?>

<div class="commissions-page p-6">
    <h1>My Commissions</h1>

    <div id="commissions-summary">
        <script>window.PRELOADED_COMMISSIONS = null;</script>
        <div id="commissions-table-container"></div>
    </div>

    <div id="commissions-controls" class="mt-4">
        <label>Depth: <span class="select-wrapper"><select id="depth-select"></select></span></label>
        <button id="refresh-commissions">Refresh</button>
        <button id="toggle-currency" style="margin-left:12px;">Display in USD</button>
        <span id="currency-note" style="color:#b00;display:none;margin-left:8px;font-size:0.95em;">Converting this to dollar will incur additional charges.</span>
    </div>

    <div id="commissions-details"></div>
</div>

<?php // Close mainContent and include footer ?>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>

<style>
    .commissions-page { font-family: Arial, sans-serif; }
    table.comms { border-collapse: collapse; width: 100%; }
    table.comms th, table.comms td { border: 1px solid #ddd; padding: 8px; }
    /* Make depth selector more visible and larger */
    #commissions-controls { display:flex; align-items:center; gap:12px; }
    #depth-select {
        min-width: 140px;
        padding: 0.45rem 0.7rem;
        font-size: 14px;
        border-radius: 0.375rem;
        border: 1px solid var(--border-color, #cbd5e0);
        background: var(--card-bg, #ffffff);
        color: var(--text-primary, #111827);
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 2.2rem; /* make room for the custom arrow */
    }
    /* Ensure the small caret doesn't overlap text on some browsers */
    #depth-select::-ms-expand { display:none; }
    /* wrapper to draw a theme-aware dropdown pointer */
    .select-wrapper{ position: relative; display:inline-block; }
    .select-wrapper::after{
        content: '';
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 0; height: 0;
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        /* use the secondary text variable for a slightly lighter pointer */
        border-top: 8px solid var(--text-secondary);
        opacity: 0.9;
        pointer-events: none;
    }
    /* dark theme: make the pointer lighter if needed */
    :root[class~="dark"] .select-wrapper::after {
        border-top-color: var(--text-primary);
    }
</style>

<script>
(() => {
    const rates = window.PRELOADED_COMMISSIONS?.commissionRates || [0.05,0.04,0.03,0.02,0.01,0.005,0.0025,0.0025,0.00];
    const maxDepth = rates.length;
    const depthSelect = document.getElementById('depth-select');
    for (let i=1;i<=maxDepth;i++){
        const o = document.createElement('option');
        o.value = i;
        o.textContent = 'Level ' + i; // show descriptive label
        if (i === maxDepth) o.selected = true; // default to deepest level on first load
        depthSelect.appendChild(o);
    }

    let currentDepth = maxDepth;
    depthSelect.value = String(currentDepth);

    // currency display state (default from server payload when available)
    let currencyState = { symbol: 'P', rate: 0.018, isUsd: false };

    function renderTable(data, depth){
        const container = document.getElementById('commissions-table-container');
        if (!data) { container.innerHTML = '<p>Loading...</p>'; return; }

        // initialize currencyState from server-provided payload if present
        if (data.currencySymbol) currencyState.symbol = data.currencySymbol;
        if (typeof data.phpToUsd === 'number') currencyState.rate = Number(data.phpToUsd) || currencyState.rate;
        if (data.currency && !data.currencySymbol && data.currency === 'PHP') currencyState.symbol = 'P';

        const rows = [];
        // helper to format with optional conversion
        function formatCurrencyRaw(v){
            const val = Number(v || 0);
            if (currencyState.isUsd) return '$' + (val * currencyState.rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return currencyState.symbol + val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        rows.push('<table class="comms"><thead><tr><th>Level</th><th>Downline Count</th><th>Sum</th><th>Rate</th><th>Commission</th></tr></thead><tbody>');
        for (let i = 0; i < depth; i++) {
            const level = i + 1;
            const count = data.perLevelCounts[i] || 0;
            const sum = data.perLevelSums[i] || 0;

            let rate = 0;
            if (data.commissionRates && typeof data.commissionRates[i] !== 'undefined') {
                rate = parseFloat(data.commissionRates[i].replace('%', '')) / 100;
            }

            const commission = data.perLevelEarnings[i] || 0;

            const rateDisplay = `${(rate * 100).toFixed(2)}%`;

            rows.push(`<tr><td>${level}</td><td>${count}</td><td>${formatCurrencyRaw(sum)}</td><td>${rateDisplay}</td><td>${formatCurrencyRaw(commission)}</td></tr>`);
        }
        rows.push('</tbody></table>');
        // single summary line (projected downline commissions up to displayed depth)
        const earnings = data.perLevelEarnings || [];
        let projected = 0;
        for (let j = 0; j < depth && j < earnings.length; j++) {
            projected += (earnings[j] || 0);
        }

        const convPercent = Number(data.conversionRate || 0);
        const phpToUsd = Number(data.phpToUsd || currencyState.rate || 0.018);

        if (currencyState.isUsd) {
            const beforeUsd = projected * phpToUsd;
            const afterUsd = beforeUsd * (1 - (convPercent / 100));
            rows.push(`<p><strong>Summary (depth ${depth}):</strong> Projected commissions (downline up to level ${depth}): <strong>$${beforeUsd.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</strong> before charges, <strong>$${afterUsd.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</strong> after ${convPercent}% conversion charges.</p>`);
        } else {
            rows.push(`<p><strong>Summary (depth ${depth}):</strong> Projected commissions (downline up to level ${depth}): ${formatCurrencyRaw(projected)} <small style="margin-left:8px;color:#666;">(If converted to USD, ${convPercent}% conversion fee will apply)</small></p>`);
        }
        container.innerHTML = rows.join('');
    }

    async function fetchApi(depth){
        const res = await fetch(`/api/user/commissions?depth=${depth}&format=json`);
        if (!res.ok) throw new Error('Fetch failed');
        return await res.json();
    }

    async function loadAndRender(depth){
        let data = window.PRELOADED_COMMISSIONS;
        if (!data) {
            try { data = (await fetchApi(depth)); if (data.success) data = data; else data = null; } catch(e){ data=null; }
        }
        if (data && data.success) renderTable(data, depth);
        else if (data && !data.success) document.getElementById('commissions-table-container').innerText = 'Error: ' + (data.message||'Unknown');
        else document.getElementById('commissions-table-container').innerText = 'No data';
    }

    document.getElementById('refresh-commissions').addEventListener('click', ()=>{ loadAndRender(currentDepth); });
    depthSelect.addEventListener('change', (e)=>{ currentDepth = parseInt(e.target.value,10); loadAndRender(currentDepth); });

    // currency toggle handler
    const toggleBtn = document.getElementById('toggle-currency');
    const noteEl = document.getElementById('currency-note');
    toggleBtn.addEventListener('click', ()=>{
        // warn user before converting
        const ok = confirm('Converting displayed amounts to USD will incur additional charges. Continue?');
        if (!ok) return;
        // toggle USD display
        currencyState.isUsd = !currencyState.isUsd;
        // show a persistent note when in USD mode
        noteEl.style.display = currencyState.isUsd ? 'inline' : 'none';
        toggleBtn.textContent = currencyState.isUsd ? 'Display in PHP' : 'Display in USD';
        // re-render with the current depth
        loadAndRender(currentDepth);
    });

    // initial
    loadAndRender(currentDepth);
})();
</script>
