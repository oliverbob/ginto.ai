/*
 * SilverQueen Premium: the owner's payment simulation (owner, 2026-10-24:
 * "simulate the payment success ... only true with datu gyud").
 *
 * Served only to him by /sites/me/sim.js; members never receive this file.
 * It plays the confirming steps on screen and opens /sites/me?paid=1&sim=1,
 * the paid page drawn in memory. Nothing is paid, saved or published.
 */
(function () {
  const $ = (id) => document.getElementById(id);
  const simPage = /[?&]sim=1\b/.test(location.search);

  // On the simulated paid page, nothing may start a real checkout.
  if (simPage) {
    document.querySelectorAll('[data-renew]').forEach((b) => { b.disabled = true; b.title = 'Simulation: nothing to pay'; });
    return;
  }

  const qr = $('qr');
  if (!qr || !$('qrCheck')) return;

  const svgFrom = $('qrDown') && $('qrDown').querySelector('svg');
  function button() {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn ghost';
    b.dataset.sim = '1';
    if (svgFrom) {
      const s = svgFrom.cloneNode(false);
      s.innerHTML = '<path d="M8 5v14l11-7z"/>';
      b.appendChild(s);
    }
    b.appendChild(document.createTextNode(' Simulate payment success'));
    b.title = 'Only you see this. Nothing is paid or saved.';
    b.addEventListener('click', play);
    return b;
  }
  // One button, at the foot of the panel, whatever the panel shows.
  const row = document.createElement('div');
  row.className = 'qact';
  row.style.cssText = 'margin-top:14px;padding-top:14px;border-top:1px solid var(--line)';
  row.appendChild(button());
  qr.appendChild(row);

  const wait = (ms) => new Promise((r) => setTimeout(r, ms));
  async function play() {
    qr.querySelectorAll('[data-sim]').forEach((b) => { b.disabled = true; });
    $('qrCode').hidden = true; $('qrGone').hidden = true; $('qrCheck').hidden = false; $('ckMore').hidden = true;
    const steps = $('qrCheck').querySelectorAll('.steps li');
    const set = (i, cls) => { if (steps[i]) steps[i].className = cls; };
    set(0, 'done'); set(1, 'now'); set(2, '');
    $('ckSay').textContent = 'Confirming your payment…';
    $('ckTime').textContent = 'Simulation · nothing is paid';
    await wait(1600);
    set(1, 'done'); set(2, 'now');
    $('ckSay').textContent = 'Payment received.';
    await wait(1600);
    set(2, 'done');
    $('ckSay').textContent = 'Paid. Your site is going live…';
    await wait(1000);
    location.assign('/sites/me?paid=1&sim=1');
  }
})();
