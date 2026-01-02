# Chat Developer Documentation

This document covers the technical implementation details of the `/chat` endpoint and its supporting infrastructure.

## Table of Contents

1. [Provider Key Management](#provider-key-management)
2. [Rate Limiting](#rate-limiting)
3. [TTS Rate Limiting](#tts-rate-limiting)
4. [Admin Panel](#admin-panel)
5. [UI Components](#ui-components)
6. [Unified Key Rotation](#unified-key-rotation)

---

## Provider Key Management

### Overview

The chat system supports multiple API keys per provider (Cerebras, Groq) with automatic rotation when rate limits are hit. Keys can be configured via environment variables or stored in the database.

### Key Sources (Priority Order)

1. **Environment Variables** (`.env`) - Used first
   - `CEREBRAS_API_KEY` - Cerebras API key
   - `GROQ_API_KEY` - Groq API key
   
2. **Database Keys** (`provider_keys` table) - Used in ID order when .env key is exhausted

### Database Schema

```sql
CREATE TABLE provider_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,              -- 'groq', 'cerebras', 'openai'
    api_key VARCHAR(255) NOT NULL,              -- The actual API key
    key_name VARCHAR(100) DEFAULT NULL,         -- Friendly name
    tier ENUM('basic', 'production') NOT NULL DEFAULT 'basic',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at DATETIME DEFAULT NULL,
    last_error_at DATETIME DEFAULT NULL,
    error_count INT NOT NULL DEFAULT 0,
    rate_limit_reset_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tier Information

- **Basic (Free)**: Lower rate limits, suitable for development
  - Cerebras: 30 RPM, 14,400 RPD, 64K TPM, 1M TPD
  - Groq: 1,000 RPM, 500K RPD, 250K TPM (varies by model)
  
- **Production (Paid)**: Higher rate limits for production use
  - Cerebras: 1,000 RPM, 1.44M RPD, 1M TPM, 2B TPD
  - Groq: Similar scaling based on tier

### ProviderKeyManager Class

Location: `src/Core/ProviderKeyManager.php`

Key methods:

```php
// Get the first available key (any provider, for default chat)
$keyManager->getFirstAvailableKey(): ?array

// Get the next available key after current one (for rotation)
$keyManager->getNextAvailableKey(int $currentKeyId): ?array

// Get a key for a specific provider (for vision/web search)
$keyManager->getAvailableKey(string $provider): ?array

// Mark a key as rate-limited
$keyManager->markRateLimited(int $keyId, \DateTimeInterface $resetAt): void

// Mark a key as healthy (clear rate limit)
$keyManager->markHealthy(int $keyId): void
```

---

## Rate Limiting

### Configuration

Environment variables:

```env
RATE_LIMIT_ADMIN_PERCENT=50      # Admins get 50% of org limit
RATE_LIMIT_USER_PERCENT=10       # Users get 10% of org limit
RATE_LIMIT_VISITOR_PERCENT=5     # Visitors get 5% of org limit
RATE_LIMIT_FALLBACK_PROVIDER=cerebras
RATE_LIMIT_FALLBACK_THRESHOLD=80 # Switch at 80% usage
```

### Rate Limit Types

- **RPM**: Requests per minute
- **RPD**: Requests per day
- **TPM**: Tokens per minute
- **TPD**: Tokens per day

### RateLimitService Class

Location: `src/Core/RateLimitService.php`

Key methods:

```php
// Check if user can make a request
$rateLimitService->canMakeRequest(
    string $userId, 
    string $userRole, 
    string $provider, 
    string $model
): array

// Get rate limits for a provider/model
$rateLimitService->getRateLimits(string $provider, string $model): array

// Log an API request
$rateLimitService->logRequest(array $data): bool

// Get organization-wide usage
$rateLimitService->getOrgUsage(string $provider, string $model, string $window): array

// Select best provider based on current usage
$rateLimitService->selectProvider(string $preferredProvider, string $model): array
```

### Database Tables

```sql
-- Rate limit configuration
CREATE TABLE rate_limit_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(32) NOT NULL,
    model VARCHAR(128) NOT NULL,
    limit_type VARCHAR(16) NOT NULL,  -- 'rpm', 'rpd', 'tpm', 'tpd'
    limit_value INT UNSIGNED NOT NULL
);

-- API request logging
CREATE TABLE api_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_role VARCHAR(32) DEFAULT 'visitor',
    provider VARCHAR(32) NOT NULL,
    model VARCHAR(128) NOT NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    tokens_total INT DEFAULT 0,
    request_type VARCHAR(32) DEFAULT 'chat',
    response_status VARCHAR(16) DEFAULT 'success',
    fallback_used TINYINT(1) DEFAULT 0,
    latency_ms INT DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## TTS Rate Limiting

### Overview

Text-to-Speech (TTS) requests via Groq's PlayAI API are rate-limited separately from chat requests. Rate limits are applied per-role to ensure fair usage across all users.

### TTS Rate Limits by Role

| Role | Limit | Window | Behavior on Limit |
|------|-------|--------|-------------------|
| **Admin** | 50% of org quota | Per minute/day | Info modal shown |
| **User** | 30 requests | Per hour | Upgrade modal shown |
| **Visitor** | 10 requests | Per session | Register modal shown |

### Organization-Wide TTS Limits

| Model | RPM | RPD |
|-------|-----|-----|
| `playai-tts` | 250 | 100,000 |
| `playai-tts-turbo` | 250 | 100,000 |
| `gpt-4o-mini-tts` | 250 | 100,000 |

### Behavior When Limit is Reached

When a user hits their TTS rate limit:

1. TTS endpoint returns HTTP 429 with JSON response
2. Headers set: `X-Ginto-TTS: rate-limited` and `X-Ginto-TTS-Reason: <reason>`
3. Client shows a modal with role-appropriate message:
   - **Visitors**: Prompted to register for higher limits
   - **Users**: Prompted to upgrade or contact for custom limits
   - **Admins**: Informed that TTS will resume when capacity is available
4. TTS is disabled for the session to prevent repeated failures

### Response Format (HTTP 429)

```json
{
  "error": "tts_rate_limit",
  "reason": "visitor_session_limit",
  "limit_type": "visitor",
  "user_role": "visitor",
  "usage": {
    "current": 10,
    "limit": 10,
    "window": "session"
  },
  "message": "You've reached the TTS limit for guests. Register for higher limits!"
}
```

### Reason Codes

| Reason | Description |
|--------|-------------|
| `visitor_session_limit` | Visitor exceeded 10 requests per session |
| `user_hourly_limit` | User exceeded 30 requests per hour |
| `org_rpm_threshold` | Organization minute limit approaching |
| `org_rpd_threshold` | Organization daily limit approaching |

### Configuration

```env
GROQ_TTS_MODEL=gpt-4o-mini-tts    # Default TTS model
GROQ_TTS_VOICE=alloy              # Default voice
```

### Implementation

Location: `/audio/tts` route in `src/Routes/web.php`

```php
// Check TTS rate limit with role-based limits
$ttsCheck = $rateLimitService->canMakeTtsRequest(
    $model, 
    'groq', 
    $userRole,     // 'admin', 'user', 'visitor'
    $userId,       // null for visitors
    $sessionId     // For visitor session tracking
);

if (!$ttsCheck['allowed']) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'tts_rate_limit',
        'reason' => $ttsCheck['reason'],
        // ... additional data
    ]);
    exit;
}
```

### Logging TTS Requests

```php
$rateLimitService->logTtsRequest(
    string $model,           // e.g., 'playai-tts'
    string $provider,        // default 'groq'
    ?int $userId,            // null for guests
    string $userRole,        // 'admin', 'user', 'visitor'
    bool $success,           // true/false
    ?string $sessionId       // For visitor tracking
);
    bool $success           // true/false
);
```

---

## Admin Panel

### API Keys Tab

Visible only to admin users. Allows:

- Viewing all configured API keys (environment + database)
- Adding new API keys with provider/tier selection
- Deleting database-stored keys (not .env keys)

### Admin Detection

Uses `UserController::isAdmin()` static method for centralized admin checking:

```php
// In routes
$isAdmin = \App\Controllers\UserController::isAdmin($userId);

// Or with session fallback
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin' || 
           \App\Controllers\UserController::isAdmin($_SESSION['user_id'] ?? null);
```

### API Endpoints

```
GET  /api/provider-keys     - List all keys (admin only)
POST /api/provider-keys     - Add new key (admin only)
DELETE /api/provider-keys   - Delete key (admin only, DB keys only)
```

### CSRF Protection

All admin API endpoints require CSRF token:

```javascript
fetch('/api/provider-keys', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ provider: 'cerebras', api_key: '...', tier: 'basic' })
});
```

---

## UI Components

### Toast Notifications

Modern toast notifications replace browser alerts:

```javascript
// Show success toast
showToast('API key added successfully', 'success');

// Show error toast
showToast('Failed to add key', 'error');
```

Implementation: `showToast(message, type)` function in `chat.php`

- Success: Green background, slides from bottom-right
- Error: Red background, slides from bottom-right
- Auto-dismisses after 3 seconds

### Universal Confirm Modal

Replaces browser `confirm()` with styled modal:

```javascript
// Returns a Promise
const confirmed = await showConfirmModal(
    'Delete API Key?',                                    // title
    'Are you sure you want to delete this key?',          // message
    { confirmText: 'Delete', confirmClass: 'bg-red-600' } // options
);

if (confirmed) {
    // User clicked confirm
}
```

Modal features:
- Customizable title and message
- Customizable button text and colors
- Promise-based API for async/await usage
- Keyboard support (Escape to cancel)
- Click outside to cancel

---

## Unified Key Rotation

### How It Works

1. **Initial Selection**: `getFirstAvailableKey()` returns the first available key regardless of provider
   - Checks .env keys first (CEREBRAS_API_KEY, GROQ_API_KEY)
   - Then database keys by ID order (ascending)

2. **On Rate Limit**: When a 429 error is received:
   - Current key is marked as rate-limited with `markRateLimited()`
   - `getNextAvailableKey($currentKeyId)` finds the next available key
   - System continues with new key

3. **Key Recovery**: When `rate_limit_reset_at` passes:
   - Key becomes available again for selection
   - `markHealthy()` clears the rate limit flag

### Rotation Flow

```
.env CEREBRAS_API_KEY (if set)
        ↓ (rate limit hit)
.env GROQ_API_KEY (if set)
        ↓ (rate limit hit)
DB Key ID 1
        ↓ (rate limit hit)
DB Key ID 2
        ↓ (rate limit hit)
...
        ↓ (all exhausted)
Back to .env CEREBRAS_API_KEY (if reset)
```

### Provider-Specific Selection

For features requiring specific providers:

```php
// Vision requires Groq (llama models)
$keyManager->getAvailableKey('groq');

// Web search requires Groq (compound-beta)
$keyManager->getAvailableKey('groq');
```

---

## Environment Variables Reference

### LLM Providers

```env
CEREBRAS_API_KEY=           # Primary Cerebras key
GROQ_API_KEY=               # Primary Groq key
```

### TTS Configuration

```env
GROQ_TTS_MODEL=gpt-4o-mini-tts
GROQ_TTS_VOICE=alloy
GROQ_TTS_CONTENT_TYPE=audio/mpeg
```

### Rate Limiting

```env
RATE_LIMIT_ADMIN_PERCENT=50
RATE_LIMIT_USER_PERCENT=10
RATE_LIMIT_VISITOR_PERCENT=5
RATE_LIMIT_FALLBACK_PROVIDER=cerebras
RATE_LIMIT_FALLBACK_THRESHOLD=80
```

---

## Troubleshooting

### Common Issues

1. **TTS not playing**: Check if rate limit is hit (look for `X-Ginto-TTS: rate-limited` header)

2. **API key not rotating**: Ensure `provider_keys` table exists and has `rate_limit_reset_at` column

3. **Admin tab not visible**: Verify user has `role = 'admin'` in database

4. **CSRF errors**: Ensure meta tag exists: `<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">`

### Logs

Check for rate limit events:

```bash
tail -f /var/log/ginto/error.log | grep -E "(TTS|rate.limit)"
```

---

## Migration

To add TTS rate limits to existing installation:

```sql
INSERT INTO rate_limit_config (provider, model, limit_type, limit_value) VALUES
('groq', 'playai-tts', 'rpm', 250),
('groq', 'playai-tts', 'rpd', 100000),
('groq', 'playai-tts-turbo', 'rpm', 250),
('groq', 'playai-tts-turbo', 'rpd', 100000),
('groq', 'gpt-4o-mini-tts', 'rpm', 250),
('groq', 'gpt-4o-mini-tts', 'rpd', 100000)
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);
```
