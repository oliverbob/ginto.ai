<footer class="bg-gray-800 border-t border-gray-700 mt-12 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-400 text-sm">
        <p>&copy; <?= date('Y') ?> Ginto AI. All rights reserved.</p>
    </div>
</footer>

<script>
// Course search functionality - placed in footer to ensure content is loaded
(function() {
    const searchInput = document.getElementById('course-search');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        const courseCards = document.querySelectorAll('.course-card');
        const heroSections = document.querySelectorAll('.course-hero');
        
        // Hide/show hero sections based on search
        heroSections.forEach(function(hero) {
            hero.style.display = query ? 'none' : '';
        });
        
        // Filter course cards
        let visibleCount = 0;
        courseCards.forEach(function(card) {
            const title = card.querySelector('h3')?.textContent?.toLowerCase() || '';
            const description = card.querySelector('p')?.textContent?.toLowerCase() || '';
            const category = card.querySelector('span')?.textContent?.toLowerCase() || '';
            
            const matches = !query || title.includes(query) || description.includes(query) || category.includes(query);
            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });
        
        // Show "no results" message
        let noResultsEl = document.getElementById('search-no-results');
        const grid = document.querySelector('.course-card')?.parentElement;
        
        if (query && visibleCount === 0 && grid) {
            if (!noResultsEl) {
                noResultsEl = document.createElement('div');
                noResultsEl.id = 'search-no-results';
                noResultsEl.className = 'col-span-full text-center py-8';
                grid.appendChild(noResultsEl);
            }
            noResultsEl.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No courses found matching "<span class="font-medium">' + query + '</span>"</p>';
            noResultsEl.style.display = '';
        } else if (noResultsEl) {
            noResultsEl.style.display = 'none';
        }
    });
})();
</script>
