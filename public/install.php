<?php
/**
 * Installation endpoint - routes to the actual installer
 */

// Set the correct working directory
chdir(__DIR__ . '/../install');

// Include the actual installer
include __DIR__ . '/../install/install.php';