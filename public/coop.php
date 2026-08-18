<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Primary Meta -->
<title>Ginto Cooperative — Member Guidelines</title>
<meta name="description" content="Official membership guidelines for Ginto Cooperative. Learn about membership requirements, loan options, repayment terms, and penalty policies.">
<meta name="theme-color" content="#1A1610">

<!-- Favicon -->
<link rel="icon" type="image/png" href="https://silverqueen.pro/assets/images/ginto.png">
<link rel="apple-touch-icon" href="https://silverqueen.pro/assets/images/ginto.png">

<!-- Open Graph (Facebook, Messenger, Viber, LinkedIn) -->
<meta property="og:type" content="website">
<meta property="og:title" content="Ginto Cooperative — Member Guidelines">
<meta property="og:description" content="Official membership guidelines for Ginto Cooperative. Membership, loan programs, and repayment policy.">
<meta property="og:image" content="https://silverqueen.pro/assets/images/ginto.png">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta property="og:url" content="https://silverqueen.pro">
<meta property="og:site_name" content="Ginto Cooperative">

<!-- Twitter / X Card -->
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="Ginto Cooperative — Member Guidelines">
<meta name="twitter:description" content="Official membership guidelines for Ginto Cooperative. Membership, loan programs, and repayment policy.">
<meta name="twitter:image" content="https://silverqueen.pro/assets/images/ginto.png">
<title>Ginto Cooperative — Member Guidelines</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  :root {
    --gold: #C9A84C;
    --gold-light: #F5EDD8;
    --gold-dark: #8B6914;
    --dark: #1A1610;
    --dark-2: #2C2519;
    --mid: #5C4F38;
    --muted: #9A8B72;
    --surface: #FDFAF4;
    --surface-2: #F5EDD8;
    --border: rgba(201,168,76,0.25);
    --border-strong: rgba(201,168,76,0.5);
    --text: #1A1610;
    --text-muted: #6B5D45;
    --radius: 12px;
    --radius-sm: 8px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--surface);
    color: var(--text);
    line-height: 1.6;
    min-height: 100vh;
  }

  /* HEADER */
  .page-header {
    background: var(--dark);
    padding: 2.5rem 1.5rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
  }

  .page-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: repeating-linear-gradient(
      45deg,
      transparent,
      transparent 40px,
      rgba(201,168,76,0.04) 40px,
      rgba(201,168,76,0.04) 41px
    );
  }

  .header-inner { position: relative; z-index: 1; max-width: 600px; margin: 0 auto; }

  .coop-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px; height: 52px;
    border-radius: 50%;
    border: 1.5px solid var(--gold);
    margin-bottom: 1rem;
  }

  .coop-logo svg { width: 26px; height: 26px; }

  .header-eyebrow {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 0.5rem;
  }

  h1 {
    font-family: 'DM Serif Display', serif;
    font-size: clamp(1.6rem, 5vw, 2.4rem);
    color: #FDFAF4;
    line-height: 1.2;
    margin-bottom: 0.5rem;
  }

  .header-sub {
    font-size: 13px;
    color: var(--muted);
    letter-spacing: 0.03em;
  }

  .header-divider {
    width: 40px;
    height: 1px;
    background: var(--gold);
    margin: 1rem auto 0;
    opacity: 0.6;
  }

  /* DOWNLOAD BUTTON */
  .download-bar {
    background: var(--dark-2);
    padding: 0.75rem 1.5rem;
    display: flex;
    justify-content: center;
  }

  .btn-download {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--gold);
    color: var(--dark);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.55rem 1.25rem;
    border-radius: 100px;
    border: none;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    text-decoration: none;
  }

  .btn-download:hover { background: #D4B060; }
  .btn-download:active { transform: scale(0.97); }

  .btn-download svg { width: 15px; height: 15px; flex-shrink: 0; }

  /* MAIN CONTENT */
  .main {
    max-width: 680px;
    margin: 0 auto;
    padding: 2rem 1.25rem 4rem;
  }

  /* SECTION */
  .section { margin-bottom: 2.25rem; }

  .section-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gold-dark);
    margin-bottom: 1rem;
  }

  .section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-strong);
  }

  /* CARD */
  .card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 0.75rem;
  }

  .card-head {
    background: var(--surface-2);
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
  }

  .card-head-title {
    font-family: 'DM Serif Display', serif;
    font-size: 15px;
    color: var(--dark);
  }

  .badge {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 100px;
  }

  .badge-gold { background: var(--gold-light); color: var(--gold-dark); border: 1px solid var(--border-strong); }
  .badge-green { background: #EAF5E9; color: #2D6A2D; border: 1px solid rgba(45,106,45,0.2); }
  .badge-blue { background: #EAF0FB; color: #1A3D8F; border: 1px solid rgba(26,61,143,0.2); }

  /* ROW TABLE */
  .row-table { padding: 0 1.25rem; }

  .row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
    padding: 0.65rem 0;
    border-bottom: 1px solid rgba(201,168,76,0.12);
    font-size: 14px;
  }

  .row:last-child { border-bottom: none; }

  .row-label { color: var(--text-muted); flex-shrink: 0; }
  .row-val { font-weight: 500; color: var(--dark); text-align: right; }

  /* NOTE */
  .note {
    margin: 0.5rem 1.25rem 1rem;
    padding: 0.65rem 1rem;
    background: var(--gold-light);
    border-left: 3px solid var(--gold);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font-size: 13px;
    color: var(--mid);
    line-height: 1.6;
  }

  /* METRICS GRID */
  .metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
  }

  .metric {
    background: var(--dark);
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    text-align: center;
  }

  .metric-label {
    font-size: 11px;
    color: var(--muted);
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 0.35rem;
  }

  .metric-value {
    font-family: 'DM Serif Display', serif;
    font-size: 1.9rem;
    color: var(--gold);
    line-height: 1;
  }

  .metric-sub {
    font-size: 11px;
    color: var(--muted);
    margin-top: 0.25rem;
  }

  /* EXAMPLE BOX */
  .example-box {
    background: var(--dark);
    border-radius: var(--radius);
    padding: 1.25rem;
    margin-bottom: 0.75rem;
  }

  .example-title {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 1rem;
  }

  .ex-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 14px;
  }

  .ex-row:last-child { border-bottom: none; }
  .ex-label { color: var(--muted); }
  .ex-val { color: #FDFAF4; font-weight: 500; }
  .ex-val.highlight { color: var(--gold); font-family: 'DM Serif Display', serif; font-size: 1.05rem; }

  /* FOOTER */
  .doc-footer {
    text-align: center;
    padding: 2rem 1.5rem;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--muted);
  }

  .doc-footer strong { color: var(--gold-dark); }

  /* PRINT STYLES */
  @media print {
    .download-bar { display: none !important; }
    body { background: white; }
    .page-header { background: #1A1610 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .metric { background: #1A1610 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .example-box { background: #1A1610 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .card { break-inside: avoid; }
    .section { break-inside: avoid; }
  }

  @media (max-width: 400px) {
    .metrics { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- HEADER -->
<header class="page-header">
  <div class="header-inner">
    <div class="coop-logo">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L14.5 8.5H21.5L16 12.5L18.5 19L12 15L5.5 19L8 12.5L2.5 8.5H9.5L12 2Z" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="header-eyebrow">Ginto Cooperative</div>
    <h1>Member Guidelines</h1>
    <p class="header-sub">Membership · Loan Programs · Repayment Policy</p>
    <div class="header-divider"></div>
  </div>
</header>

<!-- DOWNLOAD BAR -->
<div class="download-bar">
  <button class="btn-download" onclick="downloadPDF()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="7 10 12 15 17 10"/>
      <line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Download as PDF
  </button>
</div>

<!-- MAIN -->
<main class="main" id="doc-content">

  <!-- MEMBERSHIP -->
  <div class="section">
    <div class="section-label">Membership requirements</div>

    <div class="card">
      <div class="row-table">
        <div class="row">
          <span class="row-label">Valid government IDs</span>
          <span class="row-val">2 IDs + 2 pcs 1×1 photos</span>
        </div>
        <div class="row">
          <span class="row-label">Ginto AI store</span>
          <span class="row-val">Active store required</span>
        </div>
        <div class="row">
          <span class="row-label">Membership fee</span>
          <span class="row-val">₱1,000.00</span>
        </div>
        <div class="row">
          <span class="row-label">Capital share</span>
          <span class="row-val">₱2,000.00</span>
        </div>
        <div class="row">
          <span class="row-label">Capital share deadline</span>
          <span class="row-val">Within 10 months of joining</span>
        </div>
        <div class="row">
          <span class="row-label">Dividend</span>
          <span class="row-val">Declared annually, based on net surplus</span>
        </div>
      </div>
    </div>

    <div class="note">
      A Ginto AI online store is the recommended way to comply with the store membership requirement. No physical store is necessary — an active online store on the platform is sufficient.
    </div>
  </div>

  <!-- LOAN OPTIONS -->
  <div class="section">
    <div class="section-label">Loan options</div>

    <!-- REGULAR LOAN -->
    <div class="card">
      <div class="card-head">
        <span class="card-head-title">Regular Loan</span>
        <span class="badge badge-gold">6-month waiting period</span>
      </div>
      <div class="row-table">
        <div class="row">
          <span class="row-label">Eligibility</span>
          <span class="row-val">After 6 months of active membership</span>
        </div>
        <div class="row">
          <span class="row-label">Capital share required</span>
          <span class="row-val">Yes — no share, no loan</span>
        </div>
        <div class="row">
          <span class="row-label">Savings required</span>
          <span class="row-val">Not required</span>
        </div>
        <div class="row">
          <span class="row-label">Loan amount</span>
          <span class="row-val">Higher savings = higher loan ceiling</span>
        </div>
      </div>
    </div>

    <!-- INSTANT LOAN -->
    <div class="card">
      <div class="card-head">
        <span class="card-head-title">Instant Loan</span>
        <span class="badge badge-green">Available anytime</span>
      </div>
      <div class="row-table">
        <div class="row">
          <span class="row-label">Structure</span>
          <span class="row-val">Group lending — 6-member loan group</span>
        </div>
        <div class="row">
          <span class="row-label">Group requirement</span>
          <span class="row-val">You + 5 members, all compliant</span>
        </div>
        <div class="row">
          <span class="row-label">Minimum first loan</span>
          <span class="row-val">₱7,000.00</span>
        </div>
        <div class="row">
          <span class="row-label">Loan basis</span>
          <span class="row-val">40–50% of group's total combined capital shares</span>
        </div>
        <div class="row">
          <span class="row-label">Large capital share path</span>
          <span class="row-val">1 member with large share may substitute full group</span>
        </div>
      </div>
    </div>

    <div class="note">
      <strong>Group lending example:</strong> If one member of your loan group places ₱100,000 in capital share, you may borrow up to ₱50,000 (50% of their share) — without requiring the full 5-member group. The group requirement is waived when a single member's capital share sufficiently covers the loan.
    </div>
  </div>

  <!-- REPAYMENT -->
  <div class="section">
    <div class="section-label">Loan repayment</div>

    <div class="metrics">
      <div class="metric">
        <div class="metric-label">Repayment period</div>
        <div class="metric-value">10</div>
        <div class="metric-sub">months</div>
      </div>
      <div class="metric">
        <div class="metric-label">Interest rate</div>
        <div class="metric-value">2%</div>
        <div class="metric-sub">per month</div>
      </div>
    </div>

    <div class="example-box">
      <div class="example-title">Sample computation — ₱7,000 loan</div>
      <div class="ex-row">
        <span class="ex-label">Principal</span>
        <span class="ex-val">₱7,000.00</span>
      </div>
      <div class="ex-row">
        <span class="ex-label">Interest (2%/mo × 10 months)</span>
        <span class="ex-val">₱1,400.00</span>
      </div>
      <div class="ex-row">
        <span class="ex-label">Total payable</span>
        <span class="ex-val highlight">₱8,400.00</span>
      </div>
      <div class="ex-row">
        <span class="ex-label">Monthly payment</span>
        <span class="ex-val highlight">₱840.00 / month</span>
      </div>
    </div>

    <div class="card">
      <div class="row-table">
        <div class="row">
          <span class="row-label">Payment channels</span>
          <span class="row-val">GCash · Cash · Bank transfer</span>
        </div>
        <div class="row">
          <span class="row-label">Payment frequency</span>
          <span class="row-val">Monthly</span>
        </div>
      </div>
    </div>
  </div>

  <!-- PENALTY -->
  <div class="section">
    <div class="section-label">Penalty policy</div>

    <div class="card">
      <div class="card-head">
        <span class="card-head-title">Late Payment Penalty</span>
        <span class="badge badge-blue">1% / month</span>
      </div>
      <div class="row-table">
        <div class="row">
          <span class="row-label">Penalty rate</span>
          <span class="row-val">+1% per month on overdue balance</span>
        </div>
        <div class="row">
          <span class="row-label">Applied to</span>
          <span class="row-val">Unpaid portion of missed payment</span>
        </div>
        <div class="row">
          <span class="row-label">Duration</span>
          <span class="row-val">Until overdue amount is fully settled</span>
        </div>
      </div>
    </div>

    <div class="note">
      Penalties are applied monthly on any overdue balance remaining after a missed payment due date. Members are encouraged to coordinate with the cooperative in advance if facing payment difficulty.
    </div>
  </div>

</main>

<!-- FOOTER -->
<footer class="doc-footer">
  <p><strong>Ginto Cooperative</strong> &nbsp;·&nbsp; Official Member Guidelines</p>
  <p style="margin-top:4px">For inquiries, visit <strong>silverqueen.pro</strong> or contact your cooperative officer.</p>
</footer>

<script>
function downloadPDF() {
  const btn = document.querySelector('.btn-download');
  const originalText = btn.innerHTML;
  btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Generating PDF...';
  btn.disabled = true;

  const element = document.getElementById('doc-content');
  const header = document.querySelector('.page-header');
  const footer = document.querySelector('.doc-footer');

  const opt = {
    margin: 0,
    filename: 'Ginto-Cooperative-Member-Guidelines.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: '#FDFAF4'
    },
    jsPDF: {
      unit: 'mm',
      format: 'a4',
      orientation: 'portrait'
    },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };

  const clone = document.createElement('div');
  clone.style.cssText = 'background:#FDFAF4;font-family:DM Sans,sans-serif;';
  clone.appendChild(header.cloneNode(true));
  clone.appendChild(element.cloneNode(true));
  clone.appendChild(footer.cloneNode(true));

  document.body.appendChild(clone);

  html2pdf().set(opt).from(clone).save().then(() => {
    document.body.removeChild(clone);
    btn.innerHTML = originalText;
    btn.disabled = false;
  }).catch(() => {
    document.body.removeChild(clone);
    btn.innerHTML = originalText;
    btn.disabled = false;
  });
}
</script>

</body>
</html>