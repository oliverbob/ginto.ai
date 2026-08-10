# Relay API

Service-to-service endpoints under `/api/v1/relay`, for callers that have no
browser session. SilverQueen on `sq.ginto.ai` is the first of them.

## Why it exists

The Binance API key is IP-locked to `149.28.145.52`, the Vultr host this
application runs on. SilverQueen is reachable from the internet through the gntl
tunnel, but a tunnel only carries traffic *inward* — SilverQueen's own outbound
requests still leave from its ISP, and Binance refuses them. So it asks this host
instead, and this host makes the signed call from the address Binance expects.

Public market data — order books, klines, tickers — needs no key and no
whitelist. It should go straight to Binance from the browser, exactly as
`src/Views/academy/bot.php` already does. Do not proxy it through the relay;
that adds a hop and a bottleneck for nothing.

## How a caller identifies its member

Nobody logs in. The caller names the ginto.ai member it is acting for in the
`sub` claim of an HS256 token, signed with a secret both hosts hold:

```php
$token = JWT::encode([
    'sub' => 'oliverbob',        // users.username — never email, never phone
    'aud' => 'ginto-relay',
    'iss' => 'silverqueen',
    'iat' => time(),
    'exp' => time() + 300,
    'jti' => bin2hex(random_bytes(16)),
], $secret, 'HS256');
```

Sent as `Authorization: Bearer <token>`.

**The token is signed, not encrypted.** A JWT payload is base64url — anyone
holding a token can read the username in it. That is fine and it is not what the
signature is for: the username is not a secret, the authority to *claim* it is.
Confidentiality on the wire comes from TLS, which already covers the whole
request. What signing buys is that nobody can swap in a different username and
read someone else's account.

If a payload ever does need to be opaque at rest — because it lands in an access
log, say — encrypt it with `Ginto\Support\Crypto` rather than reaching for JWE.
That would want AES-256-GCM rather than the current CBC, since CBC has no
authentication tag and its ciphertext is malleable.

## What the relay checks

`Ginto\Support\RelayAuth::authenticate()`, in this order, cheapest first:

1. **Signature and expiry** (`Ginto\Support\Jwt`) — no database work for a forgery.
   The algorithm is fixed at HS256 and the token's own `alg` header is only ever
   compared against it, never used to select the algorithm. Trusting that header
   is the classic JWT forgery (`alg: none`).
2. **Lifetime cap** — 15 minutes, enforced here as well as at the issuer. The
   issuer is another machine; if it is compromised, a self-signed thousand-year
   token should still be refused at the door.
3. **Replay** — each `jti` is spendable exactly once, recorded as a file created
   with `O_EXCL` so two copies of one token arriving together cannot both win.
4. **Membership** — `users.username` must resolve to an `active` account with a
   live row in `user_subscriptions`. This mirrors
   `AcademyController::hasActiveSubscription()`, so a member who lapses loses the
   API at the same moment they lose `/academy/bot`.

Failures return a deliberately vague message. Whether a token was expired or
merely wrong, and whether a username exists, is useful to an attacker and to
nobody else; the specific reason goes to the error log.

## Configuration

On ginto.ai:

```
RELAY_JWT_SECRET=<openssl rand -hex 32>
RELAY_ALLOWED_USERS=          # optional, comma-separated; empty = any member
```

On the calling host (SilverQueen), the same secret as `GINTO_RELAY_SECRET`.

Anything shorter than 32 bytes is refused at both ends — `firebase/php-jwt` v7
will not sign with a shorter key, so this side enforces the same floor rather
than accepting a key the other side cannot even use.

**`RELAY_ALLOWED_USERS` is the bound worth setting.** Whoever holds the shared
secret can mint a token for *any* username; the allowlist means a stolen secret
still only reaches the accounts you named.

## Endpoints

### `GET /api/v1/relay/session`

Identity and entitlement for the token's member. The cheapest call the relay has
— no Binance request, no writes — so a caller can prove its secret, its clock and
its subscription all line up before anything expensive depends on them.

```json
{
  "ok": true,
  "username": "oliverbob",
  "user_id": 1,
  "fullname": "Oliver Bob R. Lagumen",
  "plan": "academy_pro",
  "is_pro": true,
  "can": { "paper_trade": true, "bot": true },
  "expires_at": 1786000000,
  "server_time": 1785999700
}
```

Errors are `{"ok": false, "error": "..."}` with `401` (bad or replayed token),
`403` (no access for that account) or `503` (relay not configured).

## Calling it

From SilverQueen, via `SilverQueen\Trading\GintoRelayClient`:

```php
$relay = GintoRelayClient::fromEnv();
$res   = $relay->session('oliverbob');
if ($res['ok']) {
    $plan = $res['data']['plan'];
}
```

By hand, to check a deployment:

```bash
curl -sS https://ginto.ai/api/v1/relay/session \
     -H "Authorization: Bearer $TOKEN" | jq
```

Note that a token is single-use: `jti` replay is refused, so mint a fresh one per
request rather than exporting one and repeating the curl.

## Adding endpoints

Handlers go in `src/Controllers/RelayController.php` and begin with:

```php
$member = $this->member();
if ($member === null) return;
```

Do nothing before that call and nothing at all if it returns null. `POST` routes
must also be added to `CsrfMiddleware::$skipPaths` — relay callers authenticate
with a token, not a browser session, so there is no CSRF token for them to send
(`/api/tunnel/bind` is there for the same reason).
