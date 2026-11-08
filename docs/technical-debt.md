# Technical Debt

## TD-001 — Structured AI response streaming

- **Status:** Planned
- **Priority:** Medium
- **Area:** AI chat frontend and provider integration
- **Recorded:** 20 Oktober 2026

### Context

The prototype currently uses structured mock responses in the browser for finance, activity, and farm summaries. Responses from the real AI endpoint still use the existing Markdown content flow.

### Planned improvement

- Send a `response_format` JSON Schema with `stream: true`.
- Validate the completed document before saving or displaying it.
- Convert provider deltas into `message.start`, `message.section`, and `message.done` application events.
- Render the existing structured response card for production responses.
- Keep Markdown as a fallback for unsupported models or older conversations.

### Provider notes

The tested model is `openai/gpt-6.1-sol`. It supports the structured response schema when the request only contains supported parameters. Do not send the global `temperature` parameter with `require_parameters: true` for this model; temperature here refers to model sampling, not farm weather data.

### Completion criteria

- The production stream emits a schema-valid response document.
- Partial JSON cannot be rendered as a completed answer.
- Finance, activity, farm analysis, and infographic responses have stable response types.
- Unsupported providers fall back to the existing Markdown renderer.
- Existing conversations and browser demo data remain readable.
