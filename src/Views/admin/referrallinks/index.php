<?php
// Admin Referral Links View
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
$users = $users ?? [];
$search = $search ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$totalCount = $totalCount ?? 0;
$csrf_token = $csrf_token ?? '';
$baseUrl = rtrim(getenv('BASE_URL') ?: (defined('BASE_URL') ? BASE_URL : 'https://ginto.ai'), '/');
?>
<!DOCTYPE html>
<html lang="en"<?php echo $htmlDark; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>Referral Links - Ginto Admin</title>
    <script>
        (function () {
            try {
                var saved = null;
                try { saved = localStorage.getItem('theme'); } catch (e) { saved = null; }
                if (!saved) {
                    var m = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
                    saved = m ? m[1] : null;
                }
                if (saved === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (err) {}
        })();
    </script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <style>
        #sidebar nav { max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; }
        #sidebar nav::-webkit-scrollbar { width: 8px; }
        #sidebar nav::-webkit-scrollbar-track { background: transparent; }
        #sidebar nav::-webkit-scrollbar-thumb { background-color: rgba(156,163,175,0.5); border-radius: 9999px; }
        .copy-btn:hover { transform: scale(1.05); }
        .copy-btn.copied { background-color: #22c55e !important; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen flex flex-col">
        <?php include __DIR__ . '/../parts/header.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-link text-blue-500"></i>
                        Referral Links
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Search users and copy their referral links</p>
                </div>
                
                <!-- Search Box -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                    <div class="flex gap-4">
                        <div class="flex-1 relative">
                            <input type="text" id="searchInput" value="<?= htmlspecialchars($search) ?>" 
                                placeholder="Start typing to search by name, username, email, or public ID..." 
                                class="w-full px-4 py-2 pl-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                autocomplete="off">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" id="searchIcon"></i>
                            <i class="fas fa-spinner fa-spin absolute left-3 top-1/2 transform -translate-y-1/2 text-blue-500 hidden" id="loadingIcon"></i>
                        </div>
                        <button type="button" id="clearBtn" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-medium rounded-lg transition flex items-center gap-2 <?= $search ? '' : 'hidden' ?>">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                
                <!-- Results -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700" id="resultCount">
                        <span class="text-gray-600 dark:text-gray-400">
                            Showing <strong class="text-gray-900 dark:text-white"><?= $totalCount ?></strong> users
                        </span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Referral Link</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Copy</th>
                                </tr>
                            </thead>
                            <tbody id="resultsBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-search text-4xl mb-4 text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-lg">No users found<?= $search ? ' matching your search' : '' ?></p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <?php 
                                    $referralLink = $baseUrl . '/register?ref=' . htmlspecialchars($user['public_id'] ?? '');
                                    $statusColor = ($user['status'] ?? '') === 'active' ? 'green' : 'gray';
                                ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($user['fullname'] ?? 'N/A') ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">@<?= htmlspecialchars($user['username'] ?? '') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-<?= $statusColor ?>-100 dark:bg-<?= $statusColor ?>-900 text-<?= $statusColor ?>-800 dark:text-<?= $statusColor ?>-200">
                                            <?= htmlspecialchars(ucfirst($user['status'] ?? 'unknown')) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded break-all"><?= $referralLink ?></code>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="copyLink('<?= $referralLink ?>', this)" 
                                            class="copy-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded transition">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-center gap-2">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="/admin/referrallinks?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-3 py-1 rounded <?= $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    const baseUrl = '<?= $baseUrl ?>';
    let searchTimeout = null;
    
    function copyLink(link, btn) {
        navigator.clipboard.writeText(link).then(() => {
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '<i class="fas fa-copy"></i>';
            }, 2000);
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function renderUsers(users, search) {
        const tbody = document.getElementById('resultsBody');
        const countDiv = document.getElementById('resultCount');
        
        if (!users || users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        <i class="fas fa-search text-4xl mb-4 text-gray-300 dark:text-gray-600"></i>
                        <p class="text-lg">No users found${search ? ' matching your search' : ''}</p>
                    </td>
                </tr>`;
            countDiv.innerHTML = `<span class="text-gray-600 dark:text-gray-400">No results</span>`;
            return;
        }
        
        countDiv.innerHTML = search 
            ? `<span class="text-gray-600 dark:text-gray-400">Found <strong class="text-gray-900 dark:text-white">${users.length}</strong> user(s) matching "<strong class="text-blue-500">${escapeHtml(search)}</strong>"</span>`
            : `<span class="text-gray-600 dark:text-gray-400">Showing <strong class="text-gray-900 dark:text-white">${users.length}</strong> users</span>`;
        
        tbody.innerHTML = users.map(user => {
            const referralLink = baseUrl + '/register?ref=' + encodeURIComponent(user.public_id || '');
            const statusColor = user.status === 'active' ? 'green' : 'gray';
            return `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">@${escapeHtml(user.username || '')}</div>
                        <div class="text-xs text-gray-400">${escapeHtml(user.email || '')}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded bg-${statusColor}-100 dark:bg-${statusColor}-900 text-${statusColor}-800 dark:text-${statusColor}-200">
                            ${escapeHtml((user.status || 'unknown').charAt(0).toUpperCase() + (user.status || 'unknown').slice(1))}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded break-all">${escapeHtml(referralLink)}</code>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button onclick="copyLink('${escapeHtml(referralLink)}', this)" 
                            class="copy-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded transition">
                            <i class="fas fa-copy"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    }
    
    function doSearch(query) {
        const searchIcon = document.getElementById('searchIcon');
        const loadingIcon = document.getElementById('loadingIcon');
        const clearBtn = document.getElementById('clearBtn');
        
        searchIcon.classList.add('hidden');
        loadingIcon.classList.remove('hidden');
        
        fetch('/admin/referrallinks/search?q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                searchIcon.classList.remove('hidden');
                loadingIcon.classList.add('hidden');
                
                if (data.success) {
                    renderUsers(data.users, query);
                    clearBtn.classList.toggle('hidden', !query);
                }
            })
            .catch(err => {
                searchIcon.classList.remove('hidden');
                loadingIcon.classList.add('hidden');
                console.error('Search error:', err);
            });
    }
    
    document.getElementById('searchInput').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        searchTimeout = setTimeout(() => doSearch(query), 300);
    });
    
    document.getElementById('clearBtn').addEventListener('click', function() {
        document.getElementById('searchInput').value = '';
        doSearch('');
        this.classList.add('hidden');
    });
    </script>
</body>
</html>
