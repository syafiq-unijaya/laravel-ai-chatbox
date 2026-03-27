<?php
namespace SyafiqUnijaya\AiChatbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SyafiqUnijaya\AiChatbox\Models\RagChunk;
use SyafiqUnijaya\AiChatbox\Models\RagDocument;
use SyafiqUnijaya\AiChatbox\Services\DocumentChunker;
use SyafiqUnijaya\AiChatbox\Services\EmbeddingService;

class RagController extends Controller
{
    // ── List ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $documents = RagDocument::withCount('chunks')->latest()->get();

        return view('ai-chatbox::rag', [
            'documents' => $documents,
            'ragEnabled' => (bool) config('ai-chatbox.rag_enabled'),
            'embeddingUrl' => config('ai-chatbox.rag_embedding_url'),
            'embeddingModel' => config('ai-chatbox.rag_embedding_model'),
        ]);
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,md', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $title = $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $type = strtolower($file->getClientOriginalExtension() ?: 'txt');

        $content = file_get_contents($file->getRealPath());

        if ($content === false || trim($content) === '') {
            return back()->withErrors(['file' => 'The uploaded file is empty or unreadable.']);
        }

        $document = RagDocument::create([
            'title' => $title,
            'original_filename' => $file->getClientOriginalName(),
            'file_type' => $type,
            'status' => 'processing',
            'chunk_count' => 0,
            'content' => $content,
        ]);

        try {
            $this->processDocument($document, $content);
            return redirect()->route('ai-chatbox.rag.index')
                ->with('success', "'{$title}' indexed successfully ({$document->chunk_count} chunks).");

        } catch (\Throwable $e) {
            $document->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return redirect()->route('ai-chatbox.rag.index')
                ->with('error', "Failed to index '{$title}': " . $e->getMessage());
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(int $id): RedirectResponse
    {
        $document = RagDocument::findOrFail($id);
        $title = $document->title;

        $document->chunks()->delete();
        $document->delete();

        return redirect()->route('ai-chatbox.rag.index')
            ->with('success', "'{$title}' deleted.");
    }

    // ── Reprocess (re-chunk + re-embed) ───────────────────────────────────────

    public function reprocess(int $id): RedirectResponse
    {
        $document = RagDocument::findOrFail($id);

        if (empty($document->content)) {
            return redirect()->route('ai-chatbox.rag.index')
                ->with('error', "Cannot reprocess '{$document->title}': original content was not stored.");
        }

        $document->update(['status' => 'processing', 'error_message' => null]);

        try {
            $this->processDocument($document, $document->content);
            return redirect()->route('ai-chatbox.rag.index')
                ->with('success', "'{$document->title}' reprocessed successfully ({$document->chunk_count} chunks).");

        } catch (\Throwable $e) {
            $document->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return redirect()->route('ai-chatbox.rag.index')
                ->with('error', "Failed to reprocess '{$document->title}': " . $e->getMessage());
        }
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function processDocument(RagDocument $document, string $content): void
    {
        $chunker = new DocumentChunker();
        $embedSvc = new EmbeddingService();
        $chunkSize = (int) config('ai-chatbox.rag_chunk_size', 500);
        $overlap = (int) config('ai-chatbox.rag_chunk_overlap', 50);

        $textChunks = $chunker->chunk($content, $chunkSize, $overlap);

        // Clear any previous chunks
        $document->chunks()->delete();

        $count = 0;
        foreach ($textChunks as $i => $chunkText) {
            $embedding = $embedSvc->embed($chunkText);

            RagChunk::create([
                'document_id' => $document->id,
                'chunk_index' => $i,
                'content' => $chunkText,
                'embedding' => $embedding,
            ]);
            $count++;
        }

        $document->update([
            'status' => 'ready',
            'chunk_count' => $count,
        ]);
    }
}
