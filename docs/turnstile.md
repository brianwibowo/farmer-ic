# Cloudflare Turnstile

The existing widget protects sign-in (`login`) and password-reset link requests
(`forgot_password`). Laravel verifies each token with Cloudflare before Fortify
handles the request. Tokens must be fresh, have the matching action and come
from a hostname in the deployment's allowlist. Missing configuration, an
invalid response, or an unavailable Siteverify service denies the request.

Configure these server environment variables through the deployment's secret
management process, without committing secrets:

- `TURNSTILE_SITE_KEY`: the public site key, `0x4AAAAAAGVMcSuhJ_SFt5M8`.
- `TURNSTILE_SECRET`: the corresponding widget secret, stored as a secret.
- `TURNSTILE_HOSTNAMES`: comma-separated exact frontend hostnames for that
  deployment, without schemes, ports, or paths. Production must not include
  `localhost`, `127.0.0.1`, or `::1`; use a separate local allowlist for development.

Confirm that each hostname is permitted by the existing Cloudflare widget and
that browsers can load `https://challenges.cloudflare.com`. If the deployment
uses a Content Security Policy, allow the necessary Cloudflare script and frame
sources as described in Cloudflare's Turnstile documentation. Keep the secret
server-side; never use a `VITE_` variable for it. Rebuild Laravel's configuration
cache after changing environment values and restart long-running workers.
The production container entrypoint refuses to start if any of the three
variables is empty. Configure them in `.env.production` before starting a new
release; the existing Compose `env_file` passes them to the application. The
entrypoint rebuilds the configuration cache on startup. Do not bake the secret
or a populated configuration cache into the image. Restrict access to the
environment file, containers, and generated cache, which contain the secret.

Verification calls have a ten-second timeout and fail closed. Laravel logs a
generic warning for network or upstream HTTP failures without the token or
secret. Both endpoints share a limit of 30 verification attempts per client IP
per minute, in addition to Fortify's login limiter. In a multi-instance
deployment the configured cache must be shared across instances for that limit
to apply globally. Confirm that proxy trust is restricted to infrastructure
you control; otherwise forwarded client addresses may be spoofed.

Before rollout, test both forms with a fresh real widget token on an approved
hostname, and confirm a replay is rejected. Also check that missing or incorrect
configuration denies submissions. Automated tests fake Siteverify and do not
replace this live check. Configure the secret before deploying this change:
otherwise sign-in and password-reset link requests will be unavailable.
Cloudflare's replay detection is eventually consistent; a repeat arriving very
soon after the first redemption can succeed. Do not depend on Turnstile alone
for strict, immediate single-use enforcement in high-value operations.
