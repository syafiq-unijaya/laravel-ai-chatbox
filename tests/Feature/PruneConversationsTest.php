<?php

namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SyafiqUnijaya\AiChatbox\Memory\DatabaseConversationRepository;
use SyafiqUnijaya\AiChatbox\Memory\Models\Conversation;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

/**
 * Tests for the ai-chatbox:prune-conversations Artisan command.
 *
 * Covers:
 *  - Pre-flight checks (memory driver, table existence, days validation)
 *  - Happy path deletion with default and custom --days
 *  - Boundary: conversations exactly on the cutoff are not deleted
 *  - Cascade: messages are deleted alongside their conversation
 *  - --dry-run flag: previews without deleting
 *  - --force flag: bypasses the driver check
 *  - Config key: conversation_prune_days is respected
 *  - Zero results: informational message, clean exit
 */
class PruneConversationsTest extends TestCase
{
    use RefreshDatabase;

    // ── Pre-flight checks ─────────────────────────────────────────────────────

    public function test_fails_when_memory_driver_is_not_database(): void
    {
        // Default test setup uses 'session' driver — no database, no migrations needed
        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain("memory_driver is set to 'session', not 'database'")
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fails_when_conversations_table_does_not_exist(): void
    {
        $this->useDatabase();

        // RefreshDatabase creates all tables — drop them to simulate a missing migration
        Schema::dropIfExists('ai_chatbox_messages');
        Schema::dropIfExists('ai_chatbox_conversations');

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain("Table 'ai_chatbox_conversations' does not exist")
            ->expectsOutputToContain('php artisan migrate')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fails_when_days_option_is_zero(): void
    {
        $this->useDatabase();

        $this->artisan('ai-chatbox:prune-conversations', ['--days' => '0'])
            ->expectsOutputToContain('Days must be 1 or greater')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fails_when_days_option_is_negative(): void
    {
        $this->useDatabase();

        $this->artisan('ai-chatbox:prune-conversations', ['--days' => '-5'])
            ->expectsOutputToContain('Days must be 1 or greater')
            ->assertExitCode(Command::FAILURE);
    }

    // ── --force flag ──────────────────────────────────────────────────────────

    public function test_force_flag_bypasses_driver_check_and_warns(): void
    {
        // Run migrations without switching the driver — driver stays 'session' from defineEnvironment
        $this->artisan('migrate');

        // Use Artisan::call() directly; PendingCommand has known issues with boolean flag propagation
        $exitCode = Artisan::call('ai-chatbox:prune-conversations', ['--force' => true]);
        $output   = Artisan::output();

        $this->assertStringContainsString("memory_driver is set to 'session'", $output);
        $this->assertStringContainsString('Running anyway because --force was passed', $output);
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function test_force_flag_with_database_driver_and_migrations_succeeds(): void
    {
        $this->useDatabase();

        // Override driver back to 'session' to confirm --force bypasses the check
        $this->app['config']->set('ai-chatbox.memory_driver', 'session');

        $exitCode = Artisan::call('ai-chatbox:prune-conversations', ['--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Running anyway because --force was passed', Artisan::output());
    }

    // ── Happy path ─────────────────────────────────────────────────────────────

    public function test_deletes_conversations_older_than_default_days(): void
    {
        $this->useDatabase();

        $old   = $this->createConversation('old-thread',    daysAgo: 31);
        $fresh = $this->createConversation('fresh-thread',  daysAgo: 10);

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('1 conversation(s) deleted')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('ai_chatbox_conversations', ['thread_id' => 'old-thread']);
        $this->assertDatabaseHas('ai_chatbox_conversations',    ['thread_id' => 'fresh-thread']);
    }

    public function test_deletes_conversations_older_than_custom_days(): void
    {
        $this->useDatabase();

        $this->createConversation('very-old', daysAgo: 90);
        $this->createConversation('medium',   daysAgo: 45);
        $this->createConversation('recent',   daysAgo: 5);

        $this->artisan('ai-chatbox:prune-conversations', ['--days' => '60'])
            ->expectsOutputToContain('1 conversation(s) deleted')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('ai_chatbox_conversations', ['thread_id' => 'very-old']);
        $this->assertDatabaseHas('ai_chatbox_conversations',    ['thread_id' => 'medium']);
        $this->assertDatabaseHas('ai_chatbox_conversations',    ['thread_id' => 'recent']);
    }

    public function test_deletes_multiple_old_conversations(): void
    {
        $this->useDatabase();

        $this->createConversation('old-1', daysAgo: 60);
        $this->createConversation('old-2', daysAgo: 90);
        $this->createConversation('old-3', daysAgo: 120);
        $this->createConversation('fresh', daysAgo: 5);

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('3 conversation(s) deleted')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('ai_chatbox_conversations', 1);
        $this->assertDatabaseHas('ai_chatbox_conversations', ['thread_id' => 'fresh']);
    }

    // ── Boundary ──────────────────────────────────────────────────────────────

    public function test_does_not_delete_conversation_exactly_at_cutoff(): void
    {
        $this->useDatabase();

        // exactly 30 days ago — should NOT be pruned (cutoff is strictly less than)
        $this->createConversation('boundary', daysAgo: 30);

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('Nothing to delete')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('ai_chatbox_conversations', ['thread_id' => 'boundary']);
    }

    // ── Cascade deletion ──────────────────────────────────────────────────────

    public function test_messages_are_deleted_with_their_conversation(): void
    {
        $this->useDatabase();

        $repo = new DatabaseConversationRepository();
        $repo->saveHistory('old-thread', [
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ]);

        // Age the conversation past the threshold
        DB::table('ai_chatbox_conversations')
            ->where('thread_id', 'old-thread')
            ->update(['updated_at' => now()->subDays(31)->toDateTimeString()]);

        $this->assertDatabaseCount('ai_chatbox_messages', 2);

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('messages removed via cascade')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('ai_chatbox_conversations', 0);
        $this->assertDatabaseCount('ai_chatbox_messages', 0);
    }

    public function test_messages_of_fresh_conversations_are_preserved(): void
    {
        $this->useDatabase();

        $repo = new DatabaseConversationRepository();

        $repo->saveHistory('old-thread', [
            ['role' => 'user', 'content' => 'Old message'],
        ]);
        DB::table('ai_chatbox_conversations')
            ->where('thread_id', 'old-thread')
            ->update(['updated_at' => now()->subDays(60)->toDateTimeString()]);

        $repo->saveHistory('fresh-thread', [
            ['role' => 'user', 'content' => 'Fresh message'],
        ]);

        $this->artisan('ai-chatbox:prune-conversations')->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('ai_chatbox_messages', ['content' => 'Old message']);
        $this->assertDatabaseHas('ai_chatbox_messages',    ['content' => 'Fresh message']);
    }

    // ── --dry-run flag ────────────────────────────────────────────────────────

    public function test_dry_run_reports_count_without_deleting(): void
    {
        $this->useDatabase();

        $this->createConversation('old-1', daysAgo: 40);
        $this->createConversation('old-2', daysAgo: 50);

        // Use Artisan::call() directly; PendingCommand has known issues with boolean flag propagation
        $exitCode = Artisan::call('ai-chatbox:prune-conversations', ['--dry-run' => true]);
        $output   = Artisan::output();

        $this->assertStringContainsString('[Dry run]', $output);
        $this->assertStringContainsString('would be deleted', $output);
        $this->assertSame(Command::SUCCESS, $exitCode);

        // Nothing was actually deleted — this is the key assertion for --dry-run
        $this->assertDatabaseCount('ai_chatbox_conversations', 2);
    }

    public function test_dry_run_with_no_old_conversations_exits_cleanly(): void
    {
        $this->useDatabase();

        $this->createConversation('fresh', daysAgo: 5);

        $exitCode = Artisan::call('ai-chatbox:prune-conversations', ['--dry-run' => true]);

        $this->assertStringContainsString('Nothing to delete', Artisan::output());
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    // ── Config key ────────────────────────────────────────────────────────────

    public function test_uses_conversation_prune_days_config_when_no_option_given(): void
    {
        $this->useDatabase();

        $this->app['config']->set('ai-chatbox.conversation_prune_days', 60);

        $this->createConversation('old-enough', daysAgo: 61); // older than 60 days → pruned
        $this->createConversation('too-fresh',  daysAgo: 31); // older than 30 but not 60 → kept

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('1 conversation(s) deleted')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('ai_chatbox_conversations', ['thread_id' => 'old-enough']);
        $this->assertDatabaseHas('ai_chatbox_conversations',    ['thread_id' => 'too-fresh']);
    }

    public function test_days_option_overrides_config_value(): void
    {
        $this->useDatabase();

        $this->app['config']->set('ai-chatbox.conversation_prune_days', 90);

        $this->createConversation('target', daysAgo: 40);

        // --days=30 should catch the 40-day-old record even though config says 90
        $this->artisan('ai-chatbox:prune-conversations', ['--days' => '30'])
            ->expectsOutputToContain('1 conversation(s) deleted')
            ->assertExitCode(Command::SUCCESS);
    }

    // ── Zero results ──────────────────────────────────────────────────────────

    public function test_reports_nothing_to_delete_when_no_old_conversations(): void
    {
        $this->useDatabase();

        $this->createConversation('recent', daysAgo: 5);

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('Nothing to delete')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('ai_chatbox_conversations', 1);
    }

    public function test_reports_nothing_to_delete_on_empty_table(): void
    {
        $this->useDatabase();

        $this->artisan('ai-chatbox:prune-conversations')
            ->expectsOutputToContain('Nothing to delete')
            ->assertExitCode(Command::SUCCESS);
    }

    // ── save_history touches updated_at ──────────────────────────────────────

    public function test_save_history_touches_updated_at(): void
    {
        $this->useDatabase();

        $repo     = new DatabaseConversationRepository();
        $threadId = 'touch-test-thread';

        $repo->saveHistory($threadId, [
            ['role' => 'user', 'content' => 'First message'],
        ]);

        // Age the record manually
        DB::table('ai_chatbox_conversations')
            ->where('thread_id', $threadId)
            ->update(['updated_at' => now()->subDays(60)->toDateTimeString()]);

        $before = Conversation::where('thread_id', $threadId)->value('updated_at');

        // Saving again must refresh updated_at
        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'First message'],
            ['role' => 'assistant', 'content' => 'Reply'],
        ]);

        $after = Conversation::where('thread_id', $threadId)->value('updated_at');

        $this->assertGreaterThan($before, $after);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Create a Conversation with updated_at set to a specific number of days ago.
     *
     * Uses the raw query builder for the timestamp update so Eloquent cannot
     * silently override the value with NOW() via its automatic timestamp handling.
     */
    private function createConversation(string $threadId, int $daysAgo): Conversation
    {
        $conversation = Conversation::create(['thread_id' => $threadId]);

        DB::table('ai_chatbox_conversations')
            ->where('thread_id', $threadId)
            ->update(['updated_at' => now()->subDays($daysAgo)->toDateTimeString()]);

        return $conversation->fresh();
    }
}
