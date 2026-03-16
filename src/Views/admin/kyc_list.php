<?php
/** @var array $kycs */
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
?>
<!doctype html>
<html lang="en"<?= $htmlDark ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KYC Submissions — Admin</title>
    <?php include __DIR__ . '/parts/favicons.php'; ?>
    <script>(function(){try{var s=null;try{s=localStorage.getItem('theme');}catch(e){}if(!s){var m=document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);s=m?m[1]:null;}if(s==='dark')document.documentElement.classList.add('dark');else if(s==='light')document.documentElement.classList.remove('dark');}catch(_){}})();</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <style>
        #sidebar nav { max-height: calc(100vh - 120px); overflow-y: auto; }

        /* Status badges */
        .status-badge {
            display: inline-flex; align-items: center;
            padding: 4px 14px; border-radius: 9999px;
            font-size: 0.8rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            white-space: nowrap; flex-shrink: 0;
        }
        .badge-pending  { background:#fef9c3; color:#78350f; }
        .badge-review   { background:#dbeafe; color:#1e3a8a; }
        .badge-approved { background:#dcfce7; color:#14532d; }
        .badge-rejected { background:#fee2e2; color:#7f1d1d; }
        html.dark .badge-pending  { background:#78350f; color:#fef08a; }
        html.dark .badge-review   { background:#1e3a5f; color:#93c5fd; }
        html.dark .badge-approved { background:#14532d; color:#86efac; }
        html.dark .badge-rejected { background:#7f1d1d; color:#fca5a5; }

        /* Document thumbnails */
        .doc-thumb {
            width: 110px; height: 110px; object-fit: cover;
            border-radius: 8px; border: 2px solid #e5e7eb;
            cursor: pointer; transition: transform .15s, box-shadow .15s;
        }
        .doc-thumb:hover { transform: scale(1.05); box-shadow: 0 4px 18px rgba(0,0,0,.18); }
        html.dark .doc-thumb { border-color: #374151; }

        /* Lightbox */
        #kyc-lightbox { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.88); align-items:center; justify-content:center; }
        #kyc-lightbox.open { display:flex; }
        #kyc-lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 10px; box-shadow: 0 12px 60px rgba(0,0,0,.7); }
        #kyc-lightbox-close { position:absolute; top:20px; right:28px; color:#fff; font-size:2.4rem; cursor:pointer; line-height:1; opacity:.85; transition:opacity .1s; }
        #kyc-lightbox-close:hover { opacity:1; }

        /* Card accordion */
        .kyc-card { border-radius: 12px; overflow: hidden; transition: box-shadow .2s; }
        .kyc-card:hover { box-shadow: 0 4px 24px rgba(0,0,0,.09); }
        html.dark .kyc-card:hover { box-shadow: 0 4px 24px rgba(0,0,0,.35); }
        .kyc-card-header { cursor: pointer; user-select: none; }
        .kyc-card-header:hover { background: #f9fafb; }
        html.dark .kyc-card-header:hover { background: rgba(255,255,255,.04); }

        /* Info grid labels */
        .info-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; margin-bottom: 4px; }
        .info-value { font-size: 1rem; font-weight: 500; color: #111827; }
        html.dark .info-value { color: #f3f4f6; }

        /* Action buttons */
        .kyc-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 22px; border-radius: 8px;
            font-size: 0.95rem; font-weight: 700;
            border: none; cursor: pointer; transition: filter .15s, transform .1s;
        }
        .kyc-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
        .kyc-btn:active { transform: translateY(0); filter: brightness(.97); }
        .kyc-btn-approve  { background: #16a34a; color: #fff; }
        .kyc-btn-review   { background: #2563eb; color: #fff; }
        .kyc-btn-reject   { background: #dc2626; color: #fff; }

        /* Filter tabs */
        .filter-tab { padding: 7px 18px; border-radius: 9999px; font-size: 0.9rem; font-weight: 600; border: 1.5px solid; cursor: pointer; transition: all .15s; }
        .filter-tab-active { background: #1f2937; color: #fff; border-color: transparent; }
        html.dark .filter-tab-active { background: #f9fafb; color: #111827; }
        .filter-tab-inactive { background: #fff; color: #4b5563; border-color: #d1d5db; }
        html.dark .filter-tab-inactive { background: #1f2937; color: #d1d5db; border-color: #374151; }
        .filter-tab-inactive:hover { border-color: #6b7280; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <div id="main-content" class="lg:pl-64">
        <?php include __DIR__ . '/parts/header.php'; ?>

        <!-- Page title banner -->
        <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 md:px-8 py-5">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#f97316">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="13" y2="13"/><circle cx="17" cy="16" r="3"/></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">KYC Submissions</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Review seller identity verification requests</p>
                    </div>
                </div>
                <span class="text-base font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-4 py-1.5 rounded-full whitespace-nowrap">
                    <?= count($kycs) ?> submission<?= count($kycs) !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="p-5 md:p-7 max-w-7xl mx-auto">

            <!-- Status filter tabs -->
            <div class="flex gap-2 mb-6 flex-wrap" id="kycFilterTabs">
                <?php
                $counts = array_count_values(array_column($kycs, 'status'));
                foreach (['all' => 'All', 'pending' => 'Pending', 'review' => 'More Docs', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $f => $label):
                    $cnt = $f === 'all' ? count($kycs) : ($counts[$f] ?? 0);
                ?>
                <button onclick="filterKyc('<?= $f ?>')" id="tab-<?= $f ?>"
                    class="filter-tab <?= $f === 'all' ? 'filter-tab-active' : 'filter-tab-inactive' ?>">
                    <?= $label ?><?= $cnt ? " <span style='opacity:.6'>($cnt)</span>" : '' ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Submissions -->
            <?php if (empty($kycs)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-16 text-center text-gray-400 dark:text-gray-500">
                <svg class="w-14 h-14 mx-auto mb-4 opacity-30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="13" y2="13"/></svg>
                <p class="text-lg font-semibold">No KYC submissions yet.</p>
            </div>
            <?php else: ?>
            <div id="kycGrid" class="space-y-4">
            <?php foreach ($kycs as $k):
                $user    = $k['_user'] ?? null;
                $status  = $k['status'] ?? 'pending';
                $docs    = [];
                if (!empty($k['documents'])) {
                    $decoded = json_decode($k['documents'], true);
                    if (is_array($decoded)) $docs = $decoded;
                }
                $badgeClass  = ['pending'=>'badge-pending','review'=>'badge-review','approved'=>'badge-approved','rejected'=>'badge-rejected'][$status] ?? 'badge-pending';
                $name        = htmlspecialchars($user['fullname'] ?? $user['email'] ?? 'Unknown');
                $email       = htmlspecialchars($user['email'] ?? '');
                $submittedAt = $k['submitted_at'] ? date('M j, Y · g:i a', strtotime($k['submitted_at'])) : '—';
                $reviewedAt  = $k['reviewed_at']  ? date('M j, Y · g:i a', strtotime($k['reviewed_at']))  : null;
            ?>
            <div class="kyc-card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700" data-status="<?= htmlspecialchars($status) ?>">

                <!-- Card header (clickable toggle) -->
                <div class="kyc-card-header flex items-center gap-4 px-6 py-5"
                     onclick="this.closest('.kyc-card').querySelector('.kyc-body').classList.toggle('hidden')">
                    <div class="w-12 h-12 rounded-full flex-shrink-0 flex items-center justify-center text-white text-xl font-bold" style="background:#f97316">
                        <?= strtoupper(substr($user['fullname'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-lg font-bold text-gray-900 dark:text-white truncate"><?= $name ?></div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"><?= $email ?></div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Submitted <?= $submittedAt ?></div>
                    </div>
                    <span class="status-badge <?= $badgeClass ?>"><?= ucfirst($status === 'review' ? 'More Docs' : $status) ?></span>
                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                <!-- Expandable body -->
                <div class="kyc-body hidden border-t border-gray-100 dark:border-gray-700">
                    <div class="px-6 py-6 space-y-6">

                        <!-- Personal info -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5">
                            <?php
                            $fields = [
                                'First Name'     => $k['first_name']     ?? null,
                                'Middle Name'    => $k['middle_name']    ?? null,
                                'Last Name'      => $k['last_name']      ?? null,
                                'Date of Birth'  => $k['dob']            ?? null,
                                'Place of Birth' => $k['place_of_birth'] ?? null,
                                'Nationality'    => $k['nationality']    ?? null,
                                'Country'        => $k['country']        ?? null,
                                'Mobile No.'     => $k['phone']          ?? null,
                                'TIN'            => $k['tin']            ?? null,
                                'ID Type'        => $k['id_type']        ?? null,
                                'ID Number'      => $k['identifier']     ?? null,
                            ];
                            foreach ($fields as $label => $val): ?>
                            <div>
                                <div class="info-label"><?= $label ?></div>
                                <div class="info-value"><?= $val ? htmlspecialchars($val) : '<span style="color:#9ca3af">—</span>' ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php
                            $addrParts = array_filter([
                                $k['address_street']   ?? null,
                                $k['address_city']     ?? null,
                                $k['address_province'] ?? null,
                                $k['address_zip']      ?? null,
                            ]);
                            if ($addrParts): ?>
                            <div class="col-span-2 sm:col-span-3">
                                <div class="info-label">Home Address</div>
                                <div class="info-value"><?= htmlspecialchars(implode(', ', $addrParts)) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($k['business_name']) || !empty($k['business_reg'])): ?>
                            <div class="col-span-2">
                                <div class="info-label">Business Registration</div>
                                <div class="info-value"><?= htmlspecialchars(trim(($k['business_name'] ?? '') . ($k['business_reg'] ? ' · Reg: ' . $k['business_reg'] : ''))) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($reviewedAt): ?>
                            <div class="sm:col-span-2">
                                <div class="info-label">Reviewed At</div>
                                <div class="info-value"><?= $reviewedAt ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Documents -->
                        <?php if (!empty($docs)): ?>
                        <div>
                            <div class="info-label mb-3">Documents (<?= count($docs) ?>)</div>
                            <div class="flex flex-wrap gap-3">
                            <?php foreach ($docs as $doc):
                                $docPath = htmlspecialchars($doc);
                                $ext = strtolower(pathinfo($doc, PATHINFO_EXTENSION));
                                $isPdf = $ext === 'pdf';
                            ?>
                                <?php if ($isPdf): ?>
                                <a href="<?= $docPath ?>" target="_blank"
                                   class="flex flex-col items-center justify-center gap-1 w-28 h-28 bg-gray-100 dark:bg-gray-700 rounded-xl border-2 border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-gray-600 transition-colors">
                                    <svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="text-xs font-semibold">Open PDF</span>
                                </a>
                                <?php else: ?>
                                <img src="<?= $docPath ?>" alt="KYC document" class="doc-thumb" loading="lazy"
                                     onclick="openLightbox(this.src)" title="Click to enlarge">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-base text-gray-400 dark:text-gray-500 italic">No documents uploaded.</div>
                        <?php endif; ?>

                        <!-- Review notes display -->
                        <?php if (!empty($k['review_notes'])): ?>
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4 text-base text-amber-800 dark:text-amber-300">
                            <span class="font-semibold">Review Note:</span> <?= nl2br(htmlspecialchars($k['review_notes'])) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Action form -->
                        <form method="POST" action="/admin/kyc/review/<?= intval($k['id']) ?>" class="space-y-3 pt-1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <div>
                                <label class="block info-label mb-2">Add Review Note (optional)</label>
                                <textarea name="review_notes" rows="2"
                                    placeholder="Explain why you need more docs, or a rejection reason…"
                                    class="w-full rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-base px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"><?= htmlspecialchars($k['review_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="flex flex-wrap gap-3 pt-1">
                                <?php if ($status !== 'approved'): ?>
                                <button type="submit" name="status" value="approved" class="kyc-btn kyc-btn-approve">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    Approve
                                </button>
                                <?php endif; ?>
                                <?php if ($status !== 'review'): ?>
                                <button type="submit" name="status" value="review" class="kyc-btn kyc-btn-review">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                    Require More Docs
                                </button>
                                <?php endif; ?>
                                <?php if ($status !== 'rejected'): ?>
                                <button type="submit" name="status" value="rejected" class="kyc-btn kyc-btn-reject">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    Reject
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

        <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</div>

<!-- Lightbox -->
<div id="kyc-lightbox" onclick="closeLightbox()">
    <span id="kyc-lightbox-close" onclick="closeLightbox()">×</span>
    <img id="kyc-lightbox-img" src="" alt="Document preview">
</div>

<script>
function openLightbox(src) {
    document.getElementById('kyc-lightbox-img').src = src;
    document.getElementById('kyc-lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('kyc-lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });

function filterKyc(status) {
    document.querySelectorAll('#kycFilterTabs button').forEach(function(btn) {
        btn.classList.remove('filter-tab-active');
        btn.classList.add('filter-tab-inactive');
    });
    var active = document.getElementById('tab-' + status);
    if (active) {
        active.classList.remove('filter-tab-inactive');
        active.classList.add('filter-tab-active');
    }
    document.querySelectorAll('.kyc-card').forEach(function(row) {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>

<!doctype html>
<html lang="en"<?= $htmlDark ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KYC Submissions — Admin</title>
    <?php include __DIR__ . '/parts/favicons.php'; ?>
    <script>(function(){try{var s=null;try{s=localStorage.getItem('theme');}catch(e){}if(!s){var m=document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);s=m?m[1]:null;}if(s==='dark')document.documentElement.classList.add('dark');else if(s==='light')document.documentElement.classList.remove('dark');}catch(_){}})();</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <style>
        #sidebar nav { max-height: calc(100vh - 120px); overflow-y: auto; }
        .status-badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:9999px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .badge-pending  { background:#fef9c3; color:#92400e; }
        .badge-review   { background:#dbeafe; color:#1e40af; }
        .badge-approved { background:#dcfce7; color:#166534; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        html.dark .badge-pending  { background:#78350f; color:#fef08a; }
        html.dark .badge-review   { background:#1e3a5f; color:#93c5fd; }
        html.dark .badge-approved { background:#14532d; color:#86efac; }
        html.dark .badge-rejected { background:#7f1d1d; color:#fca5a5; }
        .doc-thumb { width:80px; height:80px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb; cursor:pointer; transition:transform .15s; }
        .doc-thumb:hover { transform:scale(1.08); }
        html.dark .doc-thumb { border-color:#374151; }
        /* lightbox */
        #kyc-lightbox { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.85); align-items:center; justify-content:center; }
        #kyc-lightbox.open { display:flex; }
        #kyc-lightbox img { max-width:90vw; max-height:86vh; border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,.6); }
        #kyc-lightbox-close { position:absolute; top:18px; right:24px; color:#fff; font-size:2rem; cursor:pointer; line-height:1; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900">
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <div id="main-content" class="lg:pl-64">
        <?php include __DIR__ . '/parts/header.php'; ?>

        <div class="p-6 max-w-7xl mx-auto">

            <!-- Page header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">KYC Submissions</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Review identity verification submissions from sellers.</p>
                </div>
                <span class="text-sm text-gray-400 dark:text-gray-500"><?= count($kycs) ?> submission<?= count($kycs) !== 1 ? 's' : '' ?></span>
            </div>

            <!-- Status filter tabs -->
            <div class="flex gap-2 mb-5 flex-wrap" id="kycFilterTabs">
                <?php foreach (['all','pending','review','approved','rejected'] as $f): ?>
                <button onclick="filterKyc('<?= $f ?>')" id="tab-<?= $f ?>"
                    class="px-4 py-1.5 rounded-full text-sm font-semibold border transition-all
                           <?= $f === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 border-transparent' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-gray-400' ?>">
                    <?= ucfirst($f) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Submissions grid -->
            <?php if (empty($kycs)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-12 text-center text-gray-400 dark:text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="13" y2="13"/></svg>
                <p class="font-medium">No KYC submissions yet.</p>
            </div>
            <?php else: ?>
            <div id="kycGrid" class="space-y-4">
            <?php foreach ($kycs as $k):
                $user    = $k['_user'] ?? null;
                $status  = $k['status'] ?? 'pending';
                $docs    = [];
                if (!empty($k['documents'])) {
                    $decoded = json_decode($k['documents'], true);
                    if (is_array($decoded)) $docs = $decoded;
                }
                $badgeClass = ['pending'=>'badge-pending','review'=>'badge-review','approved'=>'badge-approved','rejected'=>'badge-rejected'][$status] ?? 'badge-pending';
                $name    = htmlspecialchars($user['fullname'] ?? $user['email'] ?? 'Unknown');
                $email   = htmlspecialchars($user['email'] ?? '');
                $submittedAt = $k['submitted_at'] ? date('M j, Y g:ia', strtotime($k['submitted_at'])) : '—';
                $reviewedAt  = $k['reviewed_at']  ? date('M j, Y g:ia', strtotime($k['reviewed_at']))  : null;
            ?>
            <div class="kyc-row bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 overflow-hidden" data-status="<?= htmlspecialchars($status) ?>">
                <!-- Row header -->
                <div class="flex flex-wrap items-center gap-4 p-5 cursor-pointer select-none" onclick="this.closest('.kyc-row').querySelector('.kyc-body').classList.toggle('hidden')">
                    <div class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                        <?= strtoupper(substr($user['fullname'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-900 dark:text-white"><?= $name ?></div>
                        <div class="text-xs text-gray-400 dark:text-gray-500"><?= $email ?> &bull; Submitted <?= $submittedAt ?></div>
                    </div>
                    <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                <!-- Expandable body -->
                <div class="kyc-body hidden border-t border-gray-100 dark:border-gray-700 p-5 space-y-5">

                    <!-- Personal info -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div><div class="text-xs text-gray-400 mb-1">First Name</div><div class="font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($k['first_name'] ?? '—') ?></div></div>
                        <div><div class="text-xs text-gray-400 mb-1">Last Name</div><div class="font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($k['last_name'] ?? '—') ?></div></div>
                        <div><div class="text-xs text-gray-400 mb-1">Date of Birth</div><div class="font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($k['dob'] ?? '—') ?></div></div>
                        <div><div class="text-xs text-gray-400 mb-1">Country</div><div class="font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars(strtoupper($k['country'] ?? '—')) ?></div></div>
                        <div class="sm:col-span-2"><div class="text-xs text-gray-400 mb-1">ID / Identifier</div><div class="font-medium text-gray-800 dark:text-gray-200 break-all"><?= htmlspecialchars($k['identifier'] ?? '—') ?></div></div>
                        <?php if ($reviewedAt): ?>
                        <div class="sm:col-span-2"><div class="text-xs text-gray-400 mb-1">Reviewed At</div><div class="font-medium text-gray-800 dark:text-gray-200"><?= $reviewedAt ?></div></div>
                        <?php endif; ?>
                    </div>

                    <!-- Documents -->
                    <?php if (!empty($docs)): ?>
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Documents (<?= count($docs) ?>)</div>
                        <div class="flex flex-wrap gap-3">
                        <?php foreach ($docs as $doc):
                            $docPath = htmlspecialchars($doc);
                            $ext = strtolower(pathinfo($doc, PATHINFO_EXTENSION));
                            $isPdf = $ext === 'pdf';
                        ?>
                            <?php if ($isPdf): ?>
                            <a href="<?= $docPath ?>" target="_blank" class="flex flex-col items-center justify-center w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-center" title="View PDF">
                                <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span style="font-size:0.62rem">PDF</span>
                            </a>
                            <?php else: ?>
                            <img src="<?= $docPath ?>" alt="KYC document" class="doc-thumb" loading="lazy"
                                 onclick="openLightbox(this.src)" title="Click to enlarge">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No documents uploaded.</p>
                    <?php endif; ?>

                    <!-- Review notes -->
                    <?php if (!empty($k['review_notes'])): ?>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3 text-sm text-yellow-800 dark:text-yellow-300">
                        <strong>Review Note:</strong> <?= nl2br(htmlspecialchars($k['review_notes'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Action form -->
                    <form method="POST" action="/admin/kyc/review/<?= intval($k['id']) ?>" class="space-y-3 pt-1">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Review Notes (optional)</label>
                            <textarea name="review_notes" rows="2" placeholder="Add a note for the applicant…"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"><?= htmlspecialchars($k['review_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="status" value="approved"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                ✓ Approve
                            </button>
                            <button type="submit" name="status" value="review"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                ↻ Require More Docs
                            </button>
                            <button type="submit" name="status" value="rejected"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                ✕ Reject
                            </button>
                        </div>
                    </form>

                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

        <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</div>

<!-- Lightbox -->
<div id="kyc-lightbox" onclick="closeLightbox()">
    <span id="kyc-lightbox-close" onclick="closeLightbox()">×</span>
    <img id="kyc-lightbox-img" src="" alt="Document preview">
</div>

<script>
function openLightbox(src) {
    document.getElementById('kyc-lightbox-img').src = src;
    document.getElementById('kyc-lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('kyc-lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });

function filterKyc(status) {
    document.querySelectorAll('#kycFilterTabs button').forEach(function(btn) {
        btn.className = btn.className.replace('bg-gray-900 text-white dark:bg-white dark:text-gray-900 border-transparent', '');
        btn.className += ' bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700';
    });
    var active = document.getElementById('tab-' + status);
    if (active) {
        active.className = active.className.replace('bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700', '');
        active.className += ' bg-gray-900 text-white dark:bg-white dark:text-gray-900 border-transparent';
    }
    document.querySelectorAll('.kyc-row').forEach(function(row) {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>