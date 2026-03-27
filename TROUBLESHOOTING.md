# AI Chatbox — Troubleshooting & Error Code Reference

When the AI service is offline or a request fails, the package returns a JSON response containing a `code` field:

```json
{ "status": "offline", "code": "E07", "message": "AI service is currently unreachable." }
```

The same code is written to `storage/logs/laravel.log`. Search for it:

```bash
grep "E07" storage/logs/laravel.log
```

---

## Error Code Reference

### Configuration Errors

These are resolved by fixing your `.env` or published `config/ai-chatbox.php`.

| Code | Meaning | How to fix |
|------|---------|------------|
| `E01` | `AI_CHATBOX_API_URL` is missing or empty | Set `AI_CHATBOX_API_URL` in `.env` |
| `E02` | `AI_CHATBOX_API_URL` is malformed — no host could be parsed | Check the URL format, e.g. `http://localhost:11434/v1/chat/completions` |
| `E03` | `AI_CHATBOX_API_TOKEN` is missing or empty | Set `AI_CHATBOX_API_TOKEN` in `.env` (use `ollama` for local Ollama) |
| `E04` | `AI_CHATBOX_API_MODEL` contains invalid characters | Model name must match `[a-zA-Z0-9_:.-]`, e.g. `phi3:mini`, `llama3`, `gpt-4o` |

---

### Security Errors

| Code | Meaning | How to fix |
|------|---------|------------|
| `E05` | SSRF protection blocked the request — the configured URL resolves to a private or reserved IP | If this is intentional (local Ollama), set `AI_CHATBOX_SSRF_PROTECTION=false` in `.env`. Do **not** disable in production. |

---

### Network / Connectivity Errors

These indicate the AI service cannot be reached from the server running Laravel.

| Code | Meaning | How to fix |
|------|---------|------------|
| `E06` | DNS resolution failed — hostname not found | Check that the hostname in `AI_CHATBOX_API_URL` is correct and resolvable from the server. Run `nslookup <host>` or `dig <host>` to verify. |
| `E07` | Connection refused — the host is reachable but nothing is listening on that port | The AI service is not running. Start Ollama (`ollama serve`) or your AI provider. Check the port number in the URL. |
| `E08` | Connection timed out — the host did not respond within the timeout window | The service may be overloaded or a firewall is silently dropping packets. Try increasing `AI_CHATBOX_TIMEOUT`. Check firewall rules between your server and the AI host. |
| `E09` | SSL/TLS error — certificate validation failed or handshake error | The AI service's SSL certificate is invalid, self-signed, or expired. Use a valid certificate, or if self-signed, configure your HTTP client to trust it. |
| `E10` | Too many redirects | The endpoint URL is redirecting in a loop. Verify `AI_CHATBOX_API_URL` points to the correct final endpoint. |
| `E11` | Generic connection error (unclassified) | Check `storage/logs/laravel.log` for the full exception message to diagnose further. |

---

### API / HTTP Errors

These occur when the AI service is reachable but returns an error HTTP status.

| Code | HTTP Status | Meaning | How to fix |
|------|-------------|---------|------------|
| `E12` | 401 | Unauthorized — the token was rejected | Check `AI_CHATBOX_API_TOKEN`. Regenerate the API key from your provider's dashboard. |
| `E13` | 403 | Forbidden — the token does not have permission | Your token may be scoped incorrectly. Check API key permissions with your provider. |
| `E14` | 404 | Not Found — the endpoint URL is wrong | Verify `AI_CHATBOX_API_URL`. For Ollama the path should be `/v1/chat/completions` (OpenAI-compatible) or `/api/chat` (native). |
| `E15` | 429 | Too Many Requests — rate limited by the AI provider | You are sending too many requests. Reduce `AI_CHATBOX_RATE_LIMIT` or upgrade your API plan. |
| `E16` | 500 / 502 / 503 / 504 | Server-side error from the AI service | The AI service itself is experiencing issues. Check the service's status page or logs. For Ollama, check `journalctl -u ollama`. |
| `E17` | Other | Unexpected HTTP status | Check `storage/logs/laravel.log` for the full response details. |

---

### Response Errors

| Code | Meaning | How to fix |
|------|---------|------------|
| `E18` | The AI API returned an empty or unparseable reply | The model may have returned an empty completion. Try a different model (`AI_CHATBOX_API_MODEL`) or adjust `AI_CHATBOX_TEMPERATURE`. Check that the API response format matches OpenAI-compatible or Ollama native format. |
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

```env
# Start Ollama first
# Then set:
AI_CHATBOX_API_URL=http://localhost:11434/v1/chat/completions
AI_CHATBOX_API_TOKEN=ollama
AI_CHATBOX_SSRF_PROTECTION=false   # required for localhost
```

### Ollama running in WSL, accessed from Windows

**Symptom:** `E06` (DNS failure) or `E07` (connection refused)

```bash
# Get your WSL IP (run inside WSL)
ip addr show eth0 | grep 'inet '
```

```env
AI_CHATBOX_API_URL=http://172.x.x.x:11434/v1/chat/completions
AI_CHATBOX_SSRF_PROTECTION=false   # WSL IP is in a private range
```

### OpenAI / cloud provider — invalid token

**Symptom:** `E12` (401 Unauthorized)

```env
AI_CHATBOX_API_TOKEN=sk-...   # paste your key, no quotes
```

### Request times out on large models

**Symptom:** `E08` (timeout)

```env
AI_CHATBOX_TIMEOUT=120   # increase to 120 seconds
```

### Wrong endpoint for provider

**Symptom:** `E14` (404 Not Found)

| Provider | Correct `AI_CHATBOX_API_URL` |
|---|---|
| Ollama (OpenAI-compatible) | `http://localhost:11434/v1/chat/completions` |
| Ollama (native) | `http://localhost:11434/api/chat` |
| Ollama cloud | `https://ollama.com/api/chat` |
| LM Studio | `http://localhost:1234/v1/chat/completions` |
| OpenAI | `https://api.openai.com/v1/chat/completions` |
| Groq | `https://api.groq.com/openai/v1/chat/completions` |
| OpenRouter | `https://openrouter.ai/api/v1/chat/completions` |

### LM Studio not connecting

**Symptom:** `E07` (connection refused) or `E05` (SSRF blocked)

```env
AI_CHATBOX_API_URL=http://localhost:1234/v1/chat/completions
AI_CHATBOX_API_TOKEN=lm-studio
AI_CHATBOX_API_MODEL=your-loaded-model-name   # must match exactly what LM Studio shows
AI_CHATBOX_SSRF_PROTECTION=false              # required — localhost is a private IP
```

Make sure the **Local Server** is started inside LM Studio and a model is loaded before sending requests.
