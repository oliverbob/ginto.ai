<?php
namespace Ginto\Controllers;

use Ginto\Controllers\AcademyController;
use Ginto\Support\RelayAuth;
use Ginto\Support\RelayAuthError;

/**
 * The service-to-service entry point for callers that cannot hold a session
 * cookie — SilverQueen behind the gntl tunnel, principally.
 *
 * Everything under /api/v1/relay identifies its member with a signed token
 * rather than a browser session, and RelayAuth is the only thing standing in
 * front of it. Handlers here should do nothing before calling member(), and
 * nothing at all if it throws.
 */
class RelayController
{
    /**
     * GET /api/v1/relay/session — who this token is for and what it may reach.
     *
     * Deliberately the cheapest possible endpoint: no Binance call, no writes.
     * It exists so a caller can prove its clock, its secret and its subscription
     * are all good before anything expensive or irreversible depends on them,
     * and so a failing integration can be diagnosed without placing an order.
     */
    public function session(): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $this->json([
            'ok'       => true,
            'username' => $member['username'],
            'user_id'  => (int) $member['user']['id'],
            'fullname' => (string) ($member['user']['fullname'] ?? ''),
            'plan'     => $member['plan'],
            'is_pro'   => $member['is_pro'],
            // What this member is entitled to, so the caller can render its UI
            // from one answer instead of guessing from the plan name.
            'can'      => [
                'paper_trade' => true,               // every active member
                'bot'         => $member['is_pro'],  // automated strategies are Pro
            ],
            'expires_at' => (int) $member['claims']['exp'],
            'server_time' => time(),
        ]);
    }

    /**
     * POST /api/v1/relay/trade/buy — open a paper position for the token's member.
     *
     * The rules — minimum size, available balance, the stop and target applied
     * to every entry — are AcademyController's, called rather than copied. A
     * second implementation of what a buy means is a second set of guardrails
     * to forget to update.
     */
    public function tradeBuy(): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $in = $this->body();
        try {
            $this->json((new AcademyController())->placePaperBuy(
                (int) $member['user']['id'],
                (string) ($in['symbol'] ?? ''),
                (float) ($in['amount'] ?? 0),
                'manual'
            ));
        } catch (\Throwable $e) {
            error_log('RelayController tradeBuy: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Could not place the trade.'], 500);
        }
    }

    /** POST /api/v1/relay/trade/sell — close a manual paper position, by id or symbol. */
    public function tradeSell(): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $in = $this->body();
        try {
            $this->json((new AcademyController())->closePaperPositions(
                (int) $member['user']['id'],
                (int) ($in['id'] ?? 0),
                (string) ($in['symbol'] ?? '')
            ));
        } catch (\Throwable $e) {
            error_log('RelayController tradeSell: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Could not close the trade.'], 500);
        }
    }

    /**
     * POST /api/v1/relay/analyze — the AI's read on one coin, or on the market.
     *
     * Advisory. The one exception is deliberate and belongs to the member, not
     * to this endpoint: for a Pro member with the bot armed, a BUY verdict opens
     * a paper trade, exactly as it does on /academy/bot. Same brain, same
     * per-member cooldown, so the relay cannot be used to get a second helping
     * of a shared AI budget.
     */
    public function analyze(): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $in  = $this->body();
        try {
            $out = (new AcademyController())->runAnalysis(
                (int) $member['user']['id'],
                (string) ($in['symbol'] ?? 'BTCUSDT'),
                (string) ($in['scope'] ?? 'coin')
            );
            $status = !empty($out['rate_limited']) ? 429 : 200;
            unset($out['rate_limited']);
            $this->json($out, $status);
        } catch (\Throwable $e) {
            error_log('RelayController analyze: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'The analysis engine is busy — try again in a moment.'], 500);
        }
    }

    /** @return array<string,mixed> */
    private function body(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);

        return is_array($raw) ? $raw : [];
    }

    /**
     * Resolve the caller, or emit the error response and return null.
     *
     * Returning null rather than throwing keeps each handler to a two-line
     * preamble, and means a handler that forgets the null check fails on a
     * missing array key instead of quietly serving an unauthenticated request.
     *
     * @return array{user:array<string,mixed>,username:string,plan:string,is_pro:bool,claims:array<string,mixed>}|null
     */
    protected function member(): ?array
    {
        try {
            return RelayAuth::authenticate();
        } catch (RelayAuthError $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], $e->status());
            return null;
        } catch (\Throwable $e) {
            error_log('RelayController: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Relay unavailable.'], 500);
            return null;
        }
    }

    /** @param array<string,mixed> $payload */
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // A token-authenticated answer is per-caller and must never be reused
        // by a shared cache sitting between here and the tunnel.
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
