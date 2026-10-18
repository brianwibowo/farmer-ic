# Docker and VPS deployment

The production image builds the Vue assets and PHP dependencies in separate
stages, then runs Laravel from Apache with PHP 8.5. The app binds to
`127.0.0.1:8080`; an existing reverse proxy can terminate HTTPS for
`farmer-ic.duckdns.org`. The production Compose file also joins the app to the
external `outline_default` network (override with `PROXY_NETWORK`) so a proxy
running in the Outline Compose project can reach the app at
`http://farmer-ic-app:80`. SQLite and uploaded files live in named volumes;
the application key is supplied through the deployment secret.

The AI message endpoint uses Server-Sent Events. The included Caddy config
routes those responses with buffering disabled so generated text can reach the
browser incrementally. If another reverse proxy is used, configure its
equivalent streaming/flush behavior for `/{team}/ai/messages`.

## Run with Docker locally

```sh
cp .env.docker.example .env.docker
docker compose --env-file .env.docker up --build
```

The app is available at <http://localhost:8080>. Stop it with
`docker compose down`; add `-v` only when you intentionally want to remove the
SQLite and storage volumes. For a stable local session across container
recreation, add an `APP_KEY` value to `.env.docker`.

Add the OpenRouter key to `.env.docker` before starting the container. The
file is ignored by Git and is read by Compose at runtime:

```dotenv
OPENROUTER_API_KEY=your-server-side-key
# Optional OpenRouter enterprise alias or model ID approved for this account.
# Leave empty to use the built-in high-quality default.
AI_ENTERPRISE_MODEL=
AI_PRIMARY_MODEL=google/gemini-3.8-flash
AI_TEXT_FALLBACK_MODELS=google/gemini-3.7-flash,openai/gpt-6-luna
AI_VISION_FALLBACK_MODELS=google/gemini-3.7-flash,inclusionai/ling-3.0-flash-vl
AI_STT_MODEL=qwen/qwen3.8-omni-flash
AI_STT_FALLBACK_MODELS=google/gemini-3.8-flash
AI_TTS_MODEL=
AI_TTS_VOICE=
AI_VOICE_ENABLED=true
```

## GitHub configuration

The `CI` workflow runs PHP, TypeScript, lint, tests, and the Vite build. The
`Deploy` workflow runs only after a successful `CI` run on `main`, fetches the
tested commit on the VPS, builds the Docker image there, and restarts the app.

Create these **GitHub Actions secrets** before enabling deployment:

| Secret            | Purpose                                                        |
| ----------------- | -------------------------------------------------------------- |
| `VPS_HOST`        | VPS hostname or IP address                                     |
| `VPS_PORT`        | SSH port (for example `12323`)                                 |
| `VPS_USER`        | SSH user                                                       |
| `VPS_PASSWORD`    | SSH password, if password authentication is enabled            |
| `VPS_DEPLOY_PATH` | Absolute directory for Compose files, such as `/home/clouduser/farmer-ic` |
| `APP_KEY`         | Laravel key generated with `php artisan key:generate --show`   |
| `OPENROUTER_API_KEY` | OpenRouter key used by the server-side AI integration          |

The SSH password and application key are read only from GitHub Secrets. Do not
put them in this repository, workflow files, or a committed `.env` file.

For AI features, provide `OPENROUTER_API_KEY` through the runtime environment
file on the server. Keep it server-only; it is never a `VITE_` variable.

The deploy workflow preserves `.env.production` between releases. Create it
once on the VPS with mode `600` and keep the runtime configuration there:

```sh
cd /srv/farmer-ic
touch .env.production
chmod 600 .env.production
nano .env.production
```

At minimum it should contain `APP_URL`, `APP_KEY`,
`OPENROUTER_API_KEY`, and the model settings from the local example above.
For production, set `AI_ENTERPRISE_MODEL` to a model ID or enterprise alias
that is enabled for the OpenRouter account. The legacy
`AI_PRIMARY_MODEL=google/gemini-3.8-flash` value is automatically routed to
that enterprise setting; an explicitly configured non-legacy primary model
still takes precedence. Verify every fallback ID against the account's
OpenRouter model list before deployment.
The workflow supplies `APP_KEY` from GitHub Secrets only when the file does
not already contain one. When `OPENROUTER_API_KEY` is present as a GitHub
Secret, the workflow writes it to `.env.production` without exposing it to the
frontend. A manually managed `.env.production` remains supported.

To deploy manually after updating `.env.production`:

```sh
docker compose --env-file .env.production -f compose.production.yaml build --pull app
docker compose --env-file .env.production -f compose.production.yaml up -d --no-build --remove-orphans app
docker compose --env-file .env.production -f compose.production.yaml logs --tail=100 app
```

The container entrypoint runs migrations, caches config/routes/views, and
creates the private storage link during startup. Verify the application health
endpoint at `/up` and then open `https://your-domain/{team-slug}/ai` after
logging in.

## VPS bootstrap

Install Docker Engine and the Compose plugin on the VPS, then create the
deployment directory and grant the SSH user access to Docker:

```sh
sudo mkdir -p /srv/farmer-ic
sudo chown -R "$USER":"$USER" /srv/farmer-ic
sudo usermod -aG docker "$USER"
```

Log in again so the group change takes effect. The default deploy starts only
the app and binds it to `127.0.0.1:8080`. If Nginx or Apache already owns ports
80/443, proxy the domain to that port. Check the current owner with:

```sh
sudo ss -ltnp | grep -E ':(80|443)\\b'
```

If the existing proxy is `https-portal`, add this route to its `DOMAINS`
setting and recreate that service:

```text
farmer-ic.duckdns.org -> http://farmer-ic-app:80
```

The proxy and app must share the network named by `PROXY_NETWORK` (the default
is `outline_default`).

The first successful `Deploy` workflow clones the repository, checks out the
tested commit, builds on the VPS, and starts the app. To use the included Caddy
instead of an existing web server, first free ports 80/443, open them in the
firewall, then run:

```sh
cd /home/clouduser/farmer-ic
docker compose --env-file .env.production --profile proxy -f compose.production.yaml up -d caddy
```
