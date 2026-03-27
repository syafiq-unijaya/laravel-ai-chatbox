<?php
namespace SyafiqUnijaya\AiChatbox\Services;

class DocumentChunker
{
    /**
     * Split raw text into overlapping chunks.
     *
     * Chunks are built by accumulating paragraphs (blocks separated by two or
     * more newlines). When adding the next paragraph would exceed the target
     * size, the current buffer is saved and a new one starts with the trailing
     * overlap carried over.
     *
     * @param  string  $text            Raw document text (plain text or Markdown).
     * @param  int     $chunkSizeTokens Target chunk size in tokens (~4 chars/token).
     * @param  int     $overlapTokens   Overlap between consecutive chunks in tokens.
     * @return string[]
     */
    public function chunk(string $text, int $chunkSizeTokens = 500, int $overlapTokens = 50): array
    {
        $chunkBytes = $chunkSizeTokens * 4;
        $overlapBytes = $overlapTokens * 4;

        // Normalize line endings and strip byte-order mark
        $text = ltrim(str_replace("\r\n", "\n", $text), "\xEF\xBB\xBF");

        // Split on paragraph boundaries (2+ blank lines)
        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }

            // If the paragraph itself is longer than one chunk, split it by sentences
            if (strlen($para) > $chunkBytes) {
                // Flush current buffer first
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = $overlapBytes > 0 ? $this->tail($current, $overlapBytes) : '';
                }

                // Split long paragraph on sentence boundaries (. ! ?)
                $sentences = preg_split('/(?<=[.!?])\s+/', $para) ?: [$para];
                foreach ($sentences as $sentence) {
                    if (strlen($current) + strlen($sentence) + 1 > $chunkBytes && $current !== '') {
                        $chunks[] = $current;
                        $current = $overlapBytes > 0 ? $this->tail($current, $overlapBytes) : '';
                    }
                    $current .= ($current !== '' ? ' ' : '') . $sentence;
                }
                continue;
            }

            // Would adding this paragraph overflow the current chunk?
            $separator = $current !== '' ? "\n\n" : '';
            if ($current !== '' && strlen($current) + strlen($separator) + strlen($para) > $chunkBytes) {
                $chunks[] = $current;
                $current = $overlapBytes > 0 ? $this->tail($current, $overlapBytes) : '';
                $separator = $current !== '' ? "\n\n" : '';
            }

            $current .= $separator . $para;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter(array_map('trim', $chunks), fn($c) => $c !== ''));
    }

    /** Return the last $bytes bytes of $str (for overlap carry-over). */
    private function tail(string $str, int $bytes): string
    {
        return strlen($str) > $bytes ? substr($str, -$bytes) : $str;
    }
}
