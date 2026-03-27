<?php
namespace SyafiqUnijaya\AiChatbox\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;
use SyafiqUnijaya\AiChatbox\Engine\Exceptions\AiEngineException;

class OpenAiCompatibleEngine implements AiEngineInterface
{
    // ── Error codes ───────────────────────────────────────────────────────────

    public const E01 = 'E01'; // api_url is missing or empty
    public const E03 = 'E03'; // api_token is missing or empty
    public const E04 = 'E04'; // api_model contains invalid characters

    public const E06 = 'E06'; // DNS resolution failed
    public const E07 = 'E07'; // Connection refused
    public const E08 = 'E08'; // Connection timed out
    public const E09 = 'E09'; // SSL/TLS error
    public const E10 = 'E10'; // Too many redirects
    public const E11 = 'E11'; // Generic connection error

    public const E12 = 'E12'; // HTTP 401 Unauthorized
    public const E13 = 'E13'; // HTTP 403 Forbidden
    public const E14 = 'E14'; // HTTP 404 Not Found
    public const E15 = 'E15'; // HTTP 429 Rate limited
    public const E16 = 'E16'; // HTTP 5xx server error
    public const E17 = 'E17'; // Unexpected HTTP status

    public const E18 = 'E18'; // Empty or unparseable response
    public const E19 = 'E19'; // Unknown / unclassified error

    // ── AiEngineInterface ─────────────────────────────────────────────────────

    public function validateConfig(array $options): void
    {
        $this->assertConfig(
            $options['api_url'] ?? '',
            $options['api_token'] ?? '',
            $options['api_model'] ?? ''
        );
    }

    public function complete(array $messages, array $options = []): string
    {
        $apiUrl = $options['api_url'] ?? '';
        $apiToken = $options['api_token'] ?? '';
        $model = $options['api_model'] ?? '';
        $timeout = $options['timeout'] ?? 30;
        $temp = (float) ($options['temperature'] ?? 0.7);
        $maxTokens = $options['max_tokens'] ?? null;

        $this->assertConfig($apiUrl, $apiToken, $model);

        try {
            $client = $this->makeClient(['timeout' => $timeout]);

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => array_filter([
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temp,
                    'max_tokens' => $maxTokens,
                    'stream' => false,
                ], fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $reply = $data['choices'][0]['message']['content'] ?? $data['message']['content'] ?? null;

            if ($reply === null || trim($reply) === '') {
                throw new AiEngineException(self::E18, 'Unable to reach AI service. Please try again later.', 502);
            }

            return trim($reply);

        } catch (AiEngineException $e) {
            throw $e;
        } catch (TooManyRedirectsException $e) {
            throw new AiEngineException(self::E10, 'Unable to reach AI service. Please try again later.', 502, $e);
        } catch (ConnectException $e) {
            throw new AiEngineException($this->classifyConnectException($e), 'Unable to reach AI service. Please try again later.', 503, $e);
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            throw new AiEngineException($this->classifyHttpStatus($status), 'Unable to reach AI service. Please try again later.', $status, $e);
        } catch (\Throwable $e) {
            throw new AiEngineException(self::E19, 'Unable to reach AI service. Please try again later.', 500, $e);
        }
    }

    public function stream(array $messages, array $options, callable $onToken): string
    {
        return ($this->beginStream($messages, $options))($onToken);
    }

    public function beginStream(array $messages, array $options): \Closure
    {
        $apiUrl = $options['api_url'] ?? '';
        $apiToken = $options['api_token'] ?? '';
        $model = $options['api_model'] ?? '';
        $timeout = $options['timeout'] ?? 30;
        $temp = (float) ($options['temperature'] ?? 0.7);

        $this->assertConfig($apiUrl, $apiToken, $model);

        // Establish the connection here so errors can be caught before streaming starts.
        // Any ConnectException / RequestException thrown here will propagate to the caller
        // (the controller) BEFORE response()->stream() is invoked.
        try {
            $client = $this->makeClient(['timeout' => $timeout]);

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => array_filter([
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temp,
                    'stream' => true,
                ], fn($v) => $v !== null),
                'stream' => true,
            ]);

        } catch (TooManyRedirectsException $e) {
            throw new AiEngineException(self::E10, 'Unable to reach AI service. Please try again later.', 502, $e);
        } catch (ConnectException $e) {
            throw new AiEngineException($this->classifyConnectException($e), 'Unable to reach AI service. Please try again later.', 503, $e);
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            throw new AiEngineException($this->classifyHttpStatus($status), 'Unable to reach AI service. Please try again later.', $status, $e);
        } catch (\Throwable $e) {
            throw new AiEngineException(self::E19, 'Unable to reach AI service. Please try again later.', 500, $e);
        }

        $body = $response->getBody();

        // Return a reader closure to be called inside response()->stream()
        return function (callable $onToken) use ($body): string {
            $fullReply = '';
            $buffer = '';

            while (!$body->eof()) {
                $buffer .= $body->read(1024);

                while (($nl = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $nl), "\r");
                    $buffer = substr($buffer, $nl + 1);

                    if ($line === '' || str_starts_with($line, ':')) {
                        continue; // blank keep-alive or SSE comment
                    }

                    if ($line === 'data: [DONE]') {
                        break 2;
                    }

                    // Strip SSE prefix (OpenAI-compatible) or parse raw JSON (Ollama native)
                    $jsonStr = str_starts_with($line, 'data: ') ? substr($line, 6) : $line;
                    $data = json_decode($jsonStr, true);

                    if (!is_array($data)) {
                        continue;
                    }

                    // OpenAI-compatible: choices[0].delta.content
                    // Ollama native:     message.content
                    $token = $data['choices'][0]['delta']['content'] ?? $data['message']['content'] ?? null;

                    if ($token !== null && $token !== '') {
                        $fullReply .= $token;
                        ($onToken)($token);
                    }

                    // Ollama native signals end with done: true
                    if (($data['done'] ?? false) === true) {
                        break 2;
                    }
                }
            }

            return $fullReply;
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertConfig(string $apiUrl, string $apiToken, string $model): void
    {
        if (empty($apiUrl)) {
            throw new AiEngineException(self::E01, 'AI API URL is not configured.', 500);
        }
        if (empty($apiToken)) {
            throw new AiEngineException(self::E03, 'AI API token is not configured.', 500);
        }
        if (!empty($model) && !preg_match('/^[a-zA-Z0-9_:.\-]+$/', $model)) {
            throw new AiEngineException(self::E04, 'Invalid model name configured.', 500);
        }
    }

    protected function makeClient(array $config): Client
    {
        return app('ai-chatbox.guzzle')($config);
    }

    public function classifyConnectException(ConnectException $e): string
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

    public function classifyHttpStatus(int $status): string
    {
        return match ($status) {
            401 => self::E12,
            403 => self::E13,
            404 => self::E14,
            429 => self::E15,
            500, 502, 503, 504 => self::E16,
            default => self::E17,
        };
    }
}
