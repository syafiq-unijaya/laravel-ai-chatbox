<?php
namespace SyafiqUnijaya\AiChatbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RagDocument extends Model
{
    protected $table = 'ai_chatbox_rag_documents';

    protected $fillable = [
        'title',
        'original_filename',
        'file_type',
        'status',
        'chunk_count',
        'content',
        'error_message',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(RagChunk::class, 'document_id')->orderBy('chunk_index');
    }
}
