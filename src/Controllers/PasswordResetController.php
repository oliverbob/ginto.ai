<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Core\View;
use Ginto\Helpers\MailHelper;

/**
 * PasswordResetController
 * Handles forgot-password and reset-password flows.
 * Email is sent only on live (HTTPS) installs.
 */
class PasswordResetController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // GET /forgot-password   – show request form
    // POST /forgot-password  – send reset email
    // -------------------------------------------------------------------------
    public function forgotPassword(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleForgotPost();
            return;
        }

        View::view('user/forgot-password', [
            'title'      => 'Forgot Password',
            'csrf_token' => generateCsrfToken(true),
            'success'    => null,
            'error'      => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET  /reset-password?token=…  – show new-password form
    // POST /reset-password          – apply new password
    // -------------------------------------------------------------------------
    public function resetPassword(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleResetPost();
            return;
        }

        $token = trim($_GET['token'] ?? '');
        if (!$token || !$this->tokenIsValid($token)) {
            View::view('user/reset-password', [
                'title'      => 'Reset Password',
                'csrf_token' => generateCsrfToken(true),
                'token'      => '',
                'error'      => 'This reset link is invalid or has expired.',
            ]);
            return;
        }

        View::view('user/reset-password', [
            'title'      => 'Reset Password',
            'csrf_token' => generateCsrfToken(true),
            'token'      => htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
            'error'      => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function handleForgotPost(): void
    {
        $csrfOk = isset($_POST['csrf_token']) && hash_equals(
            $_SESSION['csrf_token'] ?? '', $_POST['csrf_token']
        );
        if (!$csrfOk) {
            $this->forgotView(null, 'Security token mismatch. Please try again.');
            return;
        }

        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->forgotView(null, 'Please enter a valid email address.');
            return;
        }

        // Always show the same success message regardless of whether email exists
        // (prevent user enumeration)
        $user = $this->db->get('users', ['id', 'email', 'username'], ['email' => $email, 'status' => 'active']);

        if ($user) {
            $this->createAndSendToken($user);
        }

        $this->forgotView('If that email is registered, you will receive a password reset link shortly.', null);
    }

    private function handleResetPost(): void
    {
        $csrfOk = isset($_POST['csrf_token']) && hash_equals(
            $_SESSION['csrf_token'] ?? '', $_POST['csrf_token']
        );
        if (!$csrfOk) {
            $this->resetView('', 'Security token mismatch. Please try again.');
            return;
        }

        $token    = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (!$token || !$this->tokenIsValid($token)) {
            $this->resetView('', 'This reset link is invalid or has expired.');
            return;
        }

        if (strlen($password) < 8) {
            $this->resetView($token, 'Password must be at least 8 characters.');
            return;
        }

        if ($password !== $confirm) {
            $this->resetView($token, 'Passwords do not match.');
            return;
        }

        // Fetch the reset row to get the email
        $tokenHash = hash('sha256', $token);
        $row = $this->db->get('password_resets', ['id', 'email'], [
            'token_hash' => $tokenHash,
            'used_at'    => null,
            'expires_at[>]' => date('Y-m-d H:i:s'),
        ]);

        if (!$row) {
            $this->resetView('', 'This reset link is invalid or has expired.');
            return;
        }

        // Update password
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->update('users', ['password_hash' => $hash], ['email' => $row['email']]);

        // Mark token as used
        $this->db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => $row['id']]);

        View::view('user/reset-password', [
            'title'      => 'Password Reset Successful',
            'csrf_token' => generateCsrfToken(true),
            'token'      => '',
            'error'      => null,
            'done'       => true,
        ]);
    }

    private function createAndSendToken(array $user): void
    {
        // Purge old unused tokens for this email (older than 1 hour)
        try {
            $this->db->delete('password_resets', [
                'email' => $user['email'],
                'used_at' => null,
                'created_at[<]' => date('Y-m-d H:i:s', time() - 3600),
            ]);
        } catch (\Throwable $e) {
            // non-fatal
        }

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $this->db->insert('password_resets', [
            'email'      => $user['email'],
            'token_hash' => $tokenHash,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
        ]);

        // Build URL from CADDY_DOMAIN because APP_URL is http://localhost
        // on installs proxied by Caddy (the normal production setup).
        $domain  = MailHelper::siteDomain();
        $baseUrl = 'https://' . $domain;
        $resetUrl = $baseUrl . '/reset-password?token=' . urlencode($token);
        $username = htmlspecialchars($user['username'] ?? $user['email'], ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f9f9f9;padding:30px;">
  <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:10px;padding:32px;border:1px solid #e5e7eb;">
    <h2 style="color:#d97706;margin-top:0;">Password Reset Request</h2>
    <p>Hi <strong>{$username}</strong>,</p>
    <p>We received a request to reset the password for your Ginto account.</p>
    <p style="text-align:center;margin:28px 0;">
      <a href="{$resetUrl}"
         style="background:#d97706;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;display:inline-block;">
        Reset My Password
      </a>
    </p>
    <p>This link expires in <strong>1 hour</strong>. If you did not request this, you can safely ignore this email.</p>
    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
    <p style="font-size:12px;color:#6b7280;">
      If the button above doesn't work, copy this link into your browser:<br>
      <a href="{$resetUrl}" style="color:#d97706;">{$resetUrl}</a>
    </p>
    <p style="font-size:12px;color:#6b7280;">— The Ginto Team at {$domain}</p>
  </div>
</body>
</html>
HTML;

        $text = "Hi {$username},\n\nReset your Ginto password here:\n{$resetUrl}\n\nThis link expires in 1 hour.\n\n— Ginto at {$domain}";

        MailHelper::send($user['email'], 'Reset your Ginto password', $html, $text);
    }

    private function tokenIsValid(string $token): bool
    {
        $tokenHash = hash('sha256', $token);
        $row = $this->db->get('password_resets', ['id'], [
            'token_hash' => $tokenHash,
            'used_at'    => null,
            'expires_at[>]' => date('Y-m-d H:i:s'),
        ]);
        return (bool) $row;
    }

    private function forgotView(?string $success, ?string $error): void
    {
        View::view('user/forgot-password', [
            'title'      => 'Forgot Password',
            'csrf_token' => generateCsrfToken(true),
            'success'    => $success,
            'error'      => $error,
        ]);
    }

    private function resetView(string $token, string $error): void
    {
        View::view('user/reset-password', [
            'title'      => 'Reset Password',
            'csrf_token' => generateCsrfToken(true),
            'token'      => htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
            'error'      => $error,
            'done'       => false,
        ]);
    }
}
