<?php
namespace SyafiqUnijaya\AiChatbox\Services;

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

        foreach ($chunks as $chunk) {
            $embedding = $chunk->embedding;

            if (!is_array($embedding) || count($embedding) !== count($queryEmbedding)) {
                continue;
            }

            $score = $this->cosineSimilarity($queryEmbedding, $embedding);

            if ($score >= $threshold) {
                $scored[] = ['content' => $chunk->content, 'score' => $score];
            }
        }

        if (empty($scored)) {
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
