import { createApp } from 'vue'
import AiChatbox from './components/AiChatbox.vue'

const el = document.getElementById('ai-chatbox-app')
if (el) {
    createApp(AiChatbox).mount(el)
}
