<?php
namespace SyafiqUnijaya\AiChatbox\Engine;

use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;
use SyafiqUnijaya\AiChatbox\Engine\Exceptions\AiEngineException;

/**
 * Fluent wrapper around an AiEngineInterface instance + a resolved config set.
 *
 * Obtained via AI::provider('name') or AiManager::provider('name').
 *
 * Fluent modifiers (withModel, withTemperature, …) return a cloned instance
 * so the original provider object is never mutated — safe for re-use.
 *
 * Basic usage:
 *   $reply = AI::provider('ollama')->chat('Hello');
 *
 * With modifiers:
 *   $reply = AI::provider('openai')
 *               ->withModel('gpt-4o-mini')
 *               ->withTemperature(0.2)
 *               ->withSystemPrompt('You are a SQL expert.')
 *               ->chat('Write me a query that counts users by country.');
 *
 * Streaming with a callback:
 *   $reply = AI::provider('ollama')->stream('Hello', [], function (string $token) {
 *       echo $token;
 *   });
 *
 * Streaming (beginStream — connect first, read inside response()->stream()):
 *   $reader = AI::provider('ollama')->stream('Hello');
 *   return response()->stream(function () use ($reader) { $reader(fn($t) => print $t); });
 */
class AiProvider
{
    public function __construct(
        private readonly AiEngineInterface $engine,
        private readonly PromptBuilder $promptBuilder,
        private array $config,
    ) {}

    // ── Core methods ──────────────────────────────────────────────────────────

    /**
     * Send a synchronous chat request and return the AI reply.
     *
     * @param  string  $prompt   The user message.
     * @param  array   $history  Previous messages in [{role, content}] format.
     * @return string  The AI reply.
     *
     * @throws AiEngineException
     */
    public function chat(string $prompt, array $history = []): string
    {
        $messages = $this->promptBuilder->build($prompt, $history, $this->config);

        return $this->engine->complete($messages, $this->config);
    }

    /**
     * Stream a chat response.
     *
     * - With $onToken callback: streams immediately and returns the full reply string.
     * - Without $onToken: returns a reader \Closure (signature: callable $onToken → string).
     *   Use this with response()->stream() so connection errors can be caught before
     *   headers are sent.
     *
     * @param  string        $prompt
     * @param  array         $history
     * @param  callable|null $onToken  Called with each streamed token string.
     * @return string|\Closure
     *
     * @throws AiEngineException
     */
    public function stream(string $prompt, array $history = [],  ? callable $onToken = null) : string | \Closure
    {
        $messages = $this->promptBuilder->build($prompt, $history, $this->config);

        if ($onToken !== null) {
            return $this->engine->stream($messages, $this->config, $onToken);
        }

        return $this->engine->beginStream($messages, $this->config);
    }

    // ── Fluent modifiers (all return a clone) ─────────────────────────────────

    /**
     * Override the model name for this call.
     */
    public function withModel(string $model): static
    {
        $clone = clone $this;
        $clone->config['api_model'] = $model;
        return $clone;
    }

    /**
     * Override the system prompt for this call.
     */
    public function withSystemPrompt(string $prompt): static
    {
        $clone = clone $this;
        $clone->config['system_prompt'] = $prompt;
        return $clone;
    }

    /**
     * Override the reply language for this call.
     */
    public function withLanguage(string $language): static
    {
        $clone = clone $this;
        $clone->config['language'] = $language;
        return $clone;
    }

    /**
     * Override the temperature (0.0 – 1.0) for this call.
     */
    public function withTemperature(float $temperature): static
    {
        $clone = clone $this;
        $clone->config['temperature'] = $temperature;
        return $clone;
    }

    /**
     * Override the max_tokens limit for this call.
     */
    public function withMaxTokens(?int $maxTokens): static
    {
        $clone = clone $this;
        $clone->config['max_tokens'] = $maxTokens;
        return $clone;
    }

    /**
     * Override the request timeout (seconds) for this call.
     */
    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->config['timeout'] = $seconds;
        return $clone;
    }

    /**
     * Merge arbitrary config overrides for this call.
     */
    public function withConfig(array $overrides): static
    {
        $clone = clone $this;
        $clone->config = array_merge($clone->config, $overrides);
        return $clone;
    }

    /**
     * Return the resolved config array (useful for debugging).
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
