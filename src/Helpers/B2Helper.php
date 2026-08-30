<?php
/**
 * B2Helper — Backblaze B2 upload helper for product images.
 *
 * Active when DATACENTER=b2b and all required B2 env vars are set.
 * upload() returns the public CDN URL for storing in the DB.
 */
namespace Ginto\Helpers;

class B2Helper
{
    /**
     * Returns true when Backblaze B2 is configured and enabled (DATACENTER=b2b).
     */
    public static function isEnabled(): bool
    {
        $dc = $_ENV['DATACENTER'] ?? getenv('DATACENTER') ?? '';
        return strtolower(trim($dc)) === 'b2b'
            && !empty($_ENV['B2_ACCOUNT_ID']  ?? getenv('B2_ACCOUNT_ID'))
            && !empty($_ENV['B2_APP_KEY']     ?? getenv('B2_APP_KEY'))
            && !empty($_ENV['B2_BUCKET_ID']   ?? getenv('B2_BUCKET_ID'))
            && !empty($_ENV['FILE_CDN_BASE_URL'] ?? getenv('FILE_CDN_BASE_URL'));
    }

    /**
     * Upload a file from disk, without ever holding it in memory.
     *
     * upload() takes the bytes as a string, which is right for a product image
     * and impossible for a broadcast recording: an hour of video is about
     * 900 MB, and strlen()/sha1() on that means the whole file sits in the
     * process twice over. Four hours would not fit at all.
     *
     * So this one streams. sha1_file() reads in chunks, and cURL is handed a
     * file handle rather than a body — the bytes go from disk to socket and
     * never through PHP.
     *
     * There is no absolute timeout, deliberately. A three-gigabyte upload over
     * a link we do not control can legitimately take an hour, and a timeout
     * long enough to allow that is no protection at all. The low-speed guard
     * is the real one: if throughput stays under 1 KB/s for two minutes the
     * transfer is dead, whatever the clock says.
     *
     * @param  string $localPath   A readable file on this machine.
     * @param  string $remotePath  Path inside the bucket, e.g. "recordings/abc.mp4".
     * @param  string $contentType MIME type.
     * @return string              Full CDN URL.
     * @throws \Exception          On any B2 API failure.
     */
    public static function uploadFile(string $localPath, string $remotePath, string $contentType): string
    {
        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new \Exception('B2 upload: no readable file at ' . basename($localPath));
        }

        $fileSize = (int) filesize($localPath);

        if ($fileSize <= 0) {
            throw new \Exception('B2 upload: that file is empty');
        }

        // B2 takes 5 GB in one upload; past that it wants the large-file API,
        // which is a different three calls again. Refused loudly rather than
        // truncated — a half-uploaded recording that reports success is worse
        // than one that plainly failed.
        if ($fileSize > 4_800_000_000) {
            throw new \Exception(sprintf('B2 upload: %.1f GB exceeds the single-upload ceiling', $fileSize / 1e9));
        }

        [$apiUrl, $authToken] = self::authorize();
        [$uploadUrl, $uploadAuthToken] = self::uploadTarget($apiUrl, $authToken);

        $handle = fopen($localPath, 'rb');

