<?php
namespace Ginto\Services;

/**
 * On-chain verification of USDT (BEP20) transfers, using the public BNB Smart Chain
 * JSON-RPC nodes. No API key and no third-party account — the same endpoints wallets
 * use, queried with failover.
 *
 * Only three RPC methods are needed, all cheap and all keyless:
 *   eth_getTransactionReceipt  — status + the transaction's own logs
 *   eth_getTransactionByHash   — tells "unknown hash" apart from "still in mempool"
 *   eth_blockNumber            — to count confirmations
 *
 * Deliberately NOT eth_getLogs: public nodes rate-limit range scans ("limit exceeded"),
 * and it isn't needed — a receipt already carries that transaction's Transfer events.
 *
 * A transfer counts as good only when all of these hold:
 *   - the transaction succeeded (receipt status 0x1)
 *   - it contains Transfer event(s) emitted by the USDT contract
 *   - paying TO our wallet (any sender — buyers pay from exchanges and custodial wallets)
 *   - summing to at least the invoiced amount
 *   - buried under enough confirmations to be safe from a reorg
 */
class UsdtBep20Verifier
{
    /** keccak256("Transfer(address,address,uint256)") */
    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /** Binance-Peg USDT on BSC. Unlike Ethereum's 6-decimal USDT, this one has 18. */
    private const DEFAULT_CONTRACT = '0x55d398326f99059ff775485246999027b3197955';
    private const DEFAULT_DECIMALS = 18;

    /** ~3s blocks, so 12 confirmations is roughly 36 seconds. */
    private const DEFAULT_MIN_CONFIRMATIONS = 12;

    /** Keyless public endpoints, tried in order until one answers. */
    private const RPC_ENDPOINTS = [
        'https://bsc-dataseed.binance.org/',
        'https://bsc-dataseed1.defibit.io/',
        'https://bsc-dataseed1.ninicoin.io/',
        'https://bsc.publicnode.com',
    ];

    /** Tolerance on the paid amount, to absorb rounding in the sender's wallet. */
    private const AMOUNT_TOLERANCE = 0.01;

    private string $contract;
    private int $decimals;
    private int $minConfirmations;
    private array $endpoints;

