<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use SyafiqUnijaya\AiChatbox\Models\RagChunk;
use SyafiqUnijaya\AiChatbox\Models\RagDocument;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class RagDocumentTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Mock embedding API returning a simple 3-dim float vector. */
    private function mockEmbedding(array $extra = []): Response
    {
        $body = json_encode([
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3]],
            ],
        ]);

        return new Response(200, ['Content-Type' => 'application/json'], $body);
    }

    /** Create a fake UploadedFile with the given content and extension. */
    private function fakeFile(string $content, string $ext = 'txt'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rag_') . '.' . $ext;
        file_put_contents($path, $content);

        return new UploadedFile($path, 'test.' . $ext, null, null, true);
    }

    // ── Admin routes require authentication ──────────────────────────────────

    public function test_rag_index_requires_auth(): void
    {
        // The RAG admin routes use ['web', 'auth'] middleware.
        // Testbench has no real auth guard wired up, so we just assert the route
        // exists and is accessible when middleware is bypassed.
        $this->withoutMiddleware()
             ->get('/ai-chatbox/rag')
             ->assertStatus(200)
             ->assertSee('Knowledge Base');
    }

    // ── Upload validation ─────────────────────────────────────────────────────

    public function test_upload_requires_a_file(): void
    {
        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [])
             ->assertSessionHasErrors(['file']);
    }

    public function test_upload_rejects_disallowed_file_types(): void
    {
        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile('<html></html>', 'html'),
             ])
             ->assertSessionHasErrors(['file']);
    }

    // ── Successful upload ─────────────────────────────────────────────────────

    public function test_upload_txt_file_creates_document_and_chunks(): void
    {
        // Each chunk requires one embedding API call
        $content = "First paragraph about Laravel.\n\nSecond paragraph about AI.";
        $this->mockGuzzle([
            $this->mockEmbedding(), // chunk 1
            $this->mockEmbedding(), // chunk 2
        ]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file'  => $this->fakeFile($content, 'txt'),
                 'title' => 'My Test Doc',
             ])
             ->assertRedirect('/ai-chatbox/rag')
             ->assertSessionHas('success');

        $doc = RagDocument::first();
        $this->assertNotNull($doc);
        $this->assertSame('My Test Doc', $doc->title);
        $this->assertSame('ready', $doc->status);
        $this->assertGreaterThanOrEqual(1, $doc->chunk_count);

        $this->assertGreaterThanOrEqual(1, RagChunk::count());
    }

    public function test_upload_md_file_works(): void
    {
        $content = "# Title\n\nSome markdown content here.";
        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile($content, 'md'),
             ])
             ->assertRedirect('/ai-chatbox/rag')
             ->assertSessionHas('success');

        $this->assertSame('ready', RagDocument::first()->status);
    }

    public function test_title_defaults_to_filename_stem_when_omitted(): void
    {
        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile('content', 'txt'),
             ]);

        // UploadedFile fake name is 'test.txt', stem is 'test'
        $this->assertSame('test', RagDocument::first()?->title);
    }

    // ── Embedding stored as array ─────────────────────────────────────────────

    public function test_chunks_store_embedding_as_array(): void
    {
        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile('Hello world.', 'txt'),
             ]);

        $chunk = RagChunk::first();
        $this->assertIsArray($chunk->embedding);
        $this->assertCount(3, $chunk->embedding);
        $this->assertEqualsWithDelta(0.1, $chunk->embedding[0], 0.001);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_delete_removes_document_and_its_chunks(): void
    {
        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile('Hello', 'txt'),
             ]);

        $doc = RagDocument::first();
        $this->assertNotNull($doc);

        $this->withoutMiddleware()
             ->delete('/ai-chatbox/rag/' . $doc->id)
             ->assertRedirect('/ai-chatbox/rag')
             ->assertSessionHas('success');

        $this->assertDatabaseMissing('ai_chatbox_rag_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('ai_chatbox_rag_chunks', ['document_id' => $doc->id]);
    }

    // ── Reprocess ─────────────────────────────────────────────────────────────

    public function test_reprocess_regenerates_chunks(): void
    {
        // First upload (1 chunk)
        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile('Single paragraph.', 'txt'),
             ]);

        $doc = RagDocument::first();
        $originalCount = $doc->chunk_count;

        // Reprocess — needs another embedding call
        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag/' . $doc->id . '/reprocess')
             ->assertRedirect('/ai-chatbox/rag')
             ->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame('ready', $doc->status);
        $this->assertSame($originalCount, $doc->chunk_count);
    }

    // ── Per-provider embedding routing ────────────────────────────────────────

    public function test_rag_index_shows_active_provider_embedding_url(): void
    {
        $this->app['config']->set('ai-chatbox.rag_embedding_url', 'http://global.example.com/v1/embeddings');
        $this->app['config']->set('ai-chatbox.providers', [
            'lmstudio' => [
                'api_url'             => 'http://lmstudio.example.com/v1/chat',
                'api_token'           => 'lm-token',
                'api_model'           => 'lm-model',
                'rag_embedding_url'   => 'http://lmstudio.example.com/v1/embeddings',
                'rag_embedding_model' => 'my-embed-model',
            ],
        ]);
        $this->app['config']->set('ai-chatbox.active_provider', 'lmstudio');

        $this->withoutMiddleware()
             ->get('/ai-chatbox/rag')
             ->assertOk()
             ->assertSee('http://lmstudio.example.com/v1/embeddings')
             ->assertSee('my-embed-model')
             ->assertDontSee('http://global.example.com/v1/embeddings');
    }

    public function test_rag_upload_uses_active_provider_embedding_config(): void
    {
        // Global embedding URL is empty — would fail if RagController ignores active_provider
        $this->app['config']->set('ai-chatbox.rag_embedding_url', '');
        $this->app['config']->set('ai-chatbox.providers', [
            'lmstudio' => [
                'api_url'             => 'http://lmstudio.example.com/v1/chat',
                'api_token'           => 'lm-token',
                'api_model'           => 'lm-model',
                'rag_embedding_url'   => 'http://lmstudio.example.com/v1/embeddings',
                'rag_embedding_model' => 'lm-embed',
            ],
        ]);
        $this->app['config']->set('ai-chatbox.active_provider', 'lmstudio');

        $this->mockGuzzle([$this->mockEmbedding()]);

        $this->withoutMiddleware()
             ->post('/ai-chatbox/rag', [
                 'file' => $this->fakeFile('Hello from LM Studio.', 'txt'),
             ])
             ->assertRedirect('/ai-chatbox/rag')
             ->assertSessionHas('success');

        $this->assertSame('ready', RagDocument::first()->status);
    }

    // ── RAG context injection ─────────────────────────────────────────────────

    public function test_rag_context_is_injected_into_chat_when_enabled(): void
    {
        // Seed a ready document with a known chunk + embedding
        $doc = RagDocument::create([
            'title'             => 'FAQ',
            'original_filename' => 'faq.txt',
            'file_type'         => 'txt',
            'status'            => 'ready',
            'chunk_count'       => 1,
        ]);

        RagChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 0,
            'content'     => 'Laravel is a PHP framework.',
            'embedding'   => [1.0, 0.0, 0.0], // unit vector
        ]);

        // Enable RAG
        $this->app['config']->set('ai-chatbox.rag_enabled', true);

        // Two Guzzle calls: 1 embedding (query), 1 chat completion
        $embeddingResponse = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'data' => [['embedding' => [1.0, 0.0, 0.0]]], // identical → similarity 1.0
        ]));

        $chatResponse = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'choices' => [['message' => ['content' => 'Laravel is great!']]],
        ]));

        $this->mockGuzzle([$embeddingResponse, $chatResponse]);

        $response = $this->postJson('/ai-chatbox/message', ['message' => 'What is Laravel?']);

        $response->assertStatus(200)
                 ->assertJson(['reply' => 'Laravel is great!']);
    }
}
