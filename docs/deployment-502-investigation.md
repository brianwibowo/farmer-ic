# Deployment 502 investigation

## Observation: 16 November 2026

Read-only SSH inspection of `farmer.icdigital.io` found the production checkout
at `7840f692d744e85cc9e5b9764138ba03f314d51d`, not this branch. The running
app, general worker and scheduler started on 13 November at 07:27:50–07:27:51
UTC; notifications restarted on 16 November at 03:30:35 UTC. All were healthy
at inspection. The app and workers used the same image digest. The general
worker has a 190-second stop grace period and a 180-second job timeout;
notifications have 40 and 30 seconds respectively. Grace periods are maximum
shutdown allowances, not evidence that shutdown actually took that long.

Proxy logs contain connection-refused errors to `172.19.0.9:80` on 12 November
at 06:16:34–06:16:35 UTC. These establish an unavailable upstream for those
requests, not the full duration of an outage or its cause. Current container
logs do not preserve the replaced worker's shutdown, and the available Docker
event query did not provide relevant lifecycle events. The alleged three-minute
worker delay and its relationship to 502s therefore remain unproven. At inspection
both the public `/up` via the local TLS proxy and direct loopback `/up` returned
200 (approximately 63 ms and 11 ms); this is only a point-in-time check.

The repository workflow previously issued one `compose up --wait` for app,
workers, scheduler and proxy. Compose may replace services concurrently and
`--wait` concerns completion of the whole group, so its elapsed time is not
itself the duration of the web outage. The proxy routes to one named app
container. Replacement necessarily has a period without a ready old or new
container; its 10-second DNS resolver cache can also affect cutover. The
Dockerfile `/up` health check is a basic Laravel boot check, not proof that
database, queues, login or every application dependency works. Worker checks
only inspect the process command line. The entrypoint builds caches before
starting Apache or a worker.

## Change and operational constraints

The workflow now replaces app and proxy, waits for their Compose health and
checks `/up` through the proxy, then replaces worker, notifications and
scheduler. If web validation fails, consumers remain on the old release; if
the subsequent worker stage fails, web may already be on the new release.
Investigate and recover explicitly rather than assuming an atomic rollback.
This makes worker shutdown independent of web *startup sequencing*, but it does
not eliminate the single-app interruption or prove an improvement in measured
502 duration. Old workers may process jobs produced by new web code during the
transition: require backward/forward-compatible queued payloads, database
changes and side effects. The notification consumer must remain single-instance;
do not overlap it. Migrations remain an explicitly reviewed operation.

The existing `DEPLOYMENT.md` describes earlier stack and cutover observations,
which may be stale. Confirm the actual domain, proxy, target database, current
workflow enablement and intended stack before any deployment. The initial
inspection made no changes; the later, explicitly authorized live proxy test
and its restoration are recorded below.

## Verification during a separately approved deployment

1. Record the old/new immutable image digests, Git commits, Compose version,
   deployment start/end and UTC clock. Verify compatibility of both code
   versions, schema and queued jobs; retain the previous image and configuration.
2. Before rollout, start timestamped Docker event capture for app, workers and
   proxy (create/start/stop/kill/die/health), and retain old container logs.
   Collect entrypoint timestamps, queue job start/end, exit codes and signal
   events without recording secrets or sensitive payloads.
3. From an independent client, sample the public HTTPS `/up` at least once per
   second with a bounded timeout, timestamp, HTTP status and latency. Separately
   sample the direct app endpoint and a representative authenticated flow using
   approved test credentials. Include failures/timeouts, not just successful
   responses. Correlate proxy upstream errors, DNS resolution and container
   lifecycle. Keep monitoring before and after the full rollout.
4. Compare baseline and staged deployments under comparable job/load
   conditions, including a controlled long-running job on an isolated staging
   queue. Report the failure count, longest consecutive failure interval,
   web-ready time and worker stop/start interval; do not infer cause from a
   grace-period value. Ensure no duplicate notifications.
5. Check worker health, queues, scheduler, logs and the public endpoint after
   both stages. On failure use the reviewed incident/rollback plan; switching
   web alone may be unsafe if jobs or database changes are incompatible.

