<?php
/** @var array $kycs */
?>
<div class="max-w-5xl mx-auto">
    <h2 class="text-xl font-semibold mb-4">KYC Profiles</h2>
    <table class="w-full bg-white dark:bg-gray-800 rounded shadow overflow-hidden">
        <thead class="bg-gray-100 dark:bg-gray-900">
            <tr>
                <th class="p-3 text-left">User</th>
                <th class="p-3 text-left">Status</th>
                <th class="p-3 text-left">Submitted</th>
                <th class="p-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kycs as $k): $user = $k['_user'] ?? null; ?>
            <tr class="border-t dark:border-gray-700">
                <td class="p-3"><?= htmlspecialchars($user['fullname'] ?? $user['email'] ?? 'User') ?></td>
                <td class="p-3"><?= htmlspecialchars($k['status']) ?></td>
                <td class="p-3"><?= htmlspecialchars($k['submitted_at']) ?></td>
                <td class="p-3">
                    <form method="POST" action="/admin/kyc/review/<?= intval($k['id']) ?>" style="display:inline">
                        <input type="hidden" name="status" value="approved">
                        <button class="px-3 py-1 bg-green-600 text-white rounded">Approve</button>
                    </form>
                    <form method="POST" action="/admin/kyc/review/<?= intval($k['id']) ?>" style="display:inline">
                        <input type="hidden" name="status" value="rejected">
                        <button class="px-3 py-1 bg-red-600 text-white rounded">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>