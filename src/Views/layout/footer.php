<!-- Back to Top Button logic only -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        const updateBackToTopVisibility = () => {
            if (window.scrollY > 300) {
                backToTopButton.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
            } else {
                backToTopButton.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
            }
        };
        updateBackToTopVisibility();
        window.addEventListener('scroll', updateBackToTopVisibility);
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>

<!-- Uses inline styles so it stays centered even if Tailwind build omits certain utility classes -->
<div id="universalModal" aria-hidden="true" style="position:fixed; inset:0; z-index:9999; display:none;">
    <div id="universalModalBackdrop" style="position:absolute; inset:0; background:rgba(0,0,0,0.55);"></div>
    <div style="position:relative; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div id="universalModalCard" style="width:100%; max-width:460px; border-radius:14px; border:1px solid rgba(148,163,184,0.35); background:#ffffff; box-shadow:0 20px 45px rgba(0,0,0,0.25);">
            <div style="padding:18px 18px 16px 18px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <div id="universalModalIconWrap" style="margin-top:2px; color:#f59e0b;">
                        <i id="universalModalIcon" class="fas fa-exclamation-circle" style="font-size:20px;"></i>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <div id="universalModalTitle" style="font-size:18px; font-weight:700; color:#0f172a;">Notice</div>
                        <div id="universalModalMessage" style="margin-top:6px; font-size:14px; color:#475569;">&nbsp;</div>
                    </div>
                </div>
                <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                    <button id="universalModalOk" type="button" style="padding:9px 14px; border-radius:10px; border:1px solid #4f46e5; background:#4f46e5; color:#ffffff; font-size:14px; font-weight:600; cursor:pointer;">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    if (window.showModal) return; // don't override if already defined
    const modal = document.getElementById('universalModal');
    const backdrop = document.getElementById('universalModalBackdrop');
    const okBtn = document.getElementById('universalModalOk');
    const titleEl = document.getElementById('universalModalTitle');
    const msgEl = document.getElementById('universalModalMessage');
    const iconEl = document.getElementById('universalModalIcon');
    const iconWrap = document.getElementById('universalModalIconWrap');
    const card = document.getElementById('universalModalCard');

    function hide() {
        if (!modal) return;
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        const cb = modal.__onClose;
        modal.__onClose = null;
        if (typeof cb === 'function') {
            try { cb(); } catch(e) {}
        }
    }

    if (backdrop) backdrop.addEventListener('click', hide);
    if (okBtn) okBtn.addEventListener('click', hide);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.style.display !== 'none') hide();
    });

    window.showModal = function(title, message, icon = 'fas fa-exclamation-circle', iconColor = 'text-yellow-500', onClose = null, buttonText = 'OK') {
        if (!modal) {
            alert(message || title || '');
            if (typeof onClose === 'function') { try { onClose(); } catch(e) {} }
            return;
        }
        if (titleEl) titleEl.textContent = String(title || 'Notice');
        if (msgEl) msgEl.textContent = String(message || '');
            if (iconEl) iconEl.className = String(icon || 'fas fa-exclamation-circle');
            if (iconWrap) iconWrap.style.color = String(iconColor || '#f59e0b').includes('text-') ? '#f59e0b' : String(iconColor || '#f59e0b');
        if (okBtn) okBtn.textContent = String(buttonText || 'OK');

            // Basic dark-mode adaptation based on html.dark
            try {
                const isDark = document.documentElement && document.documentElement.classList.contains('dark');
                if (card) {
                    card.style.background = isDark ? '#0b1220' : '#ffffff';
                    card.style.borderColor = isDark ? 'rgba(51,65,85,0.8)' : 'rgba(148,163,184,0.35)';
                }
                if (titleEl) titleEl.style.color = isDark ? '#e6eef8' : '#0f172a';
                if (msgEl) msgEl.style.color = isDark ? '#cbd5e1' : '#475569';
            } catch (e) {}

        modal.__onClose = onClose;
            modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => { try { okBtn && okBtn.focus(); } catch(e) {} }, 0);
    };
})();
</script>

</body></html>