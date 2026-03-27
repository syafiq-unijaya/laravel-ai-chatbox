<?php
namespace SyafiqUnijaya\AiChatbox\Memory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'ai_chatbox_conversations';

    protected $fillable = ['thread_id', 'user_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
