Flow Diagram:

┌──────────────────────────────┐
│          CLIENT (Browser)    │
│                              │
│   Vue Chat UI (Chatbox)      │
│   - User input               │
│   - Chat history display     │
└──────────────┬───────────────┘
               │ HTTP (AJAX / API)
               ▼
┌──────────────────────────────┐
│     APPLICATION SERVER       │
│   (Laravel 10 + Chatbox)     │
│                              │
│  ┌────────────────────────┐  │
│  │ laravel-ai-chatbox     │  │
│  │                        │  │
│  │ - Chat Controller      │  │
│  │ - Prompt Builder       │  │
│  │ - RAG (DB knowledge)   │  │
│  │ - Conversation Memory  │  │
│  └──────────┬─────────────┘  │
│             │                │
│             ▼                │
│     Internal Services        │
│  - MySQL (chat logs)         │
│  - Knowledge DB (RAG files)  │
└─────────────┬────────────────┘
              │ HTTP API (REST)
              ▼
┌──────────────────────────────┐
│         AI SERVER            │
│      (Self-hosted LLM)       │
│                              │
│     ┌──────────────────┐     │
│     │ Ollama API       │     │
│     │                  │     │
│     │ - Model (LLM)    │     │
│     │ - Inference      │     │
│     └────────┬─────────┘     │
│              │               │
│              ▼               │
│        Local Models          │
│     (Llama / Mistral etc.)   │
└──────────────────────────────┘