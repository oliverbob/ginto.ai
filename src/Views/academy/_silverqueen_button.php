<?php
/**
 * academy/_silverqueen_button.php — the SilverQueen entry point for the Academy header.
 *
 * Renders NOTHING unless $showSilverQueen is true. That flag comes from
 * SilverQueenController::canAccess(), the same rule the /silverqueen routes enforce, so a
 * member without an active Pro Trader membership never sees the button, the wallet, or any
 * hint that the console exists. Include it inside a header's right-hand action group:
 *
 *     <?php include __DIR__ . '/_silverqueen_button.php'; ?>
 */
if (empty($showSilverQueen)) return;
?>
<a href="/silverqueen" title="SilverQueen — resource allocation console"
   class="inline-flex items-center gap-2 pl-1 pr-1 sm:pr-2.5 py-1 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/60 dark:bg-white/5 hover:border-primary/60 hover:bg-primary/5 transition-colors">
    <span class="w-7 h-7 shrink-0 rounded-lg bg-gradient-to-br from-slate-300 to-slate-500 dark:from-slate-400 dark:to-slate-700 text-white inline-flex items-center justify-center text-xs">
        <i class="fas fa-chess-queen"></i>
    </span>
    <span class="hidden sm:inline text-sm font-bold leading-none">Silver<span class="text-primary">Queen</span></span>
</a>
