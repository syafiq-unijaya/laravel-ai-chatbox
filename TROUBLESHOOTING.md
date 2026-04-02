# AI Chatbox — Troubleshooting & Error Code Reference

When the AI service is offline or a request fails, the package returns a JSON response containing a `code` field:

```json
{ "status": "offline", "code": "E07", "message": "AI service is currently unreachable." }
```

The same code is written to `storage/logs/laravel.log`. Search for it:

```bash
grep "E07" storage/logs/laravel.log
```

> **How URLs and tokens are configured:** `api_url`, `api_token`, and `api_model` are always sourced from the **active named provider**, not from top-level env vars. Set `AI_CHATBOX_ACTIVE_PROVIDER` to select the provider, then configure that provider's own variables (e.g. `OLLAMA_URL`, `OPENAI_API_KEY`). See the [Configuration Reference](README.md#ai-providers) in the README.

---

## Error Code Reference

### Configuration Errors

These are resolved by fixing your `.env` or published `config/ai-chatbox.php`.

| Code | Meaning | How to fix |
|------|---------|------------|
| `E01` | The active provider's `api_url` is missing or empty | Set the URL env var for your active provider — e.g. `OLLAMA_URL`, `OPENAI_URL`, `GROQ_URL` |
| `E02` | The active provider's `api_url` is malformed — no host could be parsed | Check the URL format, e.g. `http://localhost:11434/v1/chat/completions` |
| `E03` | The active provider's `api_token` is missing or empty | Set the token env var for your active provider — e.g. `OLLAMA_TOKEN`, `OPENAI_API_KEY`, `GROQ_API_KEY` |
| `E04` | The active provider's `api_model` contains invalid characters | Model name must match `[a-zA-Z0-9_:.-]`, e.g. `gpt-oss:120b`, `llama3`, `gpt-4o` |

---

### Security Errors

| Code | Meaning | How to fix |
|------|---------|------------|
| `E05` | SSRF protection blocked the request — the configured URL resolves to a private or reserved IP | If this is intentional (local Ollama, LM Studio), set `AI_CHATBOX_SSRF_PROTECTION=false` in `.env`. Do **not** disable in production. |

---

### Network / Connectivity Errors

These indicate the AI service cannot be reached from the server running Laravel.

| Code | Meaning | How to fix |
|------|---------|------------|
| `E06` | DNS resolution failed — hostname not found | Check that the hostname in your provider's URL env var is correct and resolvable from the server. Run `nslookup <host>` or `dig <host>` to verify. |
| `E07` | Connection refused — the host is reachable but nothing is listening on that port | The AI service is not running. Start Ollama (`ollama serve`) or your AI provider. Check the port number in the URL. |
| `E08` | Connection timed out — the host did not respond within the timeout window | The service may be overloaded or a firewall is silently dropping packets. Try increasing `AI_CHATBOX_TIMEOUT`. Check firewall rules between your server and the AI host. |
| `E09` | SSL/TLS error — certificate validation failed or handshake error | The AI service's SSL certificate is invalid, self-signed, or expired. Use a valid certificate, or configure your HTTP client to trust a self-signed one. |
| `E10` | Too many redirects | The endpoint URL is redirecting in a loop. Verify your provider's URL env var points to the correct final endpoint. |
| `E11` | Generic connection error (unclassified) | Check `storage/logs/laravel.log` for the full exception message to diagnose further. |

---

### API / HTTP Errors

These occur when the AI service is reachable but returns an error HTTP status.

| Code | HTTP Status | Meaning | How to fix |
|------|-------------|---------|------------|
| `E12` | 401 | Unauthorized — the token was rejected | Check the token env var for your active provider (e.g. `OPENAI_API_KEY`). Regenerate the API key from your provider's dashboard. |
| `E13` | 403 | Forbidden — the token does not have permission | Your token may be scoped incorrectly. Check API key permissions with your provider. |
| `E14` | 404 | Not Found — the endpoint URL is wrong | Verify your provider's URL env var. For Ollama the path should be `/v1/chat/completions` (OpenAI-compatible) or `/api/chat` (native). |
| `E15` | 429 | Too Many Requests — rate limited by the AI provider | You are sending too many requests. Reduce `AI_CHATBOX_RATE_LIMIT` or upgrade your API plan. |
| `E16` | 500 / 502 / 503 / 504 | Server-side error from the AI service | The AI service itself is experiencing issues. Check the service's status page or logs. For Ollama, check `journalctl -u ollama`. |
| `E17` | Other | Unexpected HTTP status | Check `storage/logs/laravel.log` for the full response details. |

