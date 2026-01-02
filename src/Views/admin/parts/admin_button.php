<?php
// Shared admin button helper
// Usage: admin_button('Label', '/link', ['variant'=>'primary'|'outline', 'attrs'=>['id'=>'foo']]);

if (!function_exists('admin_button')) {
    function admin_button(string $label, string $href = null, array $opts = []) {
        $variant = $opts['variant'] ?? 'primary';
        $attrs = $opts['attrs'] ?? [];
        $extra = '';
        foreach ($attrs as $k => $v) {
            $extra .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
        }

        // class sets
        $classes = '';
        if ($variant === 'outline') {
            // Use theme-aware colors: light mode gets darker text/border, dark mode keeps lighter text
            // Make the outline variant explicit about backgrounds and text so it
            // doesn't look washed-out when adjacent CTAs change state.
            // Light: white background + dark text, subtle border.
            // Dark: transparent/soft-surface background + bright text.
            // Improve light-mode readability: stronger text, slightly darker border and subtle hover/bg
            $classes = 'inline-flex admin-btn items-center px-3 py-2 border rounded text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-yellow-400 transition-colors '
                . 'bg-white text-gray-900 border-gray-300 hover:bg-gray-100 shadow-sm '
                . 'dark:bg-transparent dark:text-gray-100 dark:border-gray-700 dark:hover:bg-gray-700/20';
        } else { // primary
            // Primary remains yellow but ensure it behaves well in dark & light
            $classes = 'inline-flex admin-btn items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 rounded shadow text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-yellow-400';
        }

        $safeLabel = htmlspecialchars($label);
        if ($href) {
            echo '<a href="' . htmlspecialchars($href) . '" class="' . $classes . '"' . $extra . ' title="' . $safeLabel . '" aria-label="' . $safeLabel . '">' . $safeLabel . '</a>';
        } else {
            echo '<button class="' . $classes . '"' . $extra . ' type="button" title="' . $safeLabel . '" aria-label="' . $safeLabel . '">' . $safeLabel . '</button>';
        }
    }
}
