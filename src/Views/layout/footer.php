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

<!-- Universal Modal (shared) -->
<div id="universalModal" class="fixed inset-0 z-[100] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" id="universalModalBackdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl">
            <div class="p-5">
                <div class="flex items-start gap-3">
                    <div id="universalModalIconWrap" class="mt-0.5 text-yellow-500">
                        <i id="universalModalIcon" class="fas fa-exclamation-circle text-xl"></i>
                    </div>
                    <div class="min-w-0">
                        <div id="universalModalTitle" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Notice</div>
                        <div id="universalModalMessage" class="mt-1 text-sm text-gray-600 dark:text-gray-300">&nbsp;</div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button id="universalModalOk" type="button" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium">OK</button>
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

    function hide() {
        if (!modal) return;
        modal.classList.add('hidden');
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
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) hide();
    });

    window.showModal = function(title, message, icon = 'fas fa-exclamation-circle', iconColor = 'text-yellow-500', onClose = null, buttonText = 'OK') {
        if (!modal) {
            alert(message || title || '');
            if (typeof onClose === 'function') { try { onClose(); } catch(e) {} }
            return;
        }
        if (titleEl) titleEl.textContent = String(title || 'Notice');
        if (msgEl) msgEl.textContent = String(message || '');
        if (iconEl) iconEl.className = String(icon || 'fas fa-exclamation-circle') + ' text-xl';
        if (iconWrap) {
            iconWrap.className = 'mt-0.5 ' + String(iconColor || 'text-yellow-500');
        }
        if (okBtn) okBtn.textContent = String(buttonText || 'OK');
        modal.__onClose = onClose;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => { try { okBtn && okBtn.focus(); } catch(e) {} }, 0);
    };
})();
</script>

</body></html>