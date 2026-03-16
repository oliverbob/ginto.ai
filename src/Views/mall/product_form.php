<?php
/** @var array $categories */
/** @var string $csrf_token */
/** @var array|null $product  — set when editing */
/** @var bool $editing */
$editing    = $editing ?? false;
$product    = $product ?? [];
$pageTitle  = $editing ? 'Edit Product' : 'New Product';
$action     = $editing
    ? '/marketplace/sellers/products/update/' . (int)($product['id'] ?? 0)
    : '/marketplace/sellers/products/create';

$p = $product; // shorthand
$existingImgs = [];
if (!empty($p['images'])) $existingImgs = json_decode($p['images'], true) ?: [];
if (empty($existingImgs) && !empty($p['image_path'])) $existingImgs = [$p['image_path']];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<style>
.pf-shell {
    max-width: 820px;
    margin: 32px auto 80px;
    padding: 0 16px;
}
.pf-back {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.84rem; color: var(--muted);
    margin-bottom: 20px;
    transition: color var(--trans);
}
.pf-back:hover { color: var(--text); }
.pf-header { margin-bottom: 28px; }
.pf-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 3px; }
.pf-sub   { font-size: 0.845rem; color: var(--muted); }

.pf-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 16px;
}
.pf-section-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.pf-section-icon {
    width: 30px; height: 30px; border-radius: 8px;
    background: rgba(59,130,246,0.1); color: var(--accent);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.pf-section-title { font-size: 0.92rem; font-weight: 700; }
.pf-section-body  { padding: 20px; display: flex; flex-direction: column; gap: 16px; }

.pf-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.pf-group  { display: flex; flex-direction: column; gap: 5px; }
.pf-label  { font-size: 0.82rem; font-weight: 600; }
.pf-label .req { color: var(--danger); }
.pf-input {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 9px 12px; width: 100%;
    color: var(--text); font-size: 0.88rem; font-family: inherit;
    outline: none;
    transition: border-color var(--trans), box-shadow var(--trans);
    box-sizing: border-box;
}
.pf-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.pf-input::placeholder { color: var(--muted); }
textarea.pf-input  { resize: vertical; }
select.pf-input    { cursor: pointer; }
.pf-hint { font-size: 0.76rem; color: var(--muted); }

/* Image upload */
.img-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 28px 20px; text-align: center;
    cursor: pointer; position: relative;
    background: var(--surface2);
    transition: border-color var(--trans), background var(--trans);
}
.img-upload-area:hover,
.img-upload-area.drag-over { border-color: var(--accent); background: rgba(59,130,246,0.04); }
.img-upload-area input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.img-upload-icon  { font-size: 2rem; margin-bottom: 8px; }
.img-upload-title { font-weight: 600; font-size: 0.88rem; margin-bottom: 3px; }
.img-upload-sub   { font-size: 0.76rem; color: var(--muted); }