## Blue-green implementation and limitations

Run two independently addressable web slots with immutable images and separate
container names, while keeping a stable proxy and shared, compatible database,
encryption key, sessions, cache and persistent uploads. Assess shared storage
startup writes (`chown`, cached views and storage link), release-specific
assets and session compatibility before allowing concurrent versions.
Capacity must accommodate both slots. Do not start duplicate schedulers or
notification consumers; roll workers independently only after compatibility
has been established.

Start the inactive slot without modifying live traffic; verify its own health,
dependency-aware readiness and representative application flows directly.
Switch the proxy through a validated, atomic configuration reload or a
health-aware load balancer, accounting for Docker DNS caching, connection
draining, WebSockets and in-flight requests. Keep the old slot available and
monitor externally through the switch. If verification fails, switch back to
the old upstream and verify again; rollback requires old code, assets, schema
and queued jobs to remain compatible. Use expand/contract database changes and
an explicit recovery plan for irreversible writes. Do not automatically
remove the old slot or storage.

The repository now contains `web-blue`, `web-green` and `release-router` in
`compose.production.yaml`, the opt-in proxy override `compose.blue-green.yaml`,
and `docker/blue-green/deploy.sh`. The router persists its selected route in a
Docker volume. Both slots share application storage, sessions and uploads,
but have separate compiled-view volumes. Missing hashed assets under
`/build/assets/` fall back to the previous slot; older releases beyond that
one-slot window are not guaranteed. Keep the previous slot running.

`deploy.sh deploy` requires a tested image digest and verifies that the live
proxy already reaches the router and reports its actual active slot. It starts
only the inactive slot, waits for Docker health, checks its `/up`, switches the
router by atomic file rename and Nginx reload, and checks HTTP 200 and the slot
header through the public TLS proxy. It restores the prior route if the switch
or public verification fails. `deploy.sh rollback` switches back to the retained
previous slot after checking its health. Neither action rolls back workers,
database writes or migrations. Do not rollback without confirming code, schema,
job and side-effect compatibility. Deploy workflow preflights remain in place;
workers and the scheduler are changed after web verification, so a later worker
failure leaves new web active and needs operator intervention.

The `/up` check is not dependency-aware application verification. The workflow
checks the database connection, namespaces and migration status before the
switch, but does not verify an authenticated session, uploads, every external
service or in-flight WebSockets. Nginx reload drains old workers, but individual
long-lived connections have not been tested. Concurrent versions must remain
compatible with shared sessions, queues, schema, and storage. The previous slot
does not provide an automatic response to later runtime failures.

### One-time proxy cutover

The live domain currently uses the legacy direct route. The GitHub production
deployment variable was observed enabled and the deployed `main` workflow
still uses the old single-app rollout. **Do not leave the domain routed through
the new router while that old workflow can run:** it can recreate the proxy
and app. First review and merge the new workflow, pause automatic deployment
while performing the cutover, and confirm no older deploy is running. The new
workflow deliberately fails closed until public traffic goes through the router.
Do not force-checkout the VPS or remove its untracked demo files.

Once the reviewed code is on the VPS, from its deployment directory, provide
the existing production environment and current app image digest, then start
only the router:

```sh
export APP_IMAGE_REF="$(docker inspect --format '{{.Config.Image}}' farmer-ic-app-1)"
docker compose --env-file .env.production -f compose.production.yaml -f compose.blue-green.yaml --profile rag --profile blue-green up -d --wait --no-deps release-router
docker exec farmer-ic-release-router-1 wget -S -O /dev/null http://127.0.0.1/up
```

Confirm the response is 200 and the slot header is `legacy`, and retain the
existing app. The following explicit cutover recreates HTTPS Portal, so it may
itself produce downtime; sample the public endpoint continuously and prepare
the rollback command before proceeding:

```sh
docker compose --env-file .env.production -f compose.production.yaml -f compose.blue-green.yaml --profile rag --profile blue-green up -d --wait --no-deps https-portal
sh docker/blue-green/deploy.sh deploy
```

