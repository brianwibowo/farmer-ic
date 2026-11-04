# Farmer IC AI assistant

The AI page is rendered at `/{current_team}/ai`. Its API routes live under the
same team scope and require an authenticated, verified Owner with `ai.access`
and persisted team membership. Admin, Petugas, and Superadmin cannot enter the
AI page or call its APIs. The Superadmin Gate bypass excludes `ai.access`;
other developer permissions are unchanged. Conversation, message, and upload
actions also use their resource Policies, and history queries are scoped to
the authorized actor, team, and personal conversation.

## Configuration

Set `OPENROUTER_API_KEY` in the server environment. It must not use a `VITE_`
prefix and must never be sent to the browser. Model IDs are configured through
`AI_PRIMARY_MODEL`, `AI_ENTERPRISE_MODEL`, `AI_TEXT_FALLBACK_MODELS`,
`AI_VISION_FALLBACK_MODELS`, and `AI_STT_MODEL`.

For production, set `AI_ENTERPRISE_MODEL` to an exact model ID or enterprise
alias enabled for the OpenRouter account. The legacy
`AI_PRIMARY_MODEL=google/gemini-3.8-flash` value is treated as a compatibility
sentinel and resolves to `AI_ENTERPRISE_MODEL`; a different explicit primary
model takes precedence. If `AI_ENTERPRISE_MODEL` is empty, the application
uses `AI_GPT_PRIMARY_MODEL` and then its built-in high-quality default. Keep
fallback lists limited to IDs returned by the account's OpenRouter model list.

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
the API routes under `/{team}/ai/knowledge`; authorized Owners can create,
edit, publish, and archive them. Only published documents enter a new
conversation snapshot.

## Attachments and voice

Images are kept on the configured private filesystem disk. The server checks
the detected MIME type, size, and extension before storing them. The current
allowlist is JPEG, PNG, WebP, and GIF, with a maximum of three files at 10 MB
each.

Private uploads are served through `GET /{team}/ai/attachments/{attachment}`
after AI access, resource visibility, and personal conversation ownership checks.
Responses are private/no-store with `nosniff`; opening history does not require
making uploaded files public. Generated bitmap output is validated for actual
image bytes, MIME, size, and successful storage before it is marked completed.
An unsupported URL-only provider result fails safely without fetching an
arbitrary remote URL.

