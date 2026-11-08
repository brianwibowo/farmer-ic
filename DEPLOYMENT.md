# Docker and VPS deployment

The current application uses PHP 8.5, PostgreSQL and UUID domain/user IDs. The
image builds PHP dependencies and Wayfinder helpers with PHP 8.5, builds Vue
with Node 22, and runs Apache, workers and the scheduler from the same image.
The image includes `pcntl` for queue timeouts. Container startup caches Laravel
configuration, routes and views; **it never runs migrations or creates an
application key**.

## Important: existing VPS stacks

A read-only inspection on 8 November 2026 found two separate app stacks:

- `farmer-ic-app-1` uses `.env.production` and the legacy `farmer_ic` database
  with integer user IDs. The current UUID migrations overlap its old tables.
- `farmer.icdigital.io` currently points through `https-portal` to the separate
  `farmer-ic-demo-public-ac487036` container. It uses the UUID database
  `farmer_ic_demo_staging_ac487036`, with its own storage, Redis prefix and
  Qdrant collection. Its image is tagged `staging-c9a6dca-ac487036-v3`.

The proxy's live configuration uses the untracked
`compose.public-demo-ac487036.yaml` overlay. The proposed production workflow
and manual command below use the base Compose file alone: if allowed to proceed,
they would reconfigure the public domain to point to `farmer-ic-app-1` instead
of the currently public demo. **Do not run them until the intended public stack,
database and proxy configuration have been explicitly chosen and reviewed.**

Both databases share the same PostgreSQL service. Never reset the shared service
or run the UUID migrations directly against the legacy database. For a disposable
demo whose owner has explicitly confirmed that its data is not needed, plan a
separate demo-only database and cutover; do not assume other stacks are disposable.
If data must be preserved, back it up and test migration and rollback on an
isolated copy. Approval is required before changing the live schema or traffic.
The pending-migration check below is a safety gate, not proof of compatibility.

Do not use `git clean`, forced checkout, `--remove-orphans`, volume removal or
image pruning as routine deployment steps. Review old notification jobs with
the old code before starting new consumers; WhatsApp retries can send duplicates.

### Intended target

The intended destination is one `farmer-ic` Compose stack, not one container
for all processes. Its app, workers, scheduler, PostgreSQL, Redis, Qdrant and
HTTPS proxy remain separate services. The `farmer-ic-demo-*` containers and
their database are temporary and must remain available until the replacement
has been verified and the public route has been switched.

The stakeholder confirmed the existing application/demo data does not need to
be retained and did not request a backup. This does not authorize deleting the
shared PostgreSQL service, its volume, other databases, or unrelated containers.
Plan the new stack against an explicitly identified empty database; separately
confirm the exact old/demo resources before removing them after the cutover.

Preparation on 8 November 2026 created the separate `farmer_ic_app` database
and applied 47 migrations to it. The migration files used from the existing demo
image were compared byte-for-byte (after newline normalization) with the local
files. At the stakeholder's explicit request, the complete presentation demo was
then seeded in an isolated `testing` sandbox and promoted atomically to
`farmer_ic_app`. The existing Superadmin, role and tenant IDs were preserved.
The seeded database contains the four demo accounts, six farms, and 24 finance
transactions. The current seeder creates no notification, although the demo
credentials guide still describes one. The accounts use publicly known fixture
passwords, not secrets.
All four logins and role-specific pages were verified only on a temporary
loopback-bound test container. The prior bootstrap-only database remains
temporarily as `farmer_ic_bootstrap_20261108_a1f97d`; it is not the live demo
database and may be reviewed separately during post-cutover cleanup.
No environment file, running service or public proxy was changed; the seeded
database is not yet used by the live app. Check its status before continuing.

The image tagged `farmer-ic:verify-snapshot-20261108-f5b8c2` was built on the
VPS from an uncommitted local working-tree snapshot. Its PHP extensions, assets,
entrypoint, schema preflight, loopback login/logout, and worker connections were
checked, but it is **not** a CI-tested release image or an active service.
Use a reviewed commit and a successful CI run for the release. The current
proposed workflow publishes the image smoke-tested by CI to GHCR and deploys it
by immutable digest; it no longer rebuilds the image on the VPS. This GHCR
publishing and deployment flow has not yet run against GitHub for this change.

An isolated Compose smoke test on 8 November 2026 used that snapshot with the
new release-target script mounted read-only, a unique temporary Compose project,
and disposable PostgreSQL/Redis/Qdrant volumes. It verified empty/pending schema
rejection, migrations on the disposable database, release-target checks,
application/worker startup and Apache health. Only the test project's resources
were removed afterward. This is not a test of a committed release image.

An empty database has no usable accounts or roles. `DatabaseSeeder` deliberately
refuses production/staging and its sample accounts have public development
passwords. The demo credentials above are an explicit stakeholder-requested
exception for this presentation, not a secure deployment default. Schema
migration alone does not create the accounts or demo data.

## Local Docker (fresh demo database only)

