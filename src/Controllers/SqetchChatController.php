<?php

namespace Ginto\Controllers;

use Ginto\Support\Env;

/** Public SQetch chat ingress. Comchain validates each bearer token before the
 * centrally managed provider keys are used. */
class SqetchChatController
{
    public function handle(): void
    {
        header('Cache-Control: no-store');
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authorization === '' && function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $authorization = (string) $value;
                    break;
                }
            }
        }
        if (!preg_match('/^Bearer\s+(sk_sqetch_[A-Za-z0-9]+)$/', $authorization, $match) || strlen($match[1]) > 100) {
            $this->error(401, 'A valid SQetchImage credential is required.');
            return;
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $messages = is_array($input['messages'] ?? null) ? $input['messages'] : [];
        if ($messages === [] || count($messages) > 40) {
            $this->error(400, 'A conversation with up to 40 messages is required.');
            return;
        }
        foreach ($messages as $message) {
            if (!is_array($message) || !in_array(($message['role'] ?? ''), ['system', 'user', 'assistant'], true)
                || !is_string($message['content'] ?? null) || strlen($message['content']) > 20000) {
                $this->error(400, 'The conversation contains an invalid message.');
                return;
            }
        }

        $base = rtrim((string) (Env::get('COMCHAIN_URL', 'https://comchain.silverqueen.pro') ?? ''), '/');
        $check = curl_init($base . '/api/v1/internal/sqetch/authorize');
        curl_setopt_array($check, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $match[1]],
        ]);
        $checkBody = curl_exec($check);
        $checkStatus = (int) curl_getinfo($check, CURLINFO_RESPONSE_CODE);
        $checkError = curl_errno($check);
        curl_close($check);
        if ($checkError !== 0 || $checkStatus === 0) {
            $this->error(503, 'SQetch access validation is unavailable.');
            return;
        }
        if ($checkStatus !== 200) {
            $this->error(401, 'SQetchImage access is inactive or the credential is invalid.');
            return;
        }

        (new ApiKeysRelayController())->streamChat($messages);
    }

    private function error(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['message' => $message]]);
    }
}
