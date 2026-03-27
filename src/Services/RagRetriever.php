<?php
namespace SyafiqUnijaya\AiChatbox\Services;

use Illuminate\Support\Facades\Log;
use SyafiqUnijaya\AiChatbox\Models\RagChunk;

class RagRetriever
{
    public function __construct(private readonly EmbeddingService $embedder)
    {}

    /**
     * Retrieve the most relevant document chunks for the given query.
     *
     * Returns an ordered list of chunk content strings (best match first).
     * Returns an empty array when RAG is disabled, the embedding call fails,
     * or no chunk clears the similarity threshold.
     *
     * @return string[]
     */
    public function retrieve(string $query): array
    {
        if (!config('ai-chatbox.rag_enabled')) {
            return [];
        }

        $topK = max(1, (int) config('ai-chatbox.rag_top_k', 3));
        $threshold = (float) config('ai-chatbox.rag_similarity_threshold', 0.3);

        $queryEmbedding = $this->embedder->embed($query);

        if ($queryEmbedding === null) {
            Log::warning('AI Chatbox RAG: Query embedding failed — RAG context will not be injected for this message.', [
                'embedding_url' => config('ai-chatbox.rag_embedding_url'),
                'embedding_model' => config('ai-chatbox.rag_embedding_model'),
            ]);
            return [];
        }

        // Load all chunks that belong to ready documents
        $chunks = RagChunk::whereHas(
            'document',
            fn($q) => $q->where('status', 'ready')
        )->get(['id', 'content', 'embedding']);

        if ($chunks->isEmpty()) {
            return [];
        }

        $scored = [];
        $nullEmbeddings = 0;
        $belowThreshold = 0;

        foreach ($chunks as $chunk) {
            $embedding = $chunk->embedding;

            if (!is_array($embedding) || count($embedding) === 0) {
                $nullEmbeddings++;
                continue;
            }

            if (count($embedding) !== count($queryEmbedding)) {
                Log::warning('AI Chatbox RAG: Chunk embedding dimension mismatch — skipping chunk.', [
                    'chunk_id' => $chunk->id,
                    'chunk_dims' => count($embedding),
                    'query_dims' => count($queryEmbedding),
                    'embedding_model' => config('ai-chatbox.rag_embedding_model'),
                ]);
                continue;
            }

            $score = $this->cosineSimilarity($queryEmbedding, $embedding);

            if ($score >= $threshold) {
                $scored[] = ['content' => $chunk->content, 'score' => $score];
            } else {
                $belowThreshold++;
            }
        }

        if ($nullEmbeddings > 0) {
            Log::warning('AI Chatbox RAG: Skipped chunks with no stored embedding — reprocess the document to fix.', [
                'skipped_count' => $nullEmbeddings,
                'embedding_url' => config('ai-chatbox.rag_embedding_url'),
                'embedding_model' => config('ai-chatbox.rag_embedding_model'),
            ]);
        }

        if (empty($scored)) {
            if ($belowThreshold > 0) {
                Log::info('AI Chatbox RAG: No chunks met the similarity threshold — consider lowering AI_CHATBOX_RAG_THRESHOLD.', [
                    'threshold' => $threshold,
                    'chunks_scored' => $belowThreshold,
                ]);
            }
            return [];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_column(array_slice($scored, 0, $topK), 'content');
    }

    /**
     * Compute cosine similarity between two equal-length float vectors.
     * Returns 0.0 for zero-magnitude vectors.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0.0 ? (float) ($dot / $denom) : 0.0;
    }
}
