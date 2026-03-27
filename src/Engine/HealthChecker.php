<?php
namespace SyafiqUnijaya\AiChatbox\Engine;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Support\Facades\Log;

class HealthChecker
{
    private const E01 = 'E01'; // api_url missing
    private const E02 = 'E02'; // api_url has no host
    private const E05 = 'E05'; // SSRF — private/reserved IP
    private const E06 = 'E06'; // DNS resolution failed
    private const E07 = 'E07'; // Connection refused
    private const E08 = 'E08'; // Connection timed out
    private const E09 = 'E09'; // SSL/TLS error
    private const E10 = 'E10'; // Too many redirects
    private const E11 = 'E11'; // Generic connect error
    private const E19 = 'E19'; // Unknown error

    /**
     * Ping the configured AI service base URL.
     *
     * @param  array<string, mixed>  $cfg  Package config array
     * @return array{status: string, code?: string, message: string}
     */
    public function check(array $cfg): array
    {
        $apiUrl = $cfg['api_url'] ?? '';
        $offlineMessage = $cfg['offline_message'] ?? 'AI service is currently unreachable.';
        $timeout = min((int) ($cfg['timeout'] ?? 30), 5);
        $ssrf = $cfg['ssrf_protection'] ?? true;

        if (empty($apiUrl)) {
            return $this->offline(self::E01, $offlineMessage);
        }

        $parts = parse_url($apiUrl);
        $host = $parts['host'] ?? '';

        if (empty($host)) {
            return $this->offline(self::E02, $offlineMessage);
        }

        if ($ssrf) {
            $ip = $this->resolveHost($host);

            if ($ip === null) {
                return $this->offline(self::E06, $offlineMessage);
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return $this->offline(self::E05, $offlineMessage);
            }
        }

        $baseUrl = ($parts['scheme'] ?? 'http') . '://' . $host;
        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }

        try {
            $client = app('ai-chatbox.guzzle')(['timeout' => $timeout, 'connect_timeout' => $timeout]);
            $client->get($baseUrl, ['http_errors' => false]);

            return ['status' => 'online', 'message' => 'AI service is reachable.'];

        } catch (TooManyRedirectsException $e) {
            return $this->offline(self::E10, $offlineMessage, $e);
        } catch (ConnectException $e) {
            return $this->offline($this->classifyConnect($e), $offlineMessage, $e);
        } catch (RequestException) {
            // Any HTTP response (even 4xx/5xx) means the server is up
            return ['status' => 'online', 'message' => 'AI service is reachable.'];
        } catch (\Throwable $e) {
            return $this->offline(self::E19, $offlineMessage, $e);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function offline(string $code, string $message,  ? \Throwable $e = null) : array
    {
        Log::warning('AI Chatbox health check failed', [
            'code' => $code,
            'message' => $e?->getMessage(),
        ]);

        return ['status' => 'offline', 'code' => $code, 'message' => $message];
    }

    /**
     * Resolve a hostname to an IP with a simple in-process DNS cache.
     * Returns null when resolution fails.
     */
    private function resolveHost(string $host): ?string
    {
        static $cache = [];

        if (isset($cache[$host])) {
            return $cache[$host];
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $cache[$host] = $host;
        }

        $ip = gethostbyname($host);

        // gethostbyname returns the original string unchanged on failure
        if ($ip === $host) {
            return null;
        }

        return $cache[$host] = $ip;
    }

    private function classifyConnect(ConnectException $e): string
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'could not resolve host') || str_contains($msg, 'name or service not known')) {
            return self::E06;
        }
        if (str_contains($msg, 'connection refused')) {
            return self::E07;
        }
        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return self::E08;
        }
        if (str_contains($msg, 'ssl') || str_contains($msg, 'certificate') || str_contains($msg, 'tls')) {
            return self::E09;
        }

        return self::E11;
    }
}