Verify the public 200 response and `X-Farmer-Release-Slot` header, representative
authenticated flows, asset loading and worker status. The initial command uses
the current image, not a new release. Restore automatic deployment only after
confirming that the reviewed blue-green workflow is the one that will run.
On a failed initial cutover, first restore the existing proxy route with the
base Compose file and verify the public endpoint:

```sh
docker compose --env-file .env.production -f compose.production.yaml --profile rag up -d --wait --no-deps https-portal
```

For subsequent releases, use the workflow after its CI checks. Manual rollback
uses `sh docker/blue-green/deploy.sh rollback` with the same production
environment; verify compatible workers and data first. A failed public check
causes an attempted route restoration but can still require intervention if the
router or public proxy is unhealthy. No routine command should remove volumes,
old slots, unrelated services, or orphans.

### Additional inspection: initial proxy cutover

Before cutover, the live HTTPS Portal Nginx configuration sets its backend directly
to `http://farmer-ic-app-1:80` inside
`/etc/nginx/conf.d/farmer.icdigital.io.ssl.conf`. Introducing a stable
blue-green traffic selector therefore also requires an initial proxy cutover;
merely starting a second app cannot redirect traffic. HTTPS Portal generates
its Nginx configuration, so an ad-hoc edit inside the running container is not
a durable switch mechanism. Account for how its configuration is regenerated
and persisted across container recreation before choosing the selector.

The local Docker daemon was unavailable during the initial inspection; it was
subsequently reachable with Docker Desktop 29.9.3 using the CLI under the user's
local application directory. The existing Compose configuration test passed,
and `tests/Deployment/blue-green-proxy-experiment.py` exercised an isolated
Nginx proxy with two mock Nginx backends, direct candidate probing, reload,
rollback and 50 ms request sampling. The experiment observed no failed
requests in that short run; it does not use Laravel, HTTPS Portal, actual
production configuration, or a real deployment. The disposable containers and
network were removed by the test. The test now also uses the repository router
configuration and checks previous-slot asset fallback.

### Live VM candidate probe: 16 November 2026

With the user's permission, a uniquely named temporary app container was
started on the existing stack with the exact image reference of the running app,
without published ports or additional dependencies. Its first direct HTTP
probe failed while Apache was starting; a subsequent `/up` probe returned 200.
At that moment Docker still reported its health as `starting`, so this was not
proof of Docker health or dependency readiness. The temporary container was
removed afterward. The active app and public proxy were not switched or
restarted. This only confirms that the current image can start a second app
process with the current configuration; it does not verify a new image,
switching, rollback, sessions, assets, or deployment availability. The
container's normal entrypoint did operate on the shared storage volume.

### Live VM proxy and rollback test: 16 November 2026

The user authorized a live test. The actual current image was started in
`web-blue`, reached Docker `healthy`, and returned 200 directly. A
`release-router` container and its persistent route volume were started on the
same Compose network. Through the existing HTTPS Portal, traffic was switched
from legacy to blue, verified by HTTP status and slot header, and switched back
to legacy. The HTTPS Portal was then restored to its original direct upstream.
The production deployment variable was temporarily disabled during this test
and restored to `true` afterward. The final public `/up` returned 200.

Sampling covered the complete experiment, including *both* recreations of
HTTPS Portal for initial cutover and restoration: 319 samples, 21 non-200.
These aggregated results cannot isolate the duration or source of failures
during the blue/legacy Nginx reload itself. They demonstrate that the initial
proxy transition is not zero-downtime, not that future switches are outage-free.
The test used the same image in both slots, not a CI release or a full
authenticated flow. A second router-only test injected a failed post-switch
verification and confirmed restoration to legacy; its public check was
simulated and did not traverse HTTPS Portal. A later router-only run also
exercised legacy → blue → green → blue rollback, using the current image in
both slots; the public proxy check was simulated for that run. The router was
then restored to legacy. The router and both slots remain running on the VM but
are not currently receiving public traffic. Their
preparation files are in `/home/clouduser/farmer-ic-bluegreen-prep`; existing
untracked demo files were not modified.
