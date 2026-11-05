<?php
/**
 * Simple Mall Front‑end (bob.php)
 * -------------------------------------------------
 * This page demonstrates a modern, responsive product‑listing
 * layout inspired by popular e‑commerce sites such as Shopee
 * and Lazada. It uses TailwindCSS (via CDN) for styling, supports
 * dark mode, and is fully accessible.
 *
 * For the purpose of this demo the product data is hard‑coded in
 * a PHP array. In a real application you would fetch this data
 * from a database or an API.
 */

// -----------------------------------------------------------------
// Sample product data – in a real app replace with DB query
// -----------------------------------------------------------------
$products = [
    [
        "id" => 1,
        "name" => "Wireless Bluetooth Headphones",
        "price" => 49.99,
        "image" => "https://picsum.photos/seed/headphones/600/400",
        "rating" => 4.5,
        "reviews" => 124,
    ],
    [
        "id" => 2,
        "name" => "Smart Fitness Watch",
        "price" => 79.00,
        "image" => "https://picsum.photos/seed/fitnesswatch/600/400",
        "rating" => 4.2,
        "reviews" => 87,
    ],
    [
        "id" => 3,
        "name" => "Portable Power Bank 20000mAh",
        "price" => 29.95,
        "image" => "https://picsum.photos/seed/powerbank/600/400",
        "rating" => 4.8,
        "reviews" => 210,
    ],
    [
        "id" => 4,
        "name" => "4K Ultra‑HD Action Camera",
        "price" => 119.99,
        "image" => "https://picsum.photos/seed/actioncamera/600/400",
        "rating" => 4.4,
        "reviews" => 56,
    ],
    [
        "id" => 5,
        "name" => "Ergonomic Office Chair",
        "price" => 199.00,
        "image" => "https://picsum.photos/seed/officechair/600/400",
        "rating" => 4.6,
        "reviews" => 34,
    ],
    // Add more products as needed
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bob's Mall – Product Store</title>
    <!-- TailwindCSS CDN – enables JIT mode & dark mode support -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Enable dark mode based on user preference or OS setting
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        /* Custom scrollbar for a polished feel */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background-color: rgba(100, 100, 100, 0.5);
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Bob's Mall</h1>
            <nav class="flex space-x-4">
                <a href="#" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">Home</a>
                <a href="#" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">Categories</a>
                <a href="#" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">Contact</a>
                <button id="themeToggle" aria-label="Toggle dark mode" class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    <svg id="themeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-800 dark:text-gray-200" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2a1 1 0 011 1v2a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 2.03a1 1 0 011.42 0l1.42 1.42a1 1 0 01-1.42 1.42L14.64 5.45a1 1 0 010-1.42zM18 9a1 1 0 110 2h-2a1 1 0 110-2h2zM14.64 14.64a1 1 0 010 1.42l-1.42 1.42a1 1 0 11-1.42-1.42l1.42-1.42a1 1 0 011.42 0zM10 16a1 1 0 011 1v2a1 1 0 11-2 0v-2a1 1 0 011-1zm-4.22-1.95a1 1 0 00-1.42 0L2.94 15.47a1 1 0 101.42 1.42l1.42-1.42a1 1 0 000-1.42zM4 9a1 1 0 100 2H2a1 1 0 100-2h2zM5.36 5.36a1 1 0 00-1.42 0L2.52 6.78a1 1 0 101.42 1.42l1.42-1.42a1 1 0 000-1.42z" />
                        <path d="M10 5a5 5 0 100 10A5 5 0 0010 5z" />
                    </svg>
                </button>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Search & Filter Bar -->
        <section class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
            <input type="text" id="searchInput" placeholder="Search products..." class="w-full sm:w-1/3 px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700" aria-label="Search products" />
            <select id="sortSelect" class="px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700" aria-label="Sort products">
                <option value="default">Sort by: Default</option>
                <option value="price-asc">Price – Low to High</option>
                <option value="price-desc">Price – High to Low</option>
                <option value="rating">Rating</option>
            </select>
        </section>

        <!-- Product Grid -->
        <section id="productGrid" class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php foreach ($products as $product): ?>
                <article class="bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1">
                    <a href="#" class="block" aria-labelledby="product-<?= $product['id'] ?>-title">
                        <picture>
                            <source type="image/webp" srcset="<?= $product['image'] ?>.webp" />
                            <img src="<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" class="w-full h-48 object-cover rounded-t-lg" />
                        </picture>
                        <div class="p-4">
                            <h2 id="product-<?= $product['id'] ?>-title" class="text-lg font-semibold mb-2 line-clamp-2">
                                <?= htmlspecialchars($product['name']) ?>
                            </h2>
                            <p class="text-blue-600 dark:text-blue-400 font-bold mb-2">$<?= number_format($product['price'], 2) ?></p>
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.163c.969 0 1.371 1.24.588 1.81l-3.37 2.455a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.455a1 1 0 00-1.175 0l-3.37 2.455c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L2.07 9.384c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 00.95-.69l1.286-3.957z"/></svg>
                                <span><?= $product['rating'] ?> (<?= $product['reviews'] ?> reviews)</span>
                            </div>
                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition" aria-label="Add <?= htmlspecialchars($product['name']) ?> to cart">
                                Add to Cart
                            </button>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-600 dark:text-gray-400">
            © <?= date('Y') ?> Bob's Mall. All rights reserved.
        </div>
    </footer>

    <!-- Optional: Simple client‑side search & sort (no page reload) -->
    <script>
        const products = <?php echo json_encode($products); ?>;
        const grid = document.getElementById('productGrid');
        const searchInput = document.getElementById('searchInput');
        const sortSelect = document.getElementById('sortSelect');
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        function render(productsToRender) {
            grid.innerHTML = '';
            productsToRender.forEach(p => {
                const article = document.createElement('article');
                article.className = 'bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1';
                article.innerHTML = `
                    <a href="#" class="block" aria-labelledby="product-${p.id}-title">
                        <picture>
                            <source type="image/webp" srcset="${p.image}.webp" />
                            <img src="${p.image}" alt="${p.name}" loading="lazy" class="w-full h-48 object-cover rounded-t-lg" />
                        </picture>
                        <div class="p-4">
                            <h2 id="product-${p.id}-title" class="text-lg font-semibold mb-2 line-clamp-2">${p.name}</h2>
                            <p class="text-blue-600 dark:text-blue-400 font-bold mb-2">$${p.price.toFixed(2)}</p>
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.163c.969 0 1.371 1.24.588 1.81l-3.37 2.455a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.455a1 1 0 00-1.175 0l-3.37 2.455c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L2.07 9.384c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 00.95-.69l1.286-3.957z"/></svg>
                                <span>${p.rating} (${p.reviews} reviews)</span>
                            </div>
                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition" aria-label="Add ${p.name} to cart">
                                Add to Cart
                            </button>
                        </div>
                    </a>
                `;
                grid.appendChild(article);
            });
        }

        function filterAndSort() {
            const query = searchInput.value.toLowerCase();
            let filtered = products.filter(p => p.name.toLowerCase().includes(query));
            const sort = sortSelect.value;
            if (sort === 'price-asc') {
                filtered.sort((a, b) => a.price - b.price);
            } else if (sort === 'price-desc') {
                filtered.sort((a, b) => b.price - a.price);
            } else if (sort === 'rating') {
                filtered.sort((a, b) => b.rating - a.rating);
            }
            render(filtered);
        }

        searchInput.addEventListener('input', filterAndSort);
        sortSelect.addEventListener('change', filterAndSort);

        // Theme toggle logic
        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            // Change icon (sun / moon)
            themeIcon.innerHTML = isDark
                ? '<path d="M10 2a1 1 0 011 1v2a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 2.03a1 1 0 011.42 0l1.42 1.42a1 1 0 01-1.42 1.42L14.64 5.45a1 1 0 010-1.42zM18 9a1 1 0 110 2h-2a1 1 0 110-2h2zM14.64 14.64a1 1 0 010 1.42l-1.42 1.42a1 1 0 11-1.42-1.42l1.42-1.42a1 1 0 011.42 0zM10 16a1 1 0 011 1v2a1 1 0 11-2 0v-2a1 1 0 011-1zm-4.22-1.95a1 1 0 00-1.42 0L2.94 15.47a1 1 0 101.42 1.42l1.42-1.42a1 1 0 000-1.42zM4 9a1 1 0 100 2H2a1 1 0 100-2h2zM5.36 5.36a1 1 0 00-1.42 0L2.52 6.78a1 1 0 101.42 1.42l1.42-1.42a1 1 0 000-1.42z" />'
                : '<path d="M10 2a1 1 0 011 1v2a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 2.03a1 1 0 011.42 0l1.42 1.42a1 1 0 01-1.42 1.42L14.64 5.45a1 1 0 010-1.42zM18 9a1 1 0 110 2h-2a1 1 0 110-2h2zM14.64 14.64a1 1 0 010 1.42l-1.42 1.42a1 1 0 11-1.42-1.42l1.42-1.42a1 1 0 011.42 0zM10 16a1 1 0 011 1v2a1 1 0 11-2 0v-2a1 1 0 011-1zm-4.22-1.95a1 1 0 00-1.42 0L2.94 15.47a1 1 0 101.42 1.42l1.42-1.42a1 1 0 000-1.42zM4 9a1 1 0 100 2H2a1 1 0 100-2h2zM5.36 5.36a1 1 0 00-1.42 0L2.52 6.78a1 1 0 101.42 1.42l1.42-1.42a1 1 0 000-1.42z" />';
        });

        // Initial render
        render(products);
    </script>
</body>
</html>
