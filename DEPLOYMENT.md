# Docker and VPS deployment

The production image builds the Vue assets and PHP dependencies in separate
stages, then runs Laravel from Apache with PHP 8.5. The app binds to
`127.0.0.1:8080`; an existing reverse proxy can terminate HTTPS for
`farmer-ic.duckdns.org`. The production Compose file also joins the app to the
external `outline_default` network (override with `PROXY_NETWORK`) so a proxy
running in the Outline Compose project can reach the app at
`http://farmer-ic-app:80`. SQLite and uploaded files live in named volumes;
the application key is supplied through the deployment secret.

## Run with Docker locally

```sh
cp .env.docker.example .env.docker
docker compose --env-file .env.docker up --build
```

The app is available at <http://localhost:8080>. Stop it with
`docker compose down`; add `-v` only when you intentionally want to remove the
SQLite and storage volumes. For a stable local session across container
recreation, add an `APP_KEY` value to `.env.docker`.

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

The SSH password and application key are read only from GitHub Secrets. Do not
put them in this repository, workflow files, or a committed `.env` file.

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
