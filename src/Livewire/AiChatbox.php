<?php
namespace SyafiqUnijaya\AiChatbox\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class AiChatbox extends Component
{
    public function render(): View
    {
        return view('ai-chatbox::livewire.chatbox');
    }
}