    public function __construct()
    {
        $this->contract = strtolower(trim((string) (getenv('SQ_USDT_CONTRACT') ?: self::DEFAULT_CONTRACT)));
        $this->decimals = (int) (getenv('SQ_USDT_DECIMALS') ?: self::DEFAULT_DECIMALS);

        $conf = (int) (getenv('SQ_MIN_CONFIRMATIONS') ?: self::DEFAULT_MIN_CONFIRMATIONS);
        $this->minConfirmations = max(1, $conf);

        $custom = trim((string) (getenv('SQ_BSC_RPC') ?: ''));
        $this->endpoints = $custom !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $custom))))
            : self::RPC_ENDPOINTS;
    }

    public function minConfirmations(): int { return $this->minConfirmations; }
    public function contract(): string { return $this->contract; }

    /**
     * Check one transaction hash against an expected destination and amount.
     *
     * Verdicts:
     *   confirmed   — paid in full and buried deep enough; safe to grant
     *   pending     — real and correct, but not enough confirmations yet; check again
     *   not_found   — the chain has never heard of this hash
     *   failed      — the transaction reverted on-chain
     *   mismatch    — succeeded, but didn't pay us (wrong token/recipient, or short)
     *   unavailable — every RPC endpoint failed; decide nothing, retry later
     */
    public function verify(string $txHash, string $expectedTo, float $expectedAmount): array
    {
        $txHash     = strtolower(trim($txHash));
        $expectedTo = strtolower(trim($expectedTo));

        if (!preg_match('/^0x[0-9a-f]{64}$/', $txHash)) {
            return $this->result('mismatch', 0, null, null, null, 'Not a valid transaction hash.');
        }
        if (!preg_match('/^0x[0-9a-f]{40}$/', $expectedTo)) {
            return $this->result('unavailable', 0, null, null, null, 'Destination wallet is not configured correctly.');
        }

        $receipt = $this->rpc('eth_getTransactionReceipt', [$txHash]);
        if (!$receipt['ok']) {
            return $this->result('unavailable', 0, null, null, null, 'Could not reach any BSC node.');
        }

        // No receipt: either still in the mempool, or the hash is fiction.
        if (!is_array($receipt['result'] ?? null)) {
            $tx = $this->rpc('eth_getTransactionByHash', [$txHash]);
            if (!$tx['ok']) {
                return $this->result('unavailable', 0, null, null, null, 'Could not reach any BSC node.');
            }
            return is_array($tx['result'] ?? null)
                ? $this->result('pending', 0, null, null, null, 'Broadcast but not yet mined.')
                : $this->result('not_found', 0, null, null, null, 'No such transaction on BNB Smart Chain.');
        }

        $r = $receipt['result'];
        if (strtolower((string) ($r['status'] ?? '')) !== '0x1') {
            return $this->result('failed', 0, null, null, null, 'The transaction reverted on-chain.');
        }

        // Sum every USDT Transfer in this transaction that lands on our wallet. A
        // router or batched send can emit several; together they settle the invoice.
        $paid = 0.0;
        $from = null;
        foreach ((array) ($r['logs'] ?? []) as $log) {
            if (strtolower((string) ($log['address'] ?? '')) !== $this->contract) continue;
            $topics = (array) ($log['topics'] ?? []);
            if (count($topics) < 3) continue;
            if (strtolower((string) $topics[0]) !== self::TRANSFER_TOPIC) continue;

            $recipient = '0x' . strtolower(substr((string) $topics[2], -40));
            if ($recipient !== $expectedTo) continue;

            $paid += $this->fromWei((string) ($log['data'] ?? '0x0'));
            if ($from === null) $from = '0x' . strtolower(substr((string) $topics[1], -40));
        }

        if ($paid <= 0.0) {
            return $this->result('mismatch', 0, $from, null, 0.0,
                'This transaction sent no USDT to the SilverQueen wallet.');
        }
        if ($paid + self::AMOUNT_TOLERANCE < $expectedAmount) {
            return $this->result('mismatch', 0, $from, $expectedTo, $paid,
                sprintf('Underpaid: %s USDT received against %s USDT invoiced.',
                    rtrim(rtrim(number_format($paid, 8, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($expectedAmount, 8, '.', ''), '0'), '.')));
        }

        // Deep enough to be safe from a reorg?
        $head = $this->rpc('eth_blockNumber', []);
        if (!$head['ok'] || !is_string($head['result'] ?? null)) {
            return $this->result('unavailable', 0, $from, $expectedTo, $paid, 'Could not read the chain head.');
        }
        $headNum  = (int) hexdec((string) $head['result']);
        $blockNum = (int) hexdec((string) ($r['blockNumber'] ?? '0x0'));
        $confirmations = ($headNum > 0 && $blockNum > 0) ? max(0, $headNum - $blockNum + 1) : 0;

        if ($confirmations < $this->minConfirmations) {
            return $this->result('pending', $confirmations, $from, $expectedTo, $paid,
                sprintf('Received — waiting for confirmations (%d/%d).', $confirmations, $this->minConfirmations));
        }

        return $this->result('confirmed', $confirmations, $from, $expectedTo, $paid,
            sprintf('Verified: %s USDT received, %d confirmations.',
                rtrim(rtrim(number_format($paid, 8, '.', ''), '0'), '.'), $confirmations));
    }

    private function result(string $verdict, int $confirmations, ?string $from, ?string $to, ?float $amount, string $note): array
    {
        return [
            'ok'            => in_array($verdict, ['confirmed', 'pending'], true),
            'verdict'       => $verdict,
            'confirmations' => $confirmations,
            'from'          => $from,
            'to'            => $to,
            'amount'        => $amount !== null ? round($amount, 8) : null,
            'note'          => $note,
            'checked_at'    => date('c'),
        ];
    }

    /**
     * A 256-bit token amount is far too large for an int and loses precision as a
     * float, so scale the hex string down with bcmath when it's available.
     */
    private function fromWei(string $hex): float
    {
        $hex = ltrim(strtolower(trim($hex)), '0');
        if (strpos($hex, 'x') === 0) $hex = substr($hex, 1);
        $hex = ltrim($hex, '0');
        if ($hex === '') return 0.0;

        if (function_exists('bcdiv') && function_exists('bcadd')) {
            $dec = '0';
            foreach (str_split($hex) as $c) {
                $dec = bcadd(bcmul($dec, '16'), (string) hexdec($c));
            }
            return (float) bcdiv($dec, bcpow('10', (string) $this->decimals), 8);
        }
        return hexdec($hex) / pow(10, $this->decimals);
    }

    /** One JSON-RPC call, walking the endpoint list until something answers. */
    private function rpc(string $method, array $params): array
    {
        $payload = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);

        foreach ($this->endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $body  = curl_exec($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            curl_close($ch);

            if ($body === false || $errno || $code < 200 || $code >= 300) continue;

            $data = json_decode((string) $body, true);
            if (!is_array($data)) continue;
            // A node-level error (rate limit, etc.) means try the next endpoint.
            if (isset($data['error'])) continue;
            if (!array_key_exists('result', $data)) continue;

            return ['ok' => true, 'result' => $data['result']];
        }

        error_log('SilverQueen UsdtBep20Verifier: all RPC endpoints failed for ' . $method);
        return ['ok' => false, 'result' => null];
    }
}
