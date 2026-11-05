    </div> <!-- /.container wrapper -->

    <!-- Shared scripts -->
    <script src="/assets/js/dom.js"></script>
    <script>
        // Simple theme helper used across marketplace & seller pages
        function applyTheme(theme){ if(theme==='light'){ document.body.classList.add('light'); document.getElementById('themeIcon').textContent='🌙'; } else { document.body.classList.remove('light'); document.getElementById('themeIcon').textContent='☀️'; } localStorage.setItem('epower_theme', theme); }
        document.getElementById('themeToggle')?.addEventListener('click', ()=>{ applyTheme(document.body.classList.contains('light') ? 'dark' : 'light'); });
        // Initialize from localStorage
        applyTheme(localStorage.getItem('epower_theme') || 'dark');
    </script>
</body>
</html>
