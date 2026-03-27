<?php
namespace SyafiqUnijaya\AiChatbox;

use Illuminate\Support\Facades\Facade;
use SyafiqUnijaya\AiChatbox\Engine\AiProvider;

/**
 * Facade for the AiManager.
 *
 * Usage:
 *   AI::provider('ollama')->chat('Hello');
 *   AI::provider('openai')->withTemperature(0.9)->chat('Hello');
 *   AI::chat('Hello');           // uses the 'default' provider
 *
 * @method static AiProvider provider(string $name = 'default')
 * @method static string     chat(string $prompt, array $history = [])
 * @method static \Closure   stream(string $prompt, array $history = [])
 *
 * @see \SyafiqUnijaya\AiChatbox\AiManager
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiManager::class;
    }
}
