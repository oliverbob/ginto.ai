<?php
// Simple earnings view
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Earnings API</title>
</head>
<body>
    <h1><?php echo htmlspecialchars($message ?? ''); ?></h1>
    <div>
        <p><strong>Total Commissions:</strong> <?php echo number_format((float)($totalCommissions ?? 0), 2); ?></p>
        <p><strong>Monthly Commissions:</strong> <?php echo number_format((float)($monthlyCommissions ?? 0), 2); ?></p>
    </div>
    <?php if (!empty($user)): ?>
    <div>
        <h2>User: <?php echo htmlspecialchars($user['username'] ?? ($user['id'] ?? '')); ?></h2>
        <?php if (!empty($perLevelSums) && is_array($perLevelSums)): ?>
            <h3>Per-level sums (by level)</h3>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead><tr><th>Level</th><th>Downlines</th><th>Sum (Percentage)</th><th>Earned (@ rate)</th><th>Cumulative Earned</th></tr></thead>
                <tbody>
                <?php $runningEarned = 0.0; foreach ($perLevelSums as $i => $amt):
                    $levelNumber = $i + 1; // human-friendly level (1-based)
                    $rate = (isset($commissionRates[$i]) ? floatval($commissionRates[$i]) : 0.0);
                    $earned = isset($perLevelEarnings[$i]) ? $perLevelEarnings[$i] : ($amt * $rate);
                    $count = isset($perLevelCounts[$i]) ? intval($perLevelCounts[$i]) : 0;
                    $runningEarned += $earned;
                ?>
                    <tr>
                        <td><?php echo $levelNumber; ?></td>
                        <td><?php echo $count; ?></td>
                        <td><?php echo number_format((float)$amt, 2); ?> (<?php echo number_format($rate * 100, 2); ?>%)</td>
                        <td><?php echo number_format((float)$earned, 2); ?></td>
                        <td><?php echo number_format((float)$runningEarned, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php
                        $totalDownlines = array_sum($perLevelCounts ?? []);
                        $totalSum = array_sum($perLevelSums ?? []);
                        $totalEarned = array_sum($perLevelEarnings ?? []);
                        $finalCumulative = $runningEarned;
                    ?>
                    <tr style="font-weight:700;">
                        <td>Totals</td>
                        <td><?php echo intval($totalDownlines); ?></td>
                        <td><?php echo number_format((float)$totalSum, 2); ?> (<?php echo number_format(array_sum($commissionRates) * 100, 2); ?>%)</td>
                        <td><?php echo number_format((float)$totalEarned, 2); ?></td>
                        <td><?php echo number_format((float)$finalCumulative, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
            <p style="margin-top:10px;font-weight:600;">Summary: Total downlines across levels: <?php echo intval($totalDownlines); ?> — Total sales sum: <?php echo number_format((float)$totalSum,2); ?> — Total commission payouts: <?php echo number_format((float)$totalEarned,2); ?>.</p>
        <?php else: ?>
            <p>No per-level data available.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
