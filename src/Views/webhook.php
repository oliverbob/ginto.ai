<?php
$webhook = $webhook ?? ['is_active' => false, 'message' => 'No status available'];
?><div style="font-family: Arial, sans-serif; padding:20px;">
    <h1>Webhook Status</h1>
    <p><strong>Active:</strong> <?php echo $webhook['is_active'] ? 'Yes' : 'No'; ?></p>
    <p><strong>Message:</strong> <?php echo htmlspecialchars($webhook['message']); ?></p>
</div>
