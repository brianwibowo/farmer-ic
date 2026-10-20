# P4 production release checklist

This release keeps the RAG path observable and reversible. Run the checks below against the same commit that will be deployed.

## Pre-deploy gate

```sh
php artisan test
npm run build
php artisan ai:eval --retriever=in-memory --baseline=storage/app/private/ai-evals/baseline-v1.json --output=storage/app/private/ai-evals/release-eval.json
AI_RAG_VECTOR_ENABLED=true php artisan ai:knowledge:index-vector --status
php artisan ai:health --json
```

The release gate requires routing, retrieval Hit@1, citation validation, fallback-chain validation, and retrieval p95 to pass. Keep `release-eval.json` as the release artifact.

## Runtime configuration

- Set `APP_KEY`, `APP_URL`, `DB_CONNECTION=pgsql`, `REDIS_*`, `QDRANT_URL`, and `OPENROUTER_API_KEY` only in the server environment.
- Keep `AI_RAG_VECTOR_ENABLED=true` only after the Qdrant status is green and the index manifest is present.
- Set an explicit `AI_PRIMARY_MODEL` that passed the golden prompt gate.
- Keep `AI_EXECUTION_TIMEOUT` above the observed image-generation p95 and configure the reverse proxy for SSE without buffering.
- Never expose `OPENROUTER_API_KEY` through a `VITE_` variable.

## Deployment and rollback

```sh
docker compose --env-file .env.production -f compose.production.yaml build --pull app
docker compose --env-file .env.production -f compose.production.yaml up -d --no-build --remove-orphans app
docker compose --env-file .env.production -f compose.production.yaml logs --tail=100 app
```

After deployment, verify `/up`, `ai:health --json`, Qdrant collection health, and one finance plus one infographic trace. Roll back by redeploying the previous image/tag and preserving the PostgreSQL/Qdrant volumes.

## Observability

Every completion stores a trace with the user prompt, system prompt, endpoint, model, evidence IDs, provider request ID when supplied, status, and latency in `ai_messages.metadata.request_trace` and `ai_usage_events.metadata.request_trace`. Treat prompt contents as sensitive operational data and restrict access to the application operators.