The Images API receives only the latest user's uploaded image references using
`input_references`; previous users' or assistants' images are not forwarded as
reference inputs. This follows the [OpenRouter Images API contract](https://openrouter.ai/blog/tutorials/image-generation/).

Image copying writes PNG clipboard data when the browser supports it, converting
other supported image formats in the browser when needed. Unsupported image
clipboard support is labelled as link copying, and failures remain visible.
Failed or interrupted replies retain their failure state when history reloads.

## Image prompt fidelity

The image gateway sends the user's visual request verbatim ahead of optional
reference context. Explicit illustration/photo/3D style, a single-plant count,
and no-text/no-number preferences become non-conflicting visual constraints.
The educational guide, schedule, and pest-control quick prompts request an
informative infographic with three to five short sections, large labels in the
user's language, meaningful icons, and connectors where useful. Structural step
numbers are permitted; missing agronomic measurements must not be invented.

Creating an image file is not sufficient visual acceptance. QA must inspect the
actual output: a request for an infographic needs explanatory content and a
clear structure, and a request for a simple illustration must not silently turn
into crop photography or decorative scenery.

## QA checkpoint — 4 November 2026

- Backend AI, authorization, and Owner regression: 190 tests, 4,211 assertions.
- Frontend AI protocol, routing, validation, demo data, and image clipboard:
  48 tests. Build, Vue type checking, and targeted lint/static analysis pass.
- Live Owner chat through the configured provider was exercised. The live
  `Panduan Pemupukan Jagung sesuai Fase` quick prompt produced a genuine
  1024×1024 PNG from the configured image model in about 23 seconds, containing
  four labelled phases, fertilizer illustrations, and directional arrows.
- The infographic appeared in the original chat without refreshing, then loaded
  again from history. Browser clipboard inspection confirmed `image/png`
  after Salin gambar. Clipboard state was restored after the check.
- MIME, corrupt/missing image output, failed storage, partial streams, fallback,
  private attachment delivery, cross-user/team denial, and voice payload/error
  paths have automated coverage. Voice tests use real temporary WAV files and
  fake provider responses; physical microphone capture is not claimed tested.

These results verify this prototype's flow and sampled output, not universal
compliance for every generated image. Keep visual review in QA after prompt or
model changes.

## Farm infographic data contract — 4 November 2026

Farm-data visuals now use `FarmSnapshotService::plan()` and
`FarmInfographicComposer`, replacing the named-farm fixed SVG path. Each
request reloads `farmDemoData.json`, selects matching farms in prompt order,
and records the corpus version, date, and SHA-256. Explicit follow-ups reuse
farm IDs and presentation choices, then reload facts from the current corpus.
Educational guides and generic plant illustrations retain the existing model
workflow; they are not substituted with farm summaries.

The output is a hybrid PNG: the configured image model generates fresh crop
artwork for the header, while GD/FreeType draws selected labels, exact fact
values, and chart geometry. The model never writes the authoritative data
layer. Both the image and text identify the demo source and this composition.
The bundled Noto Sans fonts are distributed under the SIL Open Font License
in `resources/fonts/OFL-NotoSans.txt`; GD and FreeType are required at runtime.
Missing fonts/artwork/storage fail the request rather than returning a success
placeholder. Provider/model/attempts, the verified plan, and actual drawing
manifest remain in message metadata.

Supported presentations include summary cards with capacity/growth gauges,
zero-based bars with separate units, side-by-side metric comparisons, risk
cards, and additive-area/weight donut charts. Independent growth percentages
cannot form slices of a shared whole. Portrait and landscape layouts grow to
fit their selected facts; long portrait images scroll in the lightbox, which
also provides an original-size link. Themes are green, blue, or monochrome.
An unsupported chart/metric is not a promise of arbitrary generative layouts.

`usedHectare` is planted/used area; existing garden views treat
`availableHectare` as total capacity, so it is labelled **Total lahan** here.
Those two fields are never added together. A capacity gauge divides used area
by that farm's total capacity; growth uses its own 0–100% scale. Missing facts
are omitted with a note, and entirely missing or unsupported-only selections
ask for clarification before any image-model request. Unknown farm names do
not fall back to a guessed garden. Numbers supplied in a prompt cannot
replace the canonical JSON values.

Live browser QA through the Owner account produced and inspected:

| Request | Verified result |
| --- | --- |
| Tani Lestari portrait summary | 180/750 ha, 65%, 31 ton, Parakan–Temanggung, Jagung BISI-18 |
| Tani Lestari vs Sumber Makmur, area-only blue landscape bars | 180/750 vs 300/1,000 ha; growth/risk excluded |
| Harapan Jaya, status/growth/risk/action only | Kritis, 43%, source-specific water-stress risk/action; area/weight excluded |
| Follow-up: landscape monochrome, risk/action only | Same Harapan Jaya selection; status/growth removed |
| Entire corpus portrait summary | All five farms with distinct source areas, crops, statuses and growth gauges |
| Indonesian “donat”, area-only, blue landscape | 300, 180, 420, 95, 260 ha; no total-capacity/growth/risk facts |
| Unknown Kebun Pelangi / only farmer count | Helpful clarification; no generated image |

Live QA caught and fixed the Indonesian “donat” alias and comma-separated
negative-field lists; the exact prompt now has both planner and HTTP/raster
regressions.

For the six accepted image outputs, persisted PNG signatures, attachment hashes,
corpus hashes, and rendered fact manifests matched. Each used the configured
image provider, with approximately 11–15 seconds generation latency. History
reload, image lightbox, and vertical scrolling were checked at 390×844 and
844×390 in light and dark themes, with no horizontal page overflow. The theme
and viewport override were restored after QA. These
checks demonstrate sampled prompt/data fidelity, not universal correctness for
arbitrary natural-language requests.

Automated regression after these changes: **241 backend tests / 5,019
assertions**, plus **48 frontend tests**. Build, Vue type checking, targeted
Pint, Vue lint and PHPStan pass. Planner tests change a temporary JSON copy to
fractional values and verify changed facts/hash; raster tests verify drawn
text pixels, chart denominators, requested-field omissions, source provenance,
and failure handling. Profile and Beranda are outside this change.

## Current demo boundary

AI continues to use `farmDemoData.json` and the existing demo/reference knowledge,
as requested for this prototype QA. Local data answers are labelled as demo data.
This work does not migrate those answers to current farm database records.
The scoped HTTP history and attachment paths above do not constitute a broad
authorization migration of legacy retrieval services or actorless index jobs;
those existing non-HTTP and knowledge-read paths remain a follow-up audit scope.
Specifically, `KnowledgeService::snapshot()` and `retrievalDocuments()` still
read published team documents without an actor-aware `visibleTo` scope.
`IndexKnowledgeDocument::handle()` and `RemoveKnowledgeDocumentFromIndex::handle()`
still load document records without an explicit actor Gate. All Laravel AI
routes are now Owner-gated; generated output retains its existing public media
URLs, while uploaded reference files use the protected attachment endpoint.

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
npm run test:ai
npx vp check resources/js/pages/farmer/Index.vue resources/js/composables/useAiChat.ts resources/js/composables/useAiVoice.ts
npm run build
```
