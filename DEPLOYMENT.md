# Docker and VPS deployment

The production image builds the Vue assets and PHP dependencies in separate
stages, then runs Laravel from Apache with PHP 8.5. Caddy terminates HTTPS for
`farmer-ic.duckdns.org` and proxies requests to the app container. SQLite and
uploaded files live in named volumes; the application key is supplied through
the deployment secret so a new image can be rolled out without losing state.

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

The `CI` workflow runs PHP, TypeScript, lint, tests, the Vite build, and a
Docker image build for every pull request and push to `main`. The `Deploy`
workflow runs only after a successful `CI` run on `main`, publishes an image to
GHCR, then updates the VPS with the tested commit.

Create these **GitHub Actions secrets** before enabling deployment:

| Secret            | Purpose                                                        |
| ----------------- | -------------------------------------------------------------- |
| `VPS_HOST`        | VPS hostname or IP address                                     |
| `VPS_PORT`        | SSH port (for example `12323`)                                 |
| `VPS_USER`        | SSH user                                                       |
| `VPS_PASSWORD`    | SSH password, if password authentication is enabled            |
| `VPS_DEPLOY_PATH` | Absolute directory for Compose files, such as `/srv/farmer-ic` |
| `GHCR_USERNAME`   | GitHub username used by the VPS to pull the private image      |
| `GHCR_TOKEN`      | Fine-grained token with package read permission                |
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

Log in again so the group change takes effect. Open HTTP and HTTPS in the VPS
firewall so Caddy can obtain and renew the certificate:

```sh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

The first successful `Deploy` workflow copies `compose.production.yaml` and
`Caddyfile`, creates `.env.production`, logs in to GHCR, and starts the app.
Caddy then serves `https://farmer-ic.duckdns.org` with an automatically managed
TLS certificate. The app itself remains bound to `127.0.0.1:8080`.
