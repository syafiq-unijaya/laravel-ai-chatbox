<?php
namespace SyafiqUnijaya\AiChatbox;

use SyafiqUnijaya\AiChatbox\Engine\AiProvider;
use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;
use SyafiqUnijaya\AiChatbox\Engine\PromptBuilder;

/**
 * Resolves named AI providers from config and returns an AiProvider instance.
 *
 * Named providers are defined under ai-chatbox.providers.{name}.
 * Each entry only needs to specify the keys that differ from the global defaults
 * (api_url, api_token, api_model). All other settings (temperature, system_prompt,
 * language, etc.) are inherited from the top-level ai-chatbox config.
 *
 * The special name 'default' always maps to the top-level config as-is.
 *
 * Calling methods directly on the facade (e.g. AI::chat(...)) is delegated
 * to the 'default' provider via __call().
 *
 * @see \SyafiqUnijaya\AiChatbox\AI
 * @see \SyafiqUnijaya\AiChatbox\Engine\AiProvider
 */
class AiManager
{
    /**
     * Return a fluent AiProvider for the given named provider.
     *
     * @param  string  $name  Provider key from ai-chatbox.providers (or 'default')
     * @throws \InvalidArgumentException  if the provider is not configured
     */
    public function provider(string $name = 'default'): AiProvider
    {
        $config = $this->resolveConfig($name);

        return new AiProvider(
            app(AiEngineInterface::class),
            new PromptBuilder(),
            $config,
        );
    }

    /**
     * Proxy undefined method calls to the default provider.
     *
     * Allows: AI::chat($prompt)  instead of  AI::provider()->chat($prompt)
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->provider('default')->{$method}(...$args);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    public function resolveConfig(string $name): array
    {
        // 'default' uses the top-level ai-chatbox config (full, backward-compatible)
        $base = config('ai-chatbox', []);

        if ($name === 'default') {
            return $base;
        }

        $providers = $base['providers'] ?? [];

        if (!array_key_exists($name, $providers)) {
            throw new \InvalidArgumentException(
                "AI provider [{$name}] is not configured. "
                . "Add it under ai-chatbox.providers in your config."
            );
        }

        // Named providers inherit all base settings; only override what they define
        return array_merge($base, $providers[$name]);
    }
}
