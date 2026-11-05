<?php
// src/Playground/LtradingServer.php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * LtradingServer — the live trading stream at /ltrading.
 *
 * This exists to delete work, not to add a feature. /academy/bot is a polling
 * page: every open tab asks for /academy/bot/data and /academy/wallet on a timer
 * and /academy/markets every minute. Each of those boots PHP, opens MySQL and
 * asks Binance for the same marks the tab next to it just asked for, so the cost
 * of the page is a straight multiple of how many people have it open — and the
 * moment a class is watching together is exactly when it gets slowest.
 *
 * Here the loop ticks once and everybody receives the result. The shared half of
 * the payload — the class bot's positions, its marks, its reasoning — is
 * computed a single time per tick no matter how many are connected. Only the
 * per-member half is done per connection, and only for members actually
 * connected, which is the part that genuinely cannot be shared.
 *
 * What deliberately does NOT come through here is market data. Prices, candles
 * and order books are public: the browser opens its own socket to Binance for
 * those, exactly as bot.php already does. Relaying them would put back the
 * per-viewer cost this class removes, on the highest-frequency data on the page.
 *
 * Protocol — client sends:
 *   {"type":"auth","token":"<relay JWT>"}
 *   {"type":"ping"}
 *
 * Server sends:
 *   {"type":"ready","username":...,"plan":...,"is_pro":...,"interval":...}
 *   {"type":"error","error":"...","fatal":bool}
 *   {"type":"tick","bot":{...},"wallet":{...}}     the shared + personal state
 *   {"type":"markets","popular":[...],"gainers":[...],"losers":[...]}
 *   {"type":"pong"}
 */
class LtradingServer implements MessageComponentInterface
{
    /** How often the shared state is recomputed and pushed. */
    private const TICK_SECONDS = 5;

    /** Market movers change slowly and cost a full ticker sweep; they get their own cadence. */
    private const MARKETS_SECONDS = 60;

    /** A connection that has not authenticated by now is dropped. */
    private const AUTH_GRACE_SECONDS = 10;

    /** Refuse oversized frames rather than parsing them: nothing legitimate here is large. */
    private const MAX_FRAME_BYTES = 8192;

    /** @var \SplObjectStorage<ConnectionInterface,null> */
    private $clients;

    /** @var array<int,array{conn:ConnectionInterface,user_id:int,username:string,plan:string,is_pro:bool,auth:bool,opened:int}> */
    private array $meta = [];

    /** @var \PDO|null */
    private $db;

    /** Cached shared payload, rebuilt once per tick. */
    private ?array $sharedTick = null;

