<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SyafiqUnijaya\AiChatbox\Memory\ContextManager;

class ContextManagerTest extends TestCase
{
    private ContextManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ContextManager();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Build a history array of $pairs user + assistant pairs. */
    private function makeHistory(int $pairs, int $contentLength = 10): array
    {
        $history = [];
        for ($i = 1; $i <= $pairs; $i++) {
            $history[] = ['role' => 'user',      'content' => str_repeat("u{$i}", $contentLength)];
            $history[] = ['role' => 'assistant', 'content' => str_repeat("a{$i}", $contentLength)];
        }
        return $history;
    }

    private function cfg(array $overrides = []): array
    {
        return array_merge([
            'history_limit'       => 50,
            'context_token_limit' => 4000,
            'language'            => 'English',
            'system_prompt'       => 'You are helpful. Reply in {language}.',
        ], $overrides);
    }

    // ── Returns unchanged history when under all limits ───────────────────────

    public function test_returns_history_unchanged_when_under_limits(): void
    {
        $history = $this->makeHistory(3);
        $result  = $this->manager->trim($history, [], 'hello', $this->cfg());

        $this->assertSame($history, $result);
    }

    public function test_returns_empty_array_for_empty_history(): void
    {
        $result = $this->manager->trim([], [], 'hello', $this->cfg());

        $this->assertSame([], $result);
    }

    // ── Pair-count limit ──────────────────────────────────────────────────────

    public function test_trims_by_pair_count_keeping_newest_pairs(): void
    {
        $history = $this->makeHistory(5); // 5 pairs = 10 entries
        $result  = $this->manager->trim($history, [], 'hi', $this->cfg(['history_limit' => 3]));

        // Should keep the 3 newest pairs = 6 entries
        $this->assertCount(6, $result);
        $this->assertSame('u3u3u3u3u3u3u3u3u3u3', $result[0]['content']); // oldest kept pair
    }

    public function test_pair_count_limit_of_one_keeps_only_last_pair(): void
    {
        $history = $this->makeHistory(4);
        $result  = $this->manager->trim($history, [], 'hi', $this->cfg(['history_limit' => 1]));

        $this->assertCount(2, $result);
        $this->assertSame('user',      $result[0]['role']);
        $this->assertSame('assistant', $result[1]['role']);
    }

    public function test_does_not_trim_when_exactly_at_pair_limit(): void
    {
        $history = $this->makeHistory(3);
        $result  = $this->manager->trim($history, [], 'hi', $this->cfg(['history_limit' => 3]));

        $this->assertCount(6, $result);
    }

    // ── Token-count limit ─────────────────────────────────────────────────────

    public function test_trims_by_token_count_when_over_limit(): void
    {
        // Very tight limit — 20 tokens ≈ 80 chars
        $cfg = $this->cfg(['context_token_limit' => 20, 'history_limit' => 100]);

        $history = [
            ['role' => 'user',      'content' => str_repeat('a', 100)], // ~25 tokens alone
            ['role' => 'assistant', 'content' => str_repeat('b', 100)],
            ['role' => 'user',      'content' => 'short'],
            ['role' => 'assistant', 'content' => 'short'],
        ];

        $result = $this->manager->trim($history, [], 'hi', $cfg);

        // The long pair should be dropped; only the short pair survives
        $this->assertCount(2, $result);
        $this->assertSame('short', $result[0]['content']);
    }

    public function test_token_trimming_disabled_when_limit_is_zero(): void
    {
        $cfg = $this->cfg(['context_token_limit' => 0, 'history_limit' => 100]);

        // Very long history that would normally exceed any reasonable token limit
        $history = [
            ['role' => 'user',      'content' => str_repeat('x', 5000)],
            ['role' => 'assistant', 'content' => str_repeat('y', 5000)],
        ];

        $result = $this->manager->trim($history, [], 'hi', $cfg);

        // With limit=0 the history is returned as-is
        $this->assertCount(2, $result);
    }

    public function test_keeps_history_when_within_token_limit(): void
    {
        // 400 tokens = 1600 chars total budget; history is tiny
        $cfg     = $this->cfg(['context_token_limit' => 400]);
        $history = $this->makeHistory(2, 5); // very short content

        $result = $this->manager->trim($history, [], 'hi', $cfg);

        $this->assertCount(4, $result); // all 2 pairs retained
    }

    // ── System messages are included in token budget ──────────────────────────

    public function test_system_messages_count_against_token_budget(): void
    {
        $bigSystem = [['role' => 'system', 'content' => str_repeat('s', 200)]]; // 50 tokens
        $cfg       = $this->cfg(['context_token_limit' => 55]); // barely fits system + user

        $history = [
            ['role' => 'user',      'content' => str_repeat('h', 100)], // 25 tokens
            ['role' => 'assistant', 'content' => str_repeat('r', 100)], // 25 tokens
        ];

        // system(50) + history(50) + user("hi"=1) = 101 > 55 → history gets dropped
        $result = $this->manager->trim($history, $bigSystem, 'hi', $cfg);

        $this->assertCount(0, $result);
    }

    // ── Pair count + token count both apply ───────────────────────────────────

    public function test_applies_pair_limit_before_token_limit(): void
    {
        // 10 pairs in history; pair limit = 4; token limit very large
        $history = $this->makeHistory(10);
        $cfg     = $this->cfg(['history_limit' => 4, 'context_token_limit' => 99999]);

        $result = $this->manager->trim($history, [], 'hi', $cfg);

        $this->assertCount(8, $result); // 4 pairs × 2 entries
    }
}
