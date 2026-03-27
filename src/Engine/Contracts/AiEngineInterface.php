<?php
namespace SyafiqUnijaya\AiChatbox\Engine\Contracts;

use SyafiqUnijaya\AiChatbox\Engine\Exceptions\AiEngineException;

interface AiEngineInterface
{
    /**
     * Send a synchronous chat completion request.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  (api_url, api_token, api_model, temperature, etc.)
     * @return string  The AI reply content.
     *
     * @throws AiEngineException
     */
    public function complete(array $messages, array $options = []): string;

    /**
     * Validate that the required config keys (api_url, api_token, api_model) are present
     * and well-formed. Throws AiEngineException before any HTTP request is made.
     * Call this before starting a streamed response so errors can still return proper JSON.
     *
     * @param  array<string, mixed>  $options
     * @throws AiEngineException
     */
    public function validateConfig(array $options): void;

    /**
     * Stream a chat completion token-by-token.
     * Calls $onToken for each token received, then returns the full assembled reply.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @param  callable(string $token): void  $onToken
     * @return string  The full assembled reply.
     *
     * @throws AiEngineException
     */
    public function stream(array $messages, array $options, callable $onToken): string;

    /**
     * Initiate the streaming HTTP connection and return a reader closure.
     *
     * Split into two phases so the controller can handle connection errors
     * before calling response()->stream() — once the HTTP response has started
     * with status 200 it is too late to return a JSON error.
     *
     * The returned closure has signature: (callable $onToken): string
     *   — call it inside response()->stream() to read tokens and get the full reply.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return \Closure(callable(string): void): string
     *
     * @throws AiEngineException  on connection / config errors (before streaming begins)
     */
    public function beginStream(array $messages, array $options): \Closure;
}
