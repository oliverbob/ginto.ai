<?php
// Admin show page — use admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<?php include __DIR__ . '/../parts/favicons.php'; ?>
	<title><?= htmlspecialchars($page['title'] ?? 'Page') ?> — Admin</title>
	<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
	<div class="min-h-screen bg-white dark:bg-gray-900">
		<?php include __DIR__ . '/../parts/sidebar.php'; ?>
		<div id="main-content" class="lg:pl-64">
			<?php include __DIR__ . '/../parts/header.php'; ?>

			<div class="p-6 max-w-6xl mx-auto">
				<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
					<h1 class="text-2xl font-semibold mb-2"><?= htmlspecialchars($page['title'] ?? 'Page') ?></h1>
					<p class="text-xs text-gray-500 mb-4">Status: <?= htmlspecialchars($page['status'] ?? '') ?></p>
					<div class="prose max-w-none dark:prose-invert"><?= $page['content'] ?? '' ?></div>
					<p class="mt-6"><a href="/admin/pages/<?= htmlspecialchars($page['id'] ?? '') ?>/edit" class="text-sm text-blue-600">Edit</a></p>
				</div>
			</div>

			<?php include __DIR__ . '/../parts/footer.php'; ?>
		</div>
	</div>
</body>
</html>