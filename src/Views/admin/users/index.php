<?php
// Admin users index (redirects to UsersAdminController dashboard) - wrapped in admin layout
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Admin — Users</title>
	<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
	<div class="min-h-screen bg-white dark:bg-gray-900">
		<?php include __DIR__ . '/../parts/sidebar.php'; ?>
		<div id="main-content" class="lg:pl-64">
			<?php include __DIR__ . '/../parts/header.php'; ?>

			<div class="p-6 max-w-6xl mx-auto">
				<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
					<h1 class="text-2xl font-semibold mb-4">Users Admin</h1>
					<p><a href="/admin/users" class="text-blue-600">Back to Users Dashboard</a></p>
				</div>
			</div>

			<?php include __DIR__ . '/../parts/footer.php'; ?>
		</div>
	</div>
</body>
</html>
