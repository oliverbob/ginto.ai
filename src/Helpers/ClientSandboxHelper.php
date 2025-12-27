<?php
namespace Ginto\Helpers;

use Ginto\Core\Database;

/**
 * Helper functions to manage client sandboxes used by the Playground editor.
 * 
 * NOTE: As of the LXD-based architecture, user files live INSIDE LXD containers,
 * NOT on the host filesystem. This helper only manages sandbox_id assignment
 * and database tracking. No directories are created in /clients/.
 */
class ClientSandboxHelper
{
    // Max length for randomly generated sandbox folder name
    const SANDBOX_ID_LENGTH = 12;

    /**
     * Check if user is admin based on session data.
     */
    private static function isAdminSession($session): bool
    {
        if (!is_array($session)) return false;
        
        return !empty($session['is_admin']) ||
            (!empty($session['role_id']) && in_array((int)$session['role_id'], [1,2], true)) ||
            (!empty($session['user']['is_admin']) && $session['user']['is_admin']) ||
            (!empty($session['role']) && strtolower($session['role']) === 'admin') ||
            (!empty($session['user']['role']) && strtolower($session['user']['role']) === 'admin');
    }

    /**
     * Get or create a sandbox ID for the user. Returns the sandbox_id string.
     * 
     * IMPORTANT: This does NOT create directories on the host filesystem.
     * Files live inside LXD containers. Use LxdSandboxManager for container operations.
     *
     * @param \Medoo\Medoo|null $db
     * @param array|null $session (typically $_SESSION)
     * @return string|null The sandbox ID, or null if cannot determine
     */
    public static function getOrCreateSandboxId($db = null, $session = null): ?string
    {
        $isAdmin = self::isAdminSession($session);
        $forceSandbox = is_array($session) && (!empty($session['playground_use_sandbox']) || !empty($session['playground_admin_sandbox']));
        
        if ($isAdmin && !$forceSandbox) {
            return null; // Admin uses project root
        }

        // Check session for existing sandbox_id
        if (!empty($session['sandbox_id'])) {
            $sandboxId = $session['sandbox_id'];
            
            // SYNC CHECK: Validate sandbox still exists in DB and container exists
            $isValid = self::validateSandboxExists($sandboxId, $db);
            
            if ($isValid) {
                return $sandboxId;
            }
            
            // Invalid sandbox - clear session and fall through to create new
            if (isset($_SESSION)) {
                unset($_SESSION['sandbox_id']);
            }
        }

        $userId = $session['user']['id'] ?? $session['user_id'] ?? null;
        $publicId = $session['user']['public_id'] ?? $session['public_id'] ?? null;

        if ($db) {
            try {
                // Find existing mapping - get the MOST RECENT one that has a valid container
                if ($userId) {
                    $row = $db->get('client_sandboxes', ['sandbox_id'], [
                        'user_id' => $userId,
                        'ORDER' => ['id' => 'DESC']
                    ]);
                } elseif ($publicId) {
                    $row = $db->get('client_sandboxes', ['sandbox_id'], [
                        'public_id' => $publicId,
                        'ORDER' => ['id' => 'DESC']
                    ]);
                } else {
                    $row = null;
                }

                if (!empty($row['sandbox_id'])) {
                    // Validate this sandbox still exists before returning it
                    if (self::validateSandboxExists($row['sandbox_id'], $db)) {
                        return $row['sandbox_id'];
                    }
                    // If sandbox doesn't exist, delete this stale record
                    $db->delete('client_sandboxes', ['sandbox_id' => $row['sandbox_id']]);
                }

                // Create a unique random sandbox id
                $try = 0;
                do {
                    $candidate = self::randomId(self::SANDBOX_ID_LENGTH);
                    $exists = $db->has('client_sandboxes', ['sandbox_id' => $candidate]);
                    $try++;
                } while ($exists && $try < 16);

                if (empty($candidate)) {
                    $candidate = substr(preg_replace('/[^a-z0-9]/i', '', ($publicId ?: 'u' . $userId ?? uniqid())), 0, self::SANDBOX_ID_LENGTH);
                }

                // Insert mapping (NO directory creation!)
                $data = ['sandbox_id' => $candidate, 'created_at' => date('Y-m-d H:i:s')];
                if ($userId) $data['user_id'] = $userId;
                if ($publicId) $data['public_id'] = $publicId;

                $db->insert('client_sandboxes', $data);
                return $candidate;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        // Without DB: generate from user info
        $name = $publicId ?: ($userId ? 'u' . $userId : null);
        if ($name) {
            return substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, self::SANDBOX_ID_LENGTH);
        }

        return null;
    }

    /**
     * Get sandbox ID if it exists (does not create).
     * 
     * @param \Medoo\Medoo|null $db
     * @param array|null $session
     * @param bool $validate If true, validates sandbox actually exists (DB + container)
     * @return string|null
     */
    public static function getSandboxIdIfExists($db = null, $session = null, bool $validate = false): ?string
    {
        $sandboxId = null;
        
        if (!empty($session['sandbox_id'])) {
            $sandboxId = $session['sandbox_id'];
        } else {
            $userId = $session['user']['id'] ?? $session['user_id'] ?? null;
            $publicId = $session['user']['public_id'] ?? $session['public_id'] ?? null;

            if ($db) {
                try {
                    if ($userId) {
                        $row = $db->get('client_sandboxes', ['sandbox_id'], ['user_id' => $userId]);
                    } elseif ($publicId) {
                        $row = $db->get('client_sandboxes', ['sandbox_id'], ['public_id' => $publicId]);
                    } else {
                        $row = null;
                    }
                    if (!empty($row['sandbox_id'])) {
                        $sandboxId = $row['sandbox_id'];
                    }
                } catch (\Throwable $_) {}
            }
        }
        
        // Validate sandbox exists if requested
        if ($sandboxId && $validate) {
            if (!self::validateSandboxExists($sandboxId, $db)) {
                // Sandbox doesn't exist - clear session and return null
                if (isset($_SESSION['sandbox_id']) && $_SESSION['sandbox_id'] === $sandboxId) {
                    unset($_SESSION['sandbox_id']);
                }
                return null;
            }
        }

        return $sandboxId;
    }

    /**
     * DEPRECATED: Legacy method - returns sandbox ID marker path.
     * For admin users, returns project root.
     * For regular users, returns sandbox_id for container-based access.
     *
     * @deprecated Use getOrCreateSandboxId() instead
     */
    public static function getOrCreateSandboxRoot($db = null, $session = null)
    {
        $isAdmin = self::isAdminSession($session);
        $forceSandbox = is_array($session) && (!empty($session['playground_use_sandbox']) || !empty($session['playground_admin_sandbox']));
        
        if ($isAdmin && !$forceSandbox) {
            return realpath(ROOT_PATH) ?: ROOT_PATH;
        }

        $sandboxId = self::getOrCreateSandboxId($db, $session);
        if ($sandboxId) {
            // Return the sandbox ID - callers should use LxdSandboxManager
            return $sandboxId;
        }

        return realpath(ROOT_PATH) ?: ROOT_PATH;
    }

    /**
     * DEPRECATED: Legacy method - returns sandbox ID if exists.
     * @deprecated Use getSandboxIdIfExists() instead
     * @param bool $validate If true, validates sandbox actually exists (DB + container)
     */
    public static function getSandboxRootIfExists($db = null, $session = null, bool $validate = false)
    {
        $sandboxId = self::getSandboxIdIfExists($db, $session, $validate);
        return $sandboxId; // Returns null if not exists
    }

    /**
     * Validate that a sandbox exists in both DB and as an LXD container.
     * This prevents orphan references where session has sandbox_id but resources are missing.
     * 
     * @param string $sandboxId The sandbox ID to validate
     * @param \Medoo\Medoo|null $db Database connection
     * @return bool True if sandbox exists in DB AND container exists
     */
    public static function validateSandboxExists(string $sandboxId, $db = null): bool
    {
        // Check DB entry exists
        if ($db) {
            try {
                $exists = $db->has('client_sandboxes', ['sandbox_id' => $sandboxId]);
                if (!$exists) {
                    return false; // No DB entry
                }
            } catch (\Throwable $_) {
                // DB error - assume invalid to be safe
                return false;
            }
        }
        
        // Check LXD container exists
        if (class_exists('\\Ginto\\Helpers\\LxdSandboxManager')) {
            $containerExists = LxdSandboxManager::sandboxExists($sandboxId);
            if (!$containerExists) {
                return false; // No container
            }
        }
        
        return true;
    }

    /**
     * Helper to generate a random alpha-numeric id.
     */
    private static function randomId($len = 12)
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}
