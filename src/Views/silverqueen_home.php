<?php
// SilverQueen public landing page. Authentication remains on the existing /login and /register routes.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#050A10">
  <meta name="description" content="Discover SilverQueen: a Philippine platform connecting barangay communities through social feeds, neighborhood commerce, and local last-mile delivery.">
  <meta name="keywords" content="SilverQueen, barangay, Philippines, barangay e-commerce, community marketplace, social commerce, last-mile delivery, local riders, Ginto AI, digital barangay, Luzon, Visayas, Mindanao">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="https://silverqueen.pro/login">
  <link rel="icon" type="image/svg+xml" href="/assets/favicon/favicon.svg">
  <link rel="apple-touch-icon" href="/assets/favicon/apple-touch-icon.png">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="SilverQueen">
  <meta property="og:title" content="SilverQueen — Social Trade for Every Barangay">
  <meta property="og:description" content="A Philippine platform bringing community, local sellers, and last-mile delivery together across barangays.">
  <meta property="og:url" content="https://silverqueen.pro/login">
  <meta property="og:image" content="https://silverqueen.pro/assets/silverqueen/images/archipelago.jpeg">
  <meta property="og:image:alt" content="A night view of the Philippine archipelago illuminated by community lights">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="SilverQueen — Social Trade for Every Barangay">
  <meta name="twitter:description" content="Community feeds, neighborhood commerce, and local delivery connecting Philippine barangays.">
  <meta name="twitter:image" content="https://silverqueen.pro/assets/silverqueen/images/archipelago.jpeg">
  <title>SilverQueen — Illuminating 40,000+ Barangays</title>
  <link rel="stylesheet" href="/assets/silverqueen/story.css">
  <script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"SilverQueen","url":"https://silverqueen.pro/","description":"Philippine community social commerce and last-mile delivery platform connecting barangays.","sameAs":["https://github.com/oliverbob/ginto.ai"]}</script>
  <script src="/assets/silverqueen/story.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#cover">Skip to story</a>
  <header class="site-header">
    <a class="wordmark" href="#cover" aria-label="SilverQueen home"><span class="wordmark-mark" aria-hidden="true">SQ</span><span>SilverQueen</span></a>
    <div class="header-note">A barangay-first movement</div>
    <a class="header-login" href="#auth">Log in</a>
    <a class="header-cta" href="/register">Join the Movement <span aria-hidden="true">↗</span></a>
  </header>
  <main>
    <div class="story" id="story">
      <div class="map-stage" aria-hidden="true">
        <picture class="map-image"><source media="(max-width: 760px)" srcset="/assets/silverqueen/images/ph.jpeg"><img src="/assets/silverqueen/images/archipelago.jpeg" alt="" fetchpriority="high"></picture>
        <canvas id="philippines-map"></canvas>
        <div class="map-vignette"></div>
        <div class="map-caption"><span class="live-dot"></span><span id="map-state">ILLUSTRATIVE NETWORK · COVER</span></div>
        <div class="map-legend"><span><i class="legend-dot legend-gold"></i>Origin</span><span><i class="legend-dot legend-cyan"></i>Connection</span><span><i class="legend-dot legend-magenta"></i>Local activity</span></div>
        <div class="map-footnote">Illustrative sample · not live coverage</div>
      </div>
      <div class="chapters">
        <section class="chapter chapter-cover" id="cover" data-chapter="cover">
          <div class="panel cover-panel">
            <p class="eyebrow"><span class="eyebrow-rule"></span> SOCIAL TRADE · LOCAL DELIVERY</p>
            <h1>SilverQueen:<br><span class="title-glow">Illuminating</span><br>40,000+ Barangays</h1>
            <p class="lede">Social connection, last-mile delivery, and community commerce, in every barangay.</p>
            <div class="cover-actions">
              <a class="button button-primary" href="/register">Join the Movement <span aria-hidden="true">↗</span></a>
              <span class="scroll-cue"><span class="scroll-line"></span>Scroll to see the network</span>
            </div>
            <div class="cover-meta"><span>PHILIPPINES</span><span>COMMUNITY, CONNECTED</span></div>
          </div>
        </section>
        <section class="chapter chapter-gap" id="gap" data-chapter="gap">
          <div class="panel gap-panel">
            <p class="eyebrow"><span class="eyebrow-rule"></span> THE DISTANCE BETWEEN US</p>
            <h2>Great places.<br><span class="title-cyan">Uneven access.</span></h2>
            <p class="body-copy">Island geography can leave rural barangays outside fast delivery and direct local trade. The next customer, maker, or neighbor is often closer than the systems connecting them.</p>
            <div class="stat-block"><span class="stat-number" id="barangay-counter" aria-hidden="true">0</span><span class="stat-plus" aria-hidden="true">+</span><span class="stat-label">barangays across an archipelago</span></div>
            <p class="micro-note">A national-scale opportunity—not a claim of current platform coverage.</p>
          </div>
        </section>
        <section class="chapter chapter-pillars" id="pillars" data-chapter="pillars">
          <div class="pillar-pin">
            <div class="panel pillar-panel">
              <p class="eyebrow"><span class="eyebrow-rule"></span> THREE PILLARS · ONE LOCAL LOOP</p>
              <div class="pillar-heading"><h2>Every barangay<br>has a <span class="title-cyan">signal.</span></h2><p class="body-copy">People find one another. Local sellers reach nearby buyers. Riders carry the exchange to the doorstep.</p></div>
              <div class="pillar-deck" aria-live="polite">
                <article class="pillar-card is-active" data-pillar-card="0"><span class="pillar-index">01 / 03</span><p class="pillar-kicker">SOCIAL</p><h3>Belong before you buy.</h3><p>Local community feeds and member-to-member sharing turn nearby connections into trusted discovery.</p></article>
                <article class="pillar-card" data-pillar-card="1" aria-hidden="true"><span class="pillar-index">02 / 03</span><p class="pillar-kicker">MARKETPLACE</p><h3>Trade close to home.</h3><p>Local producers and micro-sellers offer goods directly to neighbors, keeping discovery and opportunity close.</p></article>
                <article class="pillar-card" data-pillar-card="2" aria-hidden="true"><span class="pillar-index">03 / 03</span><p class="pillar-kicker">DELIVERY</p><h3>The last mile is local.</h3><p>Barangay-level rider assignment and order tracking connect a local purchase to a local doorstep.</p></article>
              </div>
              <div class="pillar-controls" role="group" aria-label="Choose a SilverQueen pillar">
                <button class="pillar-step is-active" type="button" data-go-pillar="0" aria-label="Social pillar" aria-pressed="true"><span></span></button>
                <button class="pillar-step" type="button" data-go-pillar="1" aria-label="Marketplace pillar" aria-pressed="false"><span></span></button>
                <button class="pillar-step" type="button" data-go-pillar="2" aria-label="Delivery pillar" aria-pressed="false"><span></span></button>
                <span class="pillar-hint">Scroll to explore</span>
              </div>
            </div>
          </div>
        </section>
        <section class="chapter chapter-ignition" id="network" data-chapter="ignition">
          <div class="panel ignition-panel">
            <p class="eyebrow"><span class="eyebrow-rule"></span> A NETWORK ROLLOUT VISION</p>
            <h2>One island at a time.<br><span class="title-gold">One network together.</span></h2>
            <p class="body-copy">Start with local sparks in Luzon. Link the Visayas. Bring Mindanao into the route. Let each connection make the next one possible.</p>
            <div class="rollout-list" aria-label="Illustrative rollout sequence">
              <div class="rollout-row" data-region-step="0"><span>01</span><i></i><b>Luzon</b><small>Local sparks</small></div>
              <div class="rollout-row" data-region-step="1"><span>02</span><i></i><b>Visayas</b><small>Island links</small></div>
              <div class="rollout-row" data-region-step="2"><span>03</span><i></i><b>Mindanao</b><small>Regional reach</small></div>
              <div class="rollout-row" data-region-step="3"><span>04</span><i></i><b>One network</b><small>Shared momentum</small></div>
            </div>
            <p class="vision-note"><span class="vision-icon">✳</span> Network rollout vision — not a claim of current coverage.</p>
          </div>
        </section>
        <section class="chapter chapter-engine" id="engine" data-chapter="engine">
          <div class="panel engine-panel">
            <p class="eyebrow"><span class="eyebrow-rule"></span> THE BARANGAY ENGINE</p>
            <h2>A small order.<br><span class="title-magenta">A local chain in motion.</span></h2>
            <p class="body-copy">A neighborhood order can move through one connected loop—from a seller’s shelf to a rider nearby, with every handoff visible.</p>
            <div class="dashboard" id="sample-dashboard" data-lazy>
              <div class="dashboard-top"><span class="dashboard-brand"><i></i> SQ / LOCAL LOOP</span><span class="sample-label">SAMPLE DATA</span></div>
              <div class="order-card">
                <div class="order-head"><span class="order-id">ORDER · BRGY-0418</span><span class="order-price">₱480</span></div>
                <div class="order-route"><span class="route-origin">Merchant</span><i></i><span class="route-destination">Neighbor</span></div>
                <div class="order-stages" id="order-stages">
                  <div class="order-stage is-current" data-order-stage="0"><span class="stage-check">01</span><b>Placed</b></div>
                  <div class="order-stage" data-order-stage="1"><span class="stage-check">02</span><b>Rider assigned</b></div>
                  <div class="order-stage" data-order-stage="2"><span class="stage-check">03</span><b>In transit</b></div>
                  <div class="order-stage" data-order-stage="3"><span class="stage-check">04</span><b>Delivered</b></div>
                </div>
                <div class="order-progress"><span id="order-progress-bar"></span></div>
              </div>
              <div class="dashboard-metrics">
                <div class="metric-card"><span class="metric-label">MERCHANT SALES</span><strong>₱12,480</strong><small>Today · sample</small><div class="mini-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
                <div class="metric-card rider-metric"><span class="metric-label">RIDER EARNINGS</span><strong>₱1,240</strong><small>Today · sample</small><div class="rider-route" aria-hidden="true"><i></i><i></i><i></i></div></div>
              </div>
              <div class="dashboard-foot"><span class="sample-pulse"></span> Barangay 0418 · Illustrative activity</div>
            </div>
          </div>
        </section>
        <section class="chapter chapter-cta" id="join" data-chapter="cta">
          <div class="panel cta-panel">
            <p class="eyebrow"><span class="eyebrow-rule"></span> THE NEXT CONNECTION STARTS HERE</p>
            <h2>Empowering every community.<br><span class="title-cyan">Bridging every island.</span></h2>
            <p class="lede">A more connected Philippines begins with the people already making their barangay move.</p>
            <div class="cta-actions"><a class="button button-quiet" href="#network">Explore the Network <span aria-hidden="true">↓</span></a><a class="button button-primary" href="/register">Join the Movement <span aria-hidden="true">↗</span></a></div>
            <p class="cta-note">Social connection. Community commerce. Last-mile delivery.</p>
          </div>
        </section>
        <section class="chapter chapter-auth" id="auth" data-chapter="auth" aria-labelledby="auth-heading">
          <div class="auth-content">
            <p class="eyebrow"><span class="eyebrow-rule"></span> YOUR NEXT CONNECTION STARTS HERE</p>
            <h2 id="auth-heading">Welcome to <span class="title-cyan">SilverQueen</span></h2>
            <p class="body-copy">Sign in to continue, or join the movement and connect with your community.</p>
            <?php require __DIR__ . '/user/login_form_partial.php'; ?>
          </div>
        </section>
      </div>
    </div>
  </main>
  <footer class="site-footer sq-disclosure-footer" aria-label="SilverQueen and Ginto public disclosures">
    <div class="sq-disclosure-inner">
      <a class="footer-brand" href="#cover">SilverQueen</a>
      <p><strong>silverqueen.pro</strong> is the full implementation of <a href="https://github.com/oliverbob/ginto.ai" target="_blank" rel="noopener noreferrer">Ginto AI</a>, the first business example implementing the full scale and power of the project.</p>
      <p><strong>sq.silverqueen.pro</strong> and <strong>gk.silverqueen.pro</strong> are child projects built around the same projects by GitHub user <strong>oliverbob</strong>.</p>
      <p>The vision is to help digitize the <strong>42,064 barangays</strong> of the Philippines, while welcoming project ventures around the world that strictly do not involve venture capitalists.</p>
      <p>The author’s stage name is <strong>Bob Reyes</strong>, also known as <strong>Datu Gyud</strong> in SilverQueen.</p>
      <p>These projects are run and managed by their current operator, <strong>Ginto Services Corporation</strong> (Company Reg. No. <strong>2026040246055-25</strong>), registered in the Philippines in April 2026 to accommodate the potential of Ginto AI. This does not automatically make the corporation the owner of Ginto AI: Ginto AI is Bob Reyes’s open-source project and predates SilverQueen.</p>
      <p>All online transactions are reported to the <strong>Bureau of Internal Revenue</strong> for full compliance.</p>
      <p>For public disclosures, see <a href="https://sq.silverqueen.pro" target="_blank" rel="noopener noreferrer">sq.silverqueen.pro</a> or use these direct links:</p>
      <nav class="sq-disclosure-links" aria-label="Public disclosures">
        <a href="https://sq.silverqueen.pro/terms" target="_blank" rel="noopener noreferrer">Terms &amp; User Policy</a><span aria-hidden="true">·</span>
        <a href="https://sq.silverqueen.pro/privacy" target="_blank" rel="noopener noreferrer">Privacy</a><span aria-hidden="true">·</span>
        <a href="https://sq.silverqueen.pro/about" target="_blank" rel="noopener noreferrer">About</a>
      </nav>
      <img src="https://comchain.silverqueen.pro/assets/img/bir-registration-seal.png" alt="BIR Registration Seal" loading="lazy">
      <span class="footer-end">Illustrative concept · Philippines</span>
    </div>
  </footer>
  <?php if (!empty($loginError)): ?>
  <script>window.addEventListener('load',function(){document.getElementById('auth')?.scrollIntoView({block:'start'});});</script>
  <?php endif; ?>
</body>
</html>