.img-previews { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.img-prev-item {
    width: 80px; display: flex; flex-direction: column; align-items: center; gap: 4px;
}
.img-prev-item img {
    width: 80px; height: 80px; object-fit: cover;
    border-radius: 8px; border: 1px solid var(--border);
}
.img-prev-name { font-size: 0.63rem; color: var(--muted); text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80px; }

/* Existing images */
.existing-imgs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.existing-img-wrap { position: relative; width: 80px; }
.existing-img-wrap img {
    width: 80px; height: 80px; object-fit: cover;
    border-radius: 8px; border: 1px solid var(--border); display: block;
}

/* Footer actions */
.pf-actions { display: flex; gap: 10px; align-items: center; margin-top: 8px; }

@media (max-width: 580px) {
    .pf-grid-2 { grid-template-columns: 1fr; }
}
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="pf-shell">

    <a href="/marketplace/sellers/products" class="pf-back">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Products
    </a>

    <div class="pf-header">
        <h1 class="pf-title"><?= $pageTitle ?></h1>
        <p class="pf-sub"><?= $editing ? 'Update the details for this listing' : 'Fill in the details to create a new listing' ?></p>
    </div>

    <form method="POST" action="<?= htmlspecialchars($action) ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- Basic Info -->
        <div class="pf-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <h2 class="pf-section-title">Basic Information</h2>
            </div>
            <div class="pf-section-body">
                <div class="pf-group">
                    <label class="pf-label" for="pf-title">Title <span class="req" aria-hidden="true">*</span></label>
                    <input class="pf-input" id="pf-title" type="text" name="title" required
                        placeholder="What are you selling?"
                        value="<?= htmlspecialchars($p['title'] ?? '') ?>">
                </div>
                <div class="pf-group">
                    <label class="pf-label" for="pf-slug">URL Slug <span style="color:var(--muted);font-weight:400">(optional)</span></label>
                    <input class="pf-input" id="pf-slug" type="text" name="slug"
                        placeholder="auto-generated-from-title"
                        value="<?= htmlspecialchars($p['slug'] ?? '') ?>">
                    <span class="pf-hint">Leave blank to auto-generate from the title.</span>
                </div>
                <div class="pf-group">
                    <label class="pf-label" for="pf-short">Short Description</label>
                    <textarea class="pf-input" id="pf-short" name="short_description" rows="2"
                        placeholder="One-liner summary shown on listing cards…"><?= htmlspecialchars($p['short_description'] ?? '') ?></textarea>
                </div>
                <div class="pf-group">
                    <label class="pf-label" for="pf-desc">Full Description</label>
                    <textarea class="pf-input" id="pf-desc" name="description" rows="6"
                        placeholder="Detailed product description, features, specifications…"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Pricing & Category -->
        <div class="pf-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
                <h2 class="pf-section-title">Pricing &amp; Details</h2>
            </div>
            <div class="pf-section-body">
                <div class="pf-grid-2">
                    <div class="pf-group">
                        <label class="pf-label" for="pf-price">Price <span class="req" aria-hidden="true">*</span></label>
                        <input class="pf-input" id="pf-price" type="number" step="0.01" min="0" name="price" required
                            placeholder="0.00"
                            value="<?= htmlspecialchars($p['price'] ?? '') ?>">
                    </div>
                    <div class="pf-group">
                        <label class="pf-label" for="pf-currency">Currency</label>
                        <select class="pf-input" id="pf-currency" name="currency">
                            <?php foreach (['USD','PHP','NGN','EUR','GBP','SGD','MYR','AUD'] as $cur): ?>
                            <option value="<?= $cur ?>" <?= ($p['currency'] ?? 'USD') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="pf-grid-2">
                    <div class="pf-group">
                        <label class="pf-label" for="pf-qty">Stock Quantity</label>
                        <input class="pf-input" id="pf-qty" type="number" min="0" name="quantity"
                            placeholder="0"
                            value="<?= htmlspecialchars((string)($p['quantity'] ?? $p['stock'] ?? 0)) ?>">
                    </div>
                    <div class="pf-group">
                        <label class="pf-label" for="pf-cat">Category</label>
                        <select class="pf-input" id="pf-cat" name="category_id">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars((string)$c['id']) ?>"
                                <?= (int)($p['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="pf-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <h2 class="pf-section-title">Product Images</h2>
            </div>
            <div class="pf-section-body">
                <?php if (!empty($existingImgs)): ?>
                <div>
                    <div class="pf-hint" style="margin-bottom:8px">Current images (uploading new ones will be added alongside these):</div>
                    <div class="existing-imgs">
                        <?php foreach ($existingImgs as $imgUrl): ?>
                        <div class="existing-img-wrap">
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Product image" loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="img-upload-area" id="imgUploadArea">
                    <input type="file" name="images[]" id="imgFilesInput" multiple accept="image/*" tabindex="-1">
                    <div class="img-upload-icon">🖼️</div>
                    <div class="img-upload-title">Click or drag &amp; drop images</div>
                    <div class="img-upload-sub">JPG, PNG, WebP — up to 10 MB each. First image is the cover.</div>
                </div>
                <div class="img-previews" id="imgPreviews"></div>
            </div>
        </div>

        <div class="pf-actions">
            <button type="submit" class="btn btn-primary" style="padding:11px 28px;font-size:0.92rem">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $editing ? 'Save Changes' : 'Create Product' ?>
            </button>
            <a href="/marketplace/sellers/products" class="btn btn-secondary">Cancel</a>
        </div>

    </form>
</div>

<script>
(function () {
    const area     = document.getElementById('imgUploadArea');
    const input    = document.getElementById('imgFilesInput');
    const previews = document.getElementById('imgPreviews');

    function renderPreviews() {
        if (!previews || !input || !input.files.length) return;
        previews.innerHTML = '';
        Array.from(input.files).forEach(function (file) {
            const wrap = document.createElement('div');
            wrap.className = 'img-prev-item';
            const name = document.createElement('div');
            name.className = 'img-prev-name';
            name.textContent = file.name;
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = function (e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
                wrap.appendChild(img);
            } else {
                wrap.innerHTML = '<div style="width:80px;height:80px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:1.8rem">🖼️</div>';
            }
            wrap.appendChild(name);
            previews.appendChild(wrap);
        });
    }

    if (input) input.addEventListener('change', renderPreviews);
    if (area) {
        area.addEventListener('dragover',  function (e) { e.preventDefault(); area.classList.add('drag-over'); });
        area.addEventListener('dragleave', function ()  { area.classList.remove('drag-over'); });
        area.addEventListener('drop', function (e) {
            e.preventDefault(); area.classList.remove('drag-over');
            if (input && e.dataTransfer.files.length) {
                try { input.files = e.dataTransfer.files; } catch (_) {}
                renderPreviews();
            }
        });
    }

    // Auto-generate slug from title
    const titleInput = document.getElementById('pf-title');
    const slugInput  = document.getElementById('pf-slug');
    if (titleInput && slugInput && !slugInput.value) {
        titleInput.addEventListener('input', function () {
            if (slugInput.dataset.manual) return;
            slugInput.value = titleInput.value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .trim().replace(/\s+/g, '-');
        });
        slugInput.addEventListener('input', function () { slugInput.dataset.manual = '1'; });
    }
}());
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>