Create `.env.docker` from `.env.docker.example`. Generate a **new local-only**
key with `php artisan key:generate --show` (or `openssl rand -base64 32`,
prefixed with `base64:`) and set `APP_KEY` in that file. Use the same stable key
for the app, both workers and scheduler; losing or changing it invalidates
encrypted data and sessions. Do not reuse the VPS key locally.

For a fresh, disposable local database:

```sh
docker compose --env-file .env.docker build app
docker compose --env-file .env.docker up -d --wait postgres
docker compose --env-file .env.docker run --rm --no-deps app php artisan migrate --force --no-interaction
docker compose --env-file .env.docker up -d --wait --no-build
```

The app listens on <http://localhost:8080>. PostgreSQL is required; a legacy
SQLite volume is not migrated or removed. On an existing database, review its
schema and pending migrations instead of blindly running the fresh-demo command.
Seed only a disposable demo database using the separately documented procedure
in `docs/demo-seed-credentials.md`.

The base stack uses a PostgreSQL-backed general worker for `ai-index,default`,
one notification worker for `notifications,whatsapp`, and a scheduler. To use
Redis and Qdrant locally, use the same commands with both Compose files:

```sh
docker compose --env-file .env.docker -f compose.yaml -f compose.rag.yaml build app
docker compose --env-file .env.docker -f compose.yaml -f compose.rag.yaml up -d --wait postgres redis qdrant
docker compose --env-file .env.docker -f compose.yaml -f compose.rag.yaml run --rm --no-deps app php artisan migrate --force --no-interaction
docker compose --env-file .env.docker -f compose.yaml -f compose.rag.yaml up -d --wait --no-build
```

In this override the general queue and cache use Redis; notification jobs
remain on the database connection. Qdrant and Redis are bound to loopback.
Configure the embedding provider and credentials before enabling vector RAG.

`ai:knowledge:index-vector`, including its `--status` option, currently syncs
JSON demo knowledge before doing its work. It can write/publish demo documents
and is **not** a read-only status check. The production `indexer` service is
manual and demo-only; do not run it on a real-data database. Do not treat the
JSON fixture as authoritative current farm or finance data. Review and adapt
the indexing flow before enabling it for real data.

**Existing AI limitation, outside this CI/CD and Docker work:**
`FarmSnapshotService` reads the bundled farm JSON, `FinanceSnapshotService`
can fall back to bundled finance JSON, and `KnowledgeService` includes demo
documents in in-memory retrieval. Disabling vector search alone does not remove
those paths. These references must not be represented as live database records;
supporting tenant-scoped, current-data answers requires separate work.

## GitHub Actions

CI runs on pushes to `dev` and `main`, and on pull requests. It regenerates
gitignored Wayfinder helpers with `--with-form`, checks PHP/TypeScript/lint,
runs Vitest and Laravel's isolated PostgreSQL test suites, builds the frontend,
and builds and smoke-tests the production image with a disposable database.
The smoke test intentionally migrates only its isolated CI database.

Deploy is currently disabled on GitHub. The proposed workflow also requires the
repository variable `PRODUCTION_DEPLOY_ENABLED` to be exactly `true`; leave it
unset until cutover is approved. After a successful **push** CI run on `main`,
the Docker job pushes the tested image to `ghcr.io/<owner>/<repository>` with a
commit tag and stores its immutable digest as a CI artifact. The enabled Deploy
workflow downloads that artifact from the triggering successful CI run, checks
its repository and commit, and pulls that digest on the VPS only if the commit
is still the tip of `main`. It checks the configured Redis/Qdrant namespaces,
actual PostgreSQL target, and pending migrations with that image before
restarting services. It stops if the target is wrong or inaccessible, has no
migration table, or has pending migrations. The CI digest artifact is retained
for 30 days; package retention is managed separately in GHCR.
The workflow does not apply migrations. A failed check leaves existing running
containers in place, although the VPS Git working tree has been updated to the
new commit. The check requires the database and its Docker network to be running.

Configure GitHub secrets `VPS_HOST`, `VPS_PORT`, `VPS_USER`, and either `VPS_KEY`
(or `SSH_PRIVATE_KEY`) or `VPS_PASSWORD`. Defaults are
`farmer.icdigital.io:12320` and `clouduser`; an explicitly configured secret
overrides a default. Set `VPS_HOST_FINGERPRINT` to the independently verified
SSH host-key fingerprint to verify the server identity. `VPS_DEPLOY_PATH`
defaults to `/home/clouduser/farmer-ic`.
The existing `APP_KEY` in `.env.production` takes priority; the `APP_KEY` GitHub
secret is only a fallback if it is absent. A nonempty `OPENROUTER_API_KEY` GitHub
secret overrides the file for the deployed containers without modifying the
file. If relying on GitHub-only values, also supply them securely when running
Compose manually. Never print resolved Compose configuration containing secrets.

