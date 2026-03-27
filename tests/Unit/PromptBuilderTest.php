<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SyafiqUnijaya\AiChatbox\Engine\PromptBuilder;

class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PromptBuilder();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cfg(array $overrides = []): array
    {
        return array_merge([
            'language'       => 'English',
            'system_prompt'  => 'You are helpful. Reply in {language}.',
            'rag_enabled'    => false,
        ], $overrides);
    }

    // ── systemMessages() ─────────────────────────────────────────────────────

    public function test_system_messages_returns_single_entry_with_resolved_language(): void
    {
        $result = $this->builder->systemMessages($this->cfg(['language' => 'French']));

        $this->assertCount(1, $result);
        $this->assertSame('system', $result[0]['role']);
        $this->assertStringContainsString('French', $result[0]['content']);
        $this->assertStringNotContainsString('{language}', $result[0]['content']);
    }

    public function test_system_messages_returns_empty_when_system_prompt_is_blank(): void
    {
        $result = $this->builder->systemMessages($this->cfg(['system_prompt' => '']));

        $this->assertSame([], $result);
    }

    public function test_system_messages_returns_entry_for_whitespace_prompt(): void
    {
        // Whitespace-only prompts are passed through as-is (no trimming),
        // matching the behaviour of the original controller code.
        $result = $this->builder->systemMessages($this->cfg(['system_prompt' => '   ']));

        $this->assertCount(1, $result);
        $this->assertSame('system', $result[0]['role']);
    }

    // ── build() — message order ───────────────────────────────────────────────

    public function test_build_starts_with_system_message(): void
    {
        $messages = $this->builder->build('hi', [], $this->cfg());

        $this->assertSame('system', $messages[0]['role']);
    }

    public function test_build_places_history_after_system_and_before_user(): void
    {
        $history = [
            ['role' => 'user',      'content' => 'prev question'],
            ['role' => 'assistant', 'content' => 'prev answer'],
        ];

        $messages = $this->builder->build('new question', $history, $this->cfg());

        // [system, user-prev, assistant-prev, user-new]
        $this->assertSame('system',    $messages[0]['role']);
        $this->assertSame('user',      $messages[1]['role']);
        $this->assertSame('assistant', $messages[2]['role']);
        $this->assertSame('user',      $messages[3]['role']);
    }

    public function test_build_last_message_is_always_user(): void
    {
        $messages = $this->builder->build('my message', [], $this->cfg());

        $this->assertSame('user', end($messages)['role']);
    }

    public function test_build_user_message_is_last_even_with_history(): void
    {
        $history  = [['role' => 'user', 'content' => 'a'], ['role' => 'assistant', 'content' => 'b']];
        $messages = $this->builder->build('final', $history, $this->cfg());

        // The last message starts with the user's text (language reminder may be appended)
        $this->assertStringStartsWith('final', end($messages)['content']);
    }

    // ── Language reminder ─────────────────────────────────────────────────────

    public function test_build_appends_language_reminder_to_user_message(): void
    {
        $messages = $this->builder->build('Hello', [], $this->cfg(['language' => 'Malay']));
        $userMsg  = end($messages)['content'];

        $this->assertStringContainsString('Hello', $userMsg);
        $this->assertStringContainsString('Malay', $userMsg);
        $this->assertStringContainsString('Reply in', $userMsg);
    }

    public function test_build_does_not_add_reminder_when_system_prompt_is_empty(): void
    {
        $messages = $this->builder->build('Hello', [], $this->cfg(['system_prompt' => '']));
        $userMsg  = end($messages)['content'];

        $this->assertSame('Hello', $userMsg);
    }

    public function test_build_does_not_add_reminder_when_language_is_empty(): void
    {
        $messages = $this->builder->build('Hello', [], $this->cfg(['language' => '']));
        $userMsg  = end($messages)['content'];

        $this->assertSame('Hello', $userMsg);
    }

    // ── History passthrough ───────────────────────────────────────────────────

    public function test_build_includes_all_history_entries(): void
    {
        $history = [
            ['role' => 'user',      'content' => 'msg1'],
            ['role' => 'assistant', 'content' => 'rep1'],
            ['role' => 'user',      'content' => 'msg2'],
            ['role' => 'assistant', 'content' => 'rep2'],
        ];

        $messages = $this->builder->build('msg3', $history, $this->cfg());

        // system + 4 history + user = 6
        $this->assertCount(6, $messages);
    }

    public function test_build_with_no_history_has_only_system_and_user(): void
    {
        $messages = $this->builder->build('hello', [], $this->cfg());

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user',   $messages[1]['role']);
    }

    // ── RAG disabled ──────────────────────────────────────────────────────────

    public function test_build_does_not_inject_rag_context_when_disabled(): void
    {
        $messages = $this->builder->build('hello', [], $this->cfg(['rag_enabled' => false]));

        // With RAG off: system prompt + user message only
        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user',   $messages[1]['role']);
    }

    // ── {language} placeholder in system prompt ───────────────────────────────

    public function test_build_resolves_language_placeholder_in_system_prompt(): void
    {
        $messages = $this->builder->build('hi', [], $this->cfg([
            'language'      => 'Japanese',
            'system_prompt' => 'Reply only in {language}.',
        ]));

        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('Reply only in Japanese.', $messages[0]['content']);
    }

    public function test_system_messages_resolves_language_placeholder(): void
    {
        $result = $this->builder->systemMessages($this->cfg([
            'language'      => 'Arabic',
            'system_prompt' => 'Answer in {language} only.',
        ]));

        $this->assertSame('Answer in Arabic only.', $result[0]['content']);
    }
}