        if ($handle === false) {
            throw new \Exception('B2 upload: could not open the file');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $uploadUrl,
            // UPLOAD makes cURL stream from INFILE; CUSTOMREQUEST keeps it a
            // POST, which is what B2 expects — UPLOAD alone would make it PUT.
            CURLOPT_UPLOAD         => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_INFILE         => $handle,
            CURLOPT_INFILESIZE     => $fileSize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_LOW_SPEED_LIMIT => 1024,
            CURLOPT_LOW_SPEED_TIME  => 120,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $uploadAuthToken,
                'X-Bz-File-Name: ' . rawurlencode($remotePath),
                'Content-Type: ' . $contentType,
                'X-Bz-Content-Sha1: ' . sha1_file($localPath),
                'Content-Length: ' . $fileSize,
            ],
        ]);

        $respJson  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErr   = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($curlErrNo !== 0) {
            throw new \Exception("B2 upload cURL error ({$curlErrNo}): {$curlErr}");
        }
        if ($httpCode !== 200) {
            $resp = json_decode((string) $respJson, true);
            $msg  = is_array($resp) ? ($resp['message'] ?? $respJson) : $respJson;
            throw new \Exception("B2 upload HTTP {$httpCode}: " . substr((string) $msg, 0, 200));
        }

        $cdnBase = rtrim($_ENV['FILE_CDN_BASE_URL'] ?? getenv('FILE_CDN_BASE_URL'), '/');

        return $cdnBase . '/' . $remotePath;
    }

    /**
     * Authorise, and learn where this account's API lives.
     *
     * @return array{0:string,1:string} apiUrl, authorizationToken
     */
    private static function authorize(): array
    {
        $accountId = $_ENV['B2_ACCOUNT_ID'] ?? getenv('B2_ACCOUNT_ID');
        $appKey    = $_ENV['B2_APP_KEY']    ?? getenv('B2_APP_KEY');

        $ctx = stream_context_create(['http' => [
            'method'        => 'GET',
            'header'        => 'Authorization: Basic ' . base64_encode($accountId . ':' . $appKey) . "\r\n",
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);

        $json = @file_get_contents('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', false, $ctx);

        if ($json === false) {
            throw new \Exception('B2 auth: network error');
        }

        $auth = json_decode($json, true);

        if (!is_array($auth) || empty($auth['apiUrl']) || empty($auth['authorizationToken'])) {
            throw new \Exception('B2 auth: invalid response — ' . substr($json, 0, 200));
        }

        return [$auth['apiUrl'], $auth['authorizationToken']];
    }

    /**
     * @return array{0:string,1:string} uploadUrl, authorizationToken
     */
    private static function uploadTarget(string $apiUrl, string $authToken): array
    {
        $bucketId = $_ENV['B2_BUCKET_ID'] ?? getenv('B2_BUCKET_ID');
        $payload  = json_encode(['bucketId' => $bucketId]);

        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Authorization: {$authToken}\r\nContent-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content'       => $payload,
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);

        $json = @file_get_contents($apiUrl . '/b2api/v2/b2_get_upload_url', false, $ctx);

        if ($json === false) {
            throw new \Exception('B2 get_upload_url: network error');
        }

        $resp = json_decode($json, true);

        if (!is_array($resp) || empty($resp['uploadUrl']) || empty($resp['authorizationToken'])) {
            throw new \Exception('B2 get_upload_url: invalid response — ' . substr($json, 0, 200));
        }

        return [$resp['uploadUrl'], $resp['authorizationToken']];
    }

    /**
     * Upload raw file data to B2 and return the full public CDN URL.
     *
     * @param string $fileData     Raw binary file contents
     * @param string $remotePath   Path inside the bucket, e.g. "mall/images/abc.jpg"
     * @param string $contentType  MIME type, e.g. "image/jpeg"
     * @return string              Full CDN URL (FILE_CDN_BASE_URL + '/' + remotePath)
     * @throws \Exception          On any B2 API failure
     */
    public static function upload(string $fileData, string $remotePath, string $contentType): string
    {
        $accountId  = $_ENV['B2_ACCOUNT_ID']     ?? getenv('B2_ACCOUNT_ID');
        $appKey     = $_ENV['B2_APP_KEY']         ?? getenv('B2_APP_KEY');
        $bucketId   = $_ENV['B2_BUCKET_ID']       ?? getenv('B2_BUCKET_ID');
        $cdnBase    = rtrim($_ENV['FILE_CDN_BASE_URL'] ?? getenv('FILE_CDN_BASE_URL'), '/');
        $sha1       = sha1($fileData);
        $fileSize   = strlen($fileData);

        // 1. Authorize account
        $authHeader  = 'Authorization: Basic ' . base64_encode($accountId . ':' . $appKey);
        $authCtx     = stream_context_create(['http' => [
            'method'        => 'GET',
            'header'        => $authHeader . "\r\n",
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        $authJson = @file_get_contents('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', false, $authCtx);
        if ($authJson === false) {
            throw new \Exception('B2 auth: network error');
        }
        $auth = json_decode($authJson, true);
        if (!is_array($auth) || empty($auth['apiUrl']) || empty($auth['authorizationToken'])) {
            throw new \Exception('B2 auth: invalid response — ' . substr($authJson, 0, 200));
        }
        $apiUrl    = $auth['apiUrl'];
        $authToken = $auth['authorizationToken'];

        // 2. Get upload URL
        $payload   = json_encode(['bucketId' => $bucketId]);
        $upCtx     = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Authorization: {$authToken}\r\nContent-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content'       => $payload,
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        $upJson = @file_get_contents($apiUrl . '/b2api/v2/b2_get_upload_url', false, $upCtx);
        if ($upJson === false) {
            throw new \Exception('B2 get_upload_url: network error');
        }
        $upResp = json_decode($upJson, true);
        if (!is_array($upResp) || empty($upResp['uploadUrl']) || empty($upResp['authorizationToken'])) {
            throw new \Exception('B2 get_upload_url: invalid response — ' . substr($upJson, 0, 200));
        }
        $uploadUrl       = $upResp['uploadUrl'];
        $uploadAuthToken = $upResp['authorizationToken'];

        // 3. Upload file via cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $uploadUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fileData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $uploadAuthToken,
                'X-Bz-File-Name: ' . rawurlencode($remotePath),
                'Content-Type: ' . $contentType,
                'X-Bz-Content-Sha1: ' . $sha1,
                'Content-Length: ' . $fileSize,
            ],
        ]);
        $respJson  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErr   = curl_error($ch);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            throw new \Exception("B2 upload cURL error ({$curlErrNo}): {$curlErr}");
        }
        if ($httpCode !== 200) {
            $resp = json_decode($respJson, true);
            $msg  = is_array($resp) ? ($resp['message'] ?? $respJson) : $respJson;
            throw new \Exception("B2 upload HTTP {$httpCode}: " . substr($msg, 0, 200));
        }

        return $cdnBase . '/' . $remotePath;
    }
}