The Docker CI job needs permission to publish GHCR packages. Deploy uses its
short-lived GitHub token with package-read access and an isolated temporary
Docker login on the VPS; the login is removed afterward. Ensure the package is
linked to this repository and the repository's Actions token can read it, or a
private-package pull will fail before services restart. Verify GHCR visibility,
organization policy, registry connectivity and token permissions before enabling
Deploy. Pushing to `dev` runs CI but does not publish a release image.

## VPS runtime settings

Keep `.env.production` outside Git, restricted to the deployment user. Set the
**approved** database connection and credentials explicitly, including
`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
and the `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` used by the existing
PostgreSQL container. Do not assume changing those values migrates data. Set a
stable `APP_KEY`, `APP_URL` and, if used, the server-only OpenRouter key.

The current VPS file still points to the legacy `farmer_ic` database and must
not be used for the new release unchanged. At the approved cutover, configure:

```text
DB_CONNECTION=pgsql
DB_HOST=farmer-ic-postgres
DB_PORT=5432
DB_DATABASE=farmer_ic_app
REDIS_HOST=redis
REDIS_PREFIX=farmer_ic_app_
QDRANT_URL=http://qdrant:6333
QDRANT_COLLECTION=farmer_ic_app_knowledge
```

The release gate disallows `DB_URL` and `REDIS_URL` because they can override the
checked host/database values. It also requires Redis port 6379, the default Redis
queue connection and the default PostgreSQL queue connection (or `pgsql`).
Do not change `.env.production` while the legacy services still rely on it
without a cutover plan. The namespaces above are distinct from the existing
legacy and demo settings. The `app_release_storage` volume is separate from the
legacy `app_storage` and demo volumes; neither old volume is deleted or copied.
The gate checks configuration and the actual PostgreSQL connection, but does not
prove Redis/Qdrant connectivity, AI retrieval quality, or secret correctness.
During preparation on 8 November 2026, both running stacks' Redis prefixes and
Qdrant collections were checked against these proposed values. The proposed
Redis prefix had no keys in databases 0 or 1, Qdrant's health endpoint returned
HTTP 200, and the proposed collection did not yet exist (HTTP 404). These are
point-in-time checks, not evidence that indexing or retrieval works.

`QUEUE_CONNECTION` and `NOTIFICATION_QUEUE_CONNECTION` must be `database` or
`redis`; the general and notification workers use their respective configured
connections. The notification connection defaults to `database` to preserve
the existing queue. `DB_QUEUE_RETRY_AFTER` and `REDIS_QUEUE_RETRY_AFTER` default
to 240 seconds and must exceed the 180-second general worker timeout. Run only
one notification worker for Fonnte validation. Worker stop-grace periods allow
the current job to finish, but cannot eliminate duplicate WhatsApp messages
after crashes or interruptions. WhatsApp remains off unless
explicitly configured and approved; see `docs/operational-notifications.md`.

The base Compose stack includes `https-portal` on ports 80/443, app on loopback port
8080, PostgreSQL, Redis and Qdrant. Ensure those ports, the database, Docker
network, proxy and backups are prepared separately before enabling deployment.
The HTTPS portal resolves the app via Docker DNS. AI streams use SSE: verify
proxy buffering/flush behavior in the actual environment before a release.

## Manual deploy after an approved cutover

First resolve the existing proxy overlay and intended database as described
above. These commands do not preserve the currently public demo route. Use the
reviewed CI artifact's immutable GHCR digest for all app containers. Authenticate
to GHCR securely if the image is private; do not put a token in shell history.
The following performs the same **read-only migration gate** before replacement;
it does not create or migrate a database. Run from the deployment directory
with the infrastructure services already healthy:

```sh
set -e
export APP_IMAGE_REF='ghcr.io/<owner>/<repository>@sha256:<tested-image-digest>'
docker compose --env-file .env.production -f compose.production.yaml --profile rag pull app
docker compose --env-file .env.production -f compose.production.yaml --profile rag run --rm --no-deps app php docker/verify-release-target.php farmer_ic_app farmer-ic-postgres redis farmer_ic_app_ http://qdrant:6333 farmer_ic_app_knowledge
docker compose --env-file .env.production -f compose.production.yaml --profile rag run --rm --no-deps app php artisan migrate:status --pending=1 --no-interaction
docker compose --env-file .env.production -f compose.production.yaml --profile rag up -d --wait --wait-timeout 120 --no-build --no-deps app worker notifications scheduler https-portal
docker compose --env-file .env.production -f compose.production.yaml --profile rag ps
```

If the gate fails, stop and investigate; do not bypass it with an automatic
`migrate`, `migrate:fresh`, or `--graceful`. Check `/up`, authenticated pages,
worker/scheduler logs, queues and AI streaming after deployment. A healthy
`/up` by itself does not establish database, queue, RAG or business correctness.
Rollback requires a compatible schema and the saved image/configuration; it
does not undo migrations or restore data automatically.

## Native development

Wayfinder output is partly gitignored. Regenerate it after route/controller
changes with `npm run routes:generate`; `--with-form` is required by the forms.
The Docker and CI builds generate these helpers independently.
