<?php
// Keep login failures within the single SilverQueen pre-login experience.
$loginError = $error ?? null;
$loginOld = $old ?? [];
$gintoLoginActive = true;
require __DIR__ . '/../silverqueen_login.php';
