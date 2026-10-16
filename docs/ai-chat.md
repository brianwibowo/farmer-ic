# Farmer IC AI assistant

The AI page is rendered at `/{current_team}/ai`. Its API routes live under the
same team scope and require an authenticated, verified team member.

## Configuration

Set `OPENROUTER_API_KEY` in the server environment. It must not use a `VITE_`
prefix and must never be sent to the browser. Model IDs are configured through
`AI_PRIMARY_MODEL`, `AI_TEXT_FALLBACK_MODELS`, `AI_VISION_FALLBACK_MODELS`, and
`AI_STT_MODEL`.

`AI_STT_FALLBACK_MODELS` provides compatible audio-capable fallbacks for
transcription outages.

The primary model is tried first. Fallbacks are selected before the first
stream token and only continue for transient provider failures. A partial
stream is saved as failed and is never silently restarted with another model.

## Knowledge files

The initial trusted context lives in:

- `resources/ai/knowledge/app-facts.md`
- `resources/ai/knowledge/assistant-policy.md`

Each conversation stores the content hashes used for its system context. User
messages and uploads cannot change these files. Team adaptive documents use
the API routes under `/{team}/ai/knowledge`; owner/admin users can create,
edit, publish, and archive them. Only published documents enter a new
conversation snapshot.

## Attachments and voice

Images are kept on the configured private filesystem disk. The server checks
the detected MIME type, size, and extension before storing them. The current
allowlist is JPEG, PNG, WebP, and GIF, with a maximum of three files at 10 MB
each.

Voice recording uses `MediaRecorder` in the browser and sends the audio to the
team-scoped transcription endpoint. The backend forwards the audio to the
configured OpenRouter audio-capable model. Browser speech synthesis provides
read-aloud playback in the current UI when `AI_TTS_MODEL` is empty; when a TTS
model is configured, the UI tries the server-side `/voice/synthesize` endpoint
first and falls back to the browser voice if that request is unavailable.

## Streaming deployment

Chat responses use Server-Sent Events. The included `Caddyfile` disables
reverse-proxy buffering for AI message streams. Any alternate reverse proxy
must provide equivalent flush behavior for `/{team}/ai/messages`.

## Verification

Run the following checks before merging:

```sh
php artisan test
php -d memory_limit=256M vendor/bin/phpstan analyse app/AI app/Http/Controllers/AI app/Http/Requests/AI app/Models/Ai*.php --no-progress --memory-limit=256M --debug
./vendor/bin/pint --test
npm run types:check
npx vp check resources/js/pages/farmer/Index.vue resources/js/composables/useAiChat.ts resources/js/composables/useAiVoice.ts
npm run build
```
