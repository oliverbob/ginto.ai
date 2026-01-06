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

</body></html>