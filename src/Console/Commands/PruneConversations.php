<?php
namespace SyafiqUnijaya\AiChatbox\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use SyafiqUnijaya\AiChatbox\Memory\Models\Conversation;

class PruneConversations extends Command
{
    protected $signature = 'ai-chatbox:prune-conversations
                            {--days= : Delete conversations with no activity for this many days (overrides config)}
                            {--dry-run : Preview how many conversations would be deleted without deleting them}
                            {--force : Run even if memory_driver is not set to database}';

    protected $description = 'Delete AI chatbox conversations that have been inactive beyond the configured retention period';

    public function handle(): int
    {
        $days = $this->resolveDays();

        if ($days < 1) {
            $this->error('Days must be 1 or greater.');
            return self::FAILURE;
        }

        if (!$this->checkMemoryDriver()) {
            return self::FAILURE;
        }

        if (!$this->checkTablesExist()) {
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = Conversation::where('updated_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No conversations found that have been inactive for more than {$days} day(s). Nothing to delete.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[Dry run] {$count} conversation(s) with no activity since {$cutoff->toDateTimeString()} would be deleted.");
            return self::SUCCESS;
        }

        $this->info("Deleting {$count} conversation(s) with no activity since {$cutoff->toDateTimeString()}...");

        // Messages are cascade-deleted via the foreign key constraint
        $deleted = $query->delete();

        $this->info("Done. {$deleted} conversation(s) deleted (messages removed via cascade).");

        return self::SUCCESS;
    }

    private function resolveDays(): int
    {
        if ($this->option('days') !== null) {
            return (int) $this->option('days');
        }

        return (int) config('ai-chatbox.conversation_prune_days', 30);
    }

    private function checkMemoryDriver(): bool
    {
        $driver = config('ai-chatbox.memory_driver', 'session');

        if ($driver !== 'database') {
            if ($this->option('force')) {
                $this->warn("memory_driver is set to '{$driver}', not 'database'. Running anyway because --force was passed.");
                $this->warn("If no conversations have been stored via the database driver, this command will have nothing to delete.");
                return true;
            }

            $this->error("memory_driver is set to '{$driver}', not 'database'.");
            $this->line('  Conversation pruning only applies when AI_CHATBOX_MEMORY_DRIVER=database.');
            $this->line('  If you previously used the database driver and switched away, re-run with --force to clean up leftover records.');
            return false;
        }

        return true;
    }

    private function checkTablesExist(): bool
    {
        if (!Schema::hasTable('ai_chatbox_conversations')) {
            $this->error("Table 'ai_chatbox_conversations' does not exist.");
            $this->line('  Run the package migrations first:');
            $this->line('    php artisan migrate');
            $this->line('  Or publish them first:');
            $this->line('    php artisan vendor:publish --tag=ai-chatbox-migrations');
            return false;
        }

        if (!Schema::hasTable('ai_chatbox_messages')) {
            $this->warn("Table 'ai_chatbox_messages' does not exist. Cascade deletion of messages will be skipped.");
            $this->warn("Run 'php artisan migrate' to create the missing table.");
        }

        return true;
    }
}