---

### Response Errors

| Code | Meaning | How to fix |
|------|---------|------------|
| `E18` | The AI API returned an empty or unparseable reply | The model may have returned an empty completion. Try a different model (update your provider's model env var) or adjust `AI_CHATBOX_TEMPERATURE`. Verify the API response format matches OpenAI-compatible or Ollama native format. |
| `E19` | Unknown / unclassified error | An unexpected exception was thrown. Check `storage/logs/laravel.log` for the full stack trace. |

---

## Reading the Log

Every error is logged to Laravel's default log channel with its code:

```
[2025-01-01 12:00:00] production.WARNING: AI Chatbox health check failed {"code":"E07","message":"cURL error 7: Failed to connect to localhost port 11434"}
[2025-01-01 12:00:00] production.ERROR: AI Chatbox error {"code":"E12","status":401,"message":"Client error: 401 Unauthorized"}
```

To tail the log in real time:

```bash
tail -f storage/logs/laravel.log | grep "AI Chatbox"
```

---

## Common Scenarios

### Local Ollama not reachable

**Symptom:** `E07` (connection refused) or `E05` (SSRF blocked)

Start Ollama first (`ollama serve`), then configure:

```env
AI_CHATBOX_ACTIVE_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434/v1/chat/completions
OLLAMA_TOKEN=your-ollama-token
OLLAMA_MODEL=gpt-oss:120b
AI_CHATBOX_SSRF_PROTECTION=false   # required — localhost is a private IP
```

---

### Ollama running in WSL, accessed from Windows

**Symptom:** `E06` (DNS failure) or `E07` (connection refused)

```bash
# Get your WSL IP (run inside WSL)
ip addr show eth0 | grep 'inet '
```

```env
AI_CHATBOX_ACTIVE_PROVIDER=ollama
OLLAMA_URL=http://172.x.x.x:11434/v1/chat/completions
AI_CHATBOX_SSRF_PROTECTION=false   # WSL IP is in a private range
```

---

### OpenAI / cloud provider — invalid token

**Symptom:** `E12` (401 Unauthorized)

```env
AI_CHATBOX_ACTIVE_PROVIDER=openai
OPENAI_API_KEY=sk-...   # paste your key, no quotes
```

---

### Request times out on large models

**Symptom:** `E08` (timeout)

```env
AI_CHATBOX_TIMEOUT=120   # increase to 120 seconds
```

---

### Wrong endpoint URL for provider

**Symptom:** `E14` (404 Not Found)

| Provider | Correct URL env var | Correct value |
|---|---|---|
| Ollama (OpenAI-compatible) | `OLLAMA_URL` | `http://localhost:11434/v1/chat/completions` |
| Ollama (native) | `OLLAMA_URL` | `http://localhost:11434/api/chat` |
| Ollama cloud | `OLLAMA_URL` | `https://ollama.com/api/chat` |
| LM Studio | `LMSTUDIO_URL` | `http://localhost:1234/v1/chat/completions` |
| OpenAI | `OPENAI_URL` | `https://api.openai.com/v1/chat/completions` |
| Groq | `GROQ_URL` | `https://api.groq.com/openai/v1/chat/completions` |
| OpenRouter | *(custom provider)* | `https://openrouter.ai/api/v1/chat/completions` |

---

### LM Studio not connecting

**Symptom:** `E07` (connection refused) or `E05` (SSRF blocked)

```env
AI_CHATBOX_ACTIVE_PROVIDER=lmstudio
LMSTUDIO_URL=http://localhost:1234/v1/chat/completions
LMSTUDIO_TOKEN=lmstudio
LMSTUDIO_MODEL=your-loaded-model-name   # must match exactly what LM Studio shows
AI_CHATBOX_SSRF_PROTECTION=false        # required — localhost is a private IP
```

Make sure the **Local Server** is started inside LM Studio and a model is loaded before sending requests.