    private ?array $markets = null;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->connect();
    }

    // ── lifecycle ─────────────────────────────────────────────────────────────

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->meta[$conn->resourceId] = [
            'conn' => $conn, 'user_id' => 0, 'username' => '', 'plan' => '',
            'is_pro' => false, 'auth' => false, 'opened' => time(),
        ];
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        unset($this->meta[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        error_log('[LtradingServer] ' . $e->getMessage());
        $conn->close();
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        if (strlen((string) $msg) > self::MAX_FRAME_BYTES) {
            $this->send($from, ['type' => 'error', 'error' => 'Frame too large.', 'fatal' => true]);
            $from->close();
            return;
        }

        $data = json_decode((string) $msg, true);
        if (!is_array($data)) {
            return;
        }

        switch ($data['type'] ?? '') {
            case 'auth':
                $this->authenticate($from, (string) ($data['token'] ?? ''));
                break;

            case 'ping':
                $this->send($from, ['type' => 'pong']);
                break;
        }
    }

    /**
     * Bind a connection to the member its token names.
     *
     * The token is spent here exactly as it would be over HTTP, jti and all, so
     * a stream cannot be opened by replaying a token captured from a REST call.
     * That does mean a reconnect needs a fresh token, which is why the client is
     * told plainly that the failure is fatal rather than left retrying a token
     * the server will never accept again.
     */
    private function authenticate(ConnectionInterface $conn, string $token): void
    {
        $id = $conn->resourceId;
        if (!isset($this->meta[$id])) {
            return;
        }
        if ($this->meta[$id]['auth']) {
            return;             // already bound; ignore a second attempt
        }

        try {
            $member = \Ginto\Support\RelayAuth::authenticateToken($token);
        } catch (\Throwable $e) {
            $this->send($conn, ['type' => 'error', 'error' => $e->getMessage(), 'fatal' => true]);
            $conn->close();
            return;
        }

        $this->meta[$id]['auth']     = true;
        $this->meta[$id]['user_id']  = (int) $member['user']['id'];
        $this->meta[$id]['username'] = $member['username'];
        $this->meta[$id]['plan']     = $member['plan'];
        $this->meta[$id]['is_pro']   = (bool) $member['is_pro'];

        $this->send($conn, [
            'type' => 'ready', 'username' => $member['username'],
            'plan' => $member['plan'], 'is_pro' => (bool) $member['is_pro'],
            'interval' => self::TICK_SECONDS,
        ]);

        // Do not make a new arrival wait a whole tick to see anything: send the
        // state we already have, then let them join the normal cadence.
        if ($this->sharedTick !== null) {
            $this->pushTo($conn);
        }
        if ($this->markets !== null) {
            $this->send($conn, ['type' => 'markets'] + $this->markets);
        }
    }

    // ── the loop ──────────────────────────────────────────────────────────────

    /**
     * Wire the periodic work onto the server's event loop.
     *
     * Called once at startup by bin/start_rachet_stream.php. The timers live on
     * the loop rather than inside a request, which is the whole point: they run
     * whether or not anybody is connected to ask for them, and they run once.
     */
    public function attach(\React\EventLoop\LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(self::TICK_SECONDS, function (): void {
            try { $this->tick(); } catch (\Throwable $e) { error_log('[LtradingServer] tick: ' . $e->getMessage()); }
        });

        $loop->addPeriodicTimer(self::MARKETS_SECONDS, function (): void {
            try { $this->refreshMarkets(); } catch (\Throwable $e) { error_log('[LtradingServer] markets: ' . $e->getMessage()); }
        });

        // A socket that connects and never authenticates costs a slot and a file
        // descriptor; without this it costs them forever.
        $loop->addPeriodicTimer(5, function (): void {
            $cutoff = time() - self::AUTH_GRACE_SECONDS;
            foreach ($this->meta as $m) {
                if (!$m['auth'] && $m['opened'] < $cutoff) {
                    $this->send($m['conn'], ['type' => 'error', 'error' => 'Authentication timed out.', 'fatal' => true]);
                    $m['conn']->close();
                }
            }
        });
    }

    /** Recompute the shared state once, then hand each subscriber their view of it. */
    private function tick(): void
    {
        if ($this->authedCount() === 0) {
            // Nobody is listening. Skip the work entirely rather than warming a
            // cache for an audience that does not exist.
            $this->sharedTick = null;
            return;
        }

        $this->sharedTick = $this->buildShared();

        foreach ($this->meta as $m) {
            if ($m['auth']) {
                $this->pushTo($m['conn']);
            }
        }
    }

    private function pushTo(ConnectionInterface $conn): void
    {
        $id = $conn->resourceId;
        if (!isset($this->meta[$id]) || !$this->meta[$id]['auth']) {
            return;
        }

        $this->send($conn, [
            'type'   => 'tick',
            'bot'    => $this->sharedTick ?? [],
            'wallet' => $this->buildWallet($this->meta[$id]['user_id']),
        ]);
    }

    // ── the data ──────────────────────────────────────────────────────────────

    /**
     * The class bot's state — identical for everyone, so computed once.
     *
     * Marks come from one batched ticker call rather than one call per symbol:
     * the old per-request path asked Binance for each position separately, which
     * was tolerable at one request per poll and is not at one per viewer.
     *
     * @return array<string,mixed>
     */
    private function buildShared(): array
    {
        $out = [
            'running' => false, 'last_run_at' => null, 'last_action' => null,
            'realized' => 0.0, 'unrealized' => 0.0, 'positions' => [], 'thoughts' => [],
        ];
        if ($this->db === null) {
            return $out;
        }

        $open = $this->rows(
            'SELECT id, symbol, qty, price, stop_loss, take_profit, template, profile, created_at
               FROM gtb_trades WHERE mode = ? AND status = ? ORDER BY id DESC',
            ['paper', 'open']
        );

        $marks = $this->marks(array_values(array_unique(array_column($open, 'symbol'))));

        $unreal = 0.0;
        foreach ($open as $p) {
            $entry = (float) $p['price'];
            $qty   = (float) $p['qty'];
            $mark  = (float) ($marks[$p['symbol']] ?? $entry);
            if ($mark <= 0) {
                $mark = $entry;
            }
            $pnl     = ($mark - $entry) * $qty;
            $unreal += $pnl;

            $out['positions'][] = [
                'id' => (int) $p['id'], 'symbol' => $p['symbol'], 'base' => substr((string) $p['symbol'], 0, -4),
                'template' => $p['template'] ?? '', 'profile' => $p['profile'] ?? '',
                'entry' => $entry, 'mark' => $mark, 'qty' => $qty,
                'stop_loss' => (float) $p['stop_loss'],
                'take_profit' => $p['take_profit'] !== null ? (float) $p['take_profit'] : null,
                'pnlPct' => $entry > 0 ? ($mark - $entry) / $entry * 100 : 0,
                'unrealized' => round($pnl, 4), 'opened_at' => $p['created_at'] ?? null,
            ];
        }
        $out['unrealized'] = round($unreal, 4);

        $realized = $this->rows('SELECT COALESCE(SUM(realized_pnl),0) AS t FROM gtb_trades WHERE mode = ? AND status = ?', ['paper', 'closed']);
        $out['realized'] = round((float) ($realized[0]['t'] ?? 0), 4);

        // Oldest first, matching GtbThought::recent(), so the stream reads down
        // the page in the order the bot actually thought it.
        //
        // `decision` is what carries the meaning here. bot.php colours these by
        // a `type` field that this table has never had, so every dot on that page
        // renders the same grey default — worth knowing before copying the markup
        // and inheriting the bug along with it.
        $out['thoughts'] = array_reverse($this->rows(
            'SELECT role, phase, symbol, decision, message, created_at FROM gtb_thoughts ORDER BY id DESC LIMIT 24'
        ));

        $state = $this->rows('SELECT enabled, last_run_at, last_action FROM gtb_bot_state ORDER BY id DESC LIMIT 1');
        if ($state !== []) {
            $out['running']     = !empty($state[0]['enabled']);
            $out['last_run_at'] = $state[0]['last_run_at'] ?? null;
            $out['last_action'] = $state[0]['last_action'] ?? null;
        }

        return $out;
    }

    /**
     * One member's paper wallet and their own open trades.
     *
     * Marked against the same tick's prices as everything else, so a member
     * never sees their position valued at one price while the identical class
     * position on the same screen is valued at another.
     *
     * @return array<string,mixed>
     */
    private function buildWallet(int $userId): array
    {
        $out = ['balance' => 0.0, 'starting' => 0.0, 'equity' => 0.0, 'unrealized' => 0.0,
                'bot_enabled' => false, 'positions' => []];
        if ($this->db === null || $userId <= 0) {
            return $out;
        }

        $w = $this->rows('SELECT balance, start_balance, bot_enabled, halt_date FROM academy_wallets WHERE user_id = ?', [$userId]);
        if ($w === []) {
            return $out;
        }
        $out['balance']     = (float) $w[0]['balance'];
        $out['starting']    = (float) $w[0]['start_balance'];
        $out['bot_enabled'] = !empty($w[0]['bot_enabled']);
        $out['halted']      = ($w[0]['halt_date'] ?? null) === date('Y-m-d');

        $pos = $this->rows(
            'SELECT id, symbol, base, qty, entry, spent, stop_loss, take_profit, ref_trade_id, opened_at
               FROM academy_positions WHERE user_id = ? AND status = ? ORDER BY id DESC',
            [$userId, 'open']
        );

        $marks  = $this->marks(array_values(array_unique(array_column($pos, 'symbol'))));
        $unreal = 0.0;
        $held   = 0.0;

        foreach ($pos as $p) {
            $entry = (float) $p['entry'];
            $qty   = (float) $p['qty'];
            $mark  = (float) ($marks[$p['symbol']] ?? $entry);
            if ($mark <= 0) {
                $mark = $entry;
            }
            $value   = $qty * $mark;
            $pnl     = $value - (float) $p['spent'];
            $unreal += $pnl;
            $held   += $value;

            $out['positions'][] = [
                'id' => (int) $p['id'], 'symbol' => $p['symbol'], 'base' => $p['base'],
                'entry' => $entry, 'mark' => $mark, 'qty' => $qty, 'spent' => (float) $p['spent'],
                'stop_loss' => $p['stop_loss'] !== null ? (float) $p['stop_loss'] : null,
                'take_profit' => $p['take_profit'] !== null ? (float) $p['take_profit'] : null,
                'followed' => $p['ref_trade_id'] !== null,
                'pnlPct' => $entry > 0 ? ($mark - $entry) / $entry * 100 : 0,
                'unrealized' => round($pnl, 4), 'opened_at' => $p['opened_at'] ?? null,
            ];
        }

        $out['unrealized'] = round($unreal, 4);
        $out['equity']     = round($out['balance'] + $held, 4);

        return $out;
    }

    /**
     * Last price for many symbols in one request.
     *
     * @param list<string> $symbols
     * @return array<string,float>
     */
    private function marks(array $symbols): array
    {
        if ($symbols === []) {
            return [];
        }

        $url = 'https://api.binance.com/api/v3/ticker/price?symbols=' . urlencode(json_encode(array_values($symbols)));
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            // Stale marks are better than a broken tick: callers fall back to entry.
            return [];
        }

        $out = [];
        foreach ((array) json_decode((string) $body, true) as $row) {
            if (isset($row['symbol'], $row['price'])) {
                $out[$row['symbol']] = (float) $row['price'];
            }
        }

        return $out;
    }

    /** The movers list, swept once for everyone. */
    private function refreshMarkets(): void
    {
        if ($this->authedCount() === 0) {
            return;
        }

        $ch = curl_init('https://api.binance.com/api/v3/ticker/24hr');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            return;
        }
        $rows = json_decode((string) $body, true);
        if (!is_array($rows)) {
            return;
        }

        // Same exclusions as AcademyController::markets(): stablecoin pairs and
        // leveraged tokens are noise on a learning screen, and thin books make
        // for prices that move for no reason anyone can explain.
        $stable = ['USDC','BUSD','TUSD','FDUSD','DAI','USDP','USTC','EUR','GBP','AEUR','USD1','EURI','XUSD'];
        $items  = [];
        foreach ($rows as $r) {
            $sym = (string) ($r['symbol'] ?? '');
            if (!str_ends_with($sym, 'USDT')) continue;
            $base = substr($sym, 0, -4);
            if ($base === '' || preg_match('/(UP|DOWN|BULL|BEAR)$/', $base) || in_array($base, $stable, true)) continue;
            if ((float) ($r['quoteVolume'] ?? 0) < 5000000.0) continue;

            $items[] = ['symbol' => $sym, 'base' => $base,
                        'price' => (float) ($r['lastPrice'] ?? 0),
                        'changePct' => (float) ($r['priceChangePercent'] ?? 0),
                        'quoteVolume' => (float) ($r['quoteVolume'] ?? 0)];
        }

        $byVolume = $items;
        usort($byVolume, static fn($a, $b) => $b['quoteVolume'] <=> $a['quoteVolume']);
        $byChange = $items;
        usort($byChange, static fn($a, $b) => $b['changePct'] <=> $a['changePct']);

        $this->markets = [
            'popular' => array_slice($byVolume, 0, 40),
            'gainers' => array_slice($byChange, 0, 40),
            'losers'  => array_slice(array_reverse($byChange), 0, 40),
        ];

        foreach ($this->meta as $m) {
            if ($m['auth']) {
                $this->send($m['conn'], ['type' => 'markets'] + $this->markets);
            }
        }
    }

    // ── plumbing ──────────────────────────────────────────────────────────────

    private function authedCount(): int
    {
        $n = 0;
        foreach ($this->meta as $m) {
            if ($m['auth']) $n++;
        }

        return $n;
    }

    /** @param array<string,mixed> $payload */
    private function send(ConnectionInterface $conn, array $payload): void
    {
        try {
            $conn->send(json_encode($payload, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            // A send to a socket the client already dropped is not an error worth
            // logging on every tick.
        }
    }

    /**
     * @param list<mixed> $args
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql, array $args = []): array
    {
        if ($this->db === null) {
            // Lost earlier and not yet recovered — try again now rather than
            // staying dead until someone restarts the process.
            $this->connect();
            if ($this->db === null) {
                return [];
            }
        }

        try {
            return $this->run($sql, $args);
        } catch (\Throwable $e) {
            // A daemon holds one connection for weeks, and MySQL closes it after
            // wait_timeout with "server has gone away". Left unhandled that is
            // the worst kind of failure here: every query starts returning
            // nothing, the stream keeps ticking, and subscribers see an empty
            // portfolio rather than an error — the same fault already visible in
            // this server's log from MessengerServer. So reconnect once and
            // retry before giving up on the query.
            if (!$this->isLostConnection($e)) {
                error_log('[LtradingServer] query failed: ' . $e->getMessage());
                return [];
            }

            error_log('[LtradingServer] database connection lost; reconnecting.');
            $this->db = null;
            $this->connect();
            if ($this->db === null) {
                return [];
            }

            try {
                return $this->run($sql, $args);
            } catch (\Throwable $again) {
                error_log('[LtradingServer] query failed after reconnect: ' . $again->getMessage());
                // One bad query must not kill the loop for everyone else.
                return [];
            }
        }
    }

    /**
     * @param list<mixed> $args
     * @return list<array<string,mixed>>
     */
    private function run(string $sql, array $args): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($args);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Distinguishes a dropped connection from a query that is simply wrong. */
    private function isLostConnection(\Throwable $e): bool
    {
        $message = $e->getMessage();

        // 2006 is the server closing an idle connection, 2013 is losing it
        // mid-query. Retrying anything else would just repeat a real error.
        foreach (['2006', '2013', 'gone away', 'Lost connection', 'Broken pipe'] as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function connect(): void
    {
        try {
            $env = dirname(__DIR__, 2) . '/.env';
            if (is_file($env)) {
                foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    putenv(trim($k) . '=' . trim($v, "\"'"));
                }
            }
            $this->db = new \PDO(
                'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('DB_NAME') ?: 'ginto') . ';charset=utf8mb4',
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            error_log('[LtradingServer] DB init failed: ' . $e->getMessage());
            $this->db = null;
        }
    }
}
