#!/bin/sh
# Run on the deployment host, from the repository root. The initial proxy
# cutover is a separate operation; this script fails closed until it is done.
set -eu

mode=${1:-deploy}
case "$mode" in deploy|rollback) ;; *) echo 'Use deploy or rollback.' >&2; exit 2 ;; esac
command -v flock >/dev/null
exec 9>"${HOME:?}/.farmer-ic-blue-green.lock"
flock -n 9 || { echo 'Another blue-green operation is running.' >&2; exit 1; }

router=farmer-ic-release-router-1
compose() {
    docker compose --env-file .env.production -f compose.production.yaml \
        -f compose.blue-green.yaml --profile rag --profile blue-green "$@"
}
container_for() {
    case "$1" in
        legacy) printf '%s' farmer-ic-app-1 ;;
        blue) printf '%s' farmer-ic-web-blue-1 ;;
        green) printf '%s' farmer-ic-web-green-1 ;;
        *) echo "Invalid slot: $1" >&2; return 1 ;;
    esac
}
slot_from_host() {
    case "$1" in
        farmer-ic-app-1) printf '%s' legacy ;;
        farmer-ic-web-blue-1) printf '%s' blue ;;
        farmer-ic-web-green-1) printf '%s' green ;;
        *) echo "Invalid upstream: $1" >&2; return 1 ;;
    esac
}
public_path_is() {
    headers=$(curl -fsSk --max-time 5 -o /dev/null -D - -w '\nHTTP_STATUS:%{http_code}\n' \
        --resolve farmer.icdigital.io:443:127.0.0.1 \
        "https://farmer.icdigital.io$2") || return 1
    clean=$(printf '%s\n' "$headers" | tr -d '\r')
    printf '%s\n' "$clean" | grep -Fx 'HTTP_STATUS:200' >/dev/null || return 1
    printf '%s\n' "$clean" | grep -Fxi "X-Farmer-Release-Slot: $1" >/dev/null
}
public_is() {
    public_path_is "$1" /up && public_path_is "$1" /login
}
wait_public() {
    expected=$1
    attempt=0
    while [ "$attempt" -lt 15 ]; do
        if public_is "$expected"; then return 0; fi
        attempt=$((attempt + 1))
        sleep 2
    done
    return 1
}
ready() {
    container=$(container_for "$1")
    health=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$container")
    [ "$health" = healthy ] || { echo "$container is not healthy ($health)." >&2; return 1; }
    code=$(docker exec "$container" curl -fsS -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1/up)
    [ "$code" = 200 ] || { echo "$container returned HTTP $code." >&2; return 1; }
    code=$(docker exec "$container" curl -fsS -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1/login)
    [ "$code" = 200 ] || { echo "$container login returned HTTP $code." >&2; return 1; }
}

docker inspect "$router" >/dev/null
route=$(docker exec "$router" cat /etc/nginx/release-route/active.conf)
active=$(printf '%s\n' "$route" | sed -n 's/^set \$release_slot \(legacy\|blue\|green\);$/\1/p')
active_backend=$(printf '%s\n' "$route" | sed -n 's|^set \$release_backend http://\([a-z0-9-]*\):80;$|\1|p')
previous_host=$(printf '%s\n' "$route" | sed -n 's|^set \$release_previous http://\([a-z0-9-]*\):80;$|\1|p')
case "$active" in legacy|blue|green) ;; *) echo 'Invalid router state; refusing deployment.' >&2; exit 1 ;; esac
[ "$active_backend" = "$(container_for "$active")" ] || {
    echo 'Router slot and active upstream disagree; refusing deployment.' >&2; exit 1;
}
[ -n "$previous_host" ] || { echo 'Missing previous upstream.' >&2; exit 1; }
previous=$(slot_from_host "$previous_host")
public_is "$active" || { echo 'Public route does not match router state; refusing deployment.' >&2; exit 1; }

if [ "$mode" = deploy ]; then
    : "${APP_IMAGE_REF:?Set APP_IMAGE_REF to the tested immutable image}"
    printf '%s\n' "$APP_IMAGE_REF" | grep -Eq '@sha256:[0-9a-f]{64}$' || {
        echo 'An immutable sha256 image digest is required.' >&2; exit 1;
    }
    case "$active" in legacy|green) target=blue ;; blue) target=green ;; esac
    compose up -d --wait --wait-timeout 120 --no-build --no-deps "web-$target"
else
    target=$previous
    [ "$target" != "$active" ] || { echo 'No previous slot is available.' >&2; exit 1; }
fi
ready "$target"
target_host=$(container_for "$target")
active_host=$(container_for "$active")

# Save a copy inside the persistent volume before the atomic rename. If any
# command or the public check fails, restore and reload the previous route.
docker exec "$router" cp /etc/nginx/release-route/active.conf /etc/nginx/release-route/rollback.conf
switched=1
restore() {
    if [ "$switched" = 1 ]; then
        echo 'Switch failed; restoring the previous route.' >&2
        docker exec "$router" sh -ec 'cp /etc/nginx/release-route/rollback.conf /etc/nginx/release-route/restore.tmp; mv /etc/nginx/release-route/restore.tmp /etc/nginx/release-route/active.conf; nginx -t; nginx -s reload' || true
        if ! wait_public "$active"; then
            echo 'Rollback could not be verified; manual intervention required.' >&2
        fi
    fi
}
trap restore EXIT
trap 'exit 1' HUP INT TERM
docker exec "$router" sh -ec '
    printf "set \$release_backend http://%s:80;\nset \$release_previous http://%s:80;\nset \$release_slot %s;\n" "$1" "$2" "$3" > /etc/nginx/release-route/active.tmp
    mv /etc/nginx/release-route/active.tmp /etc/nginx/release-route/active.conf
    nginx -t
    nginx -s reload
' sh "$target_host" "$active_host" "$target"
wait_public "$target" || { echo 'Candidate is not responding through the public proxy.' >&2; exit 1; }
switched=0
trap - EXIT HUP INT TERM
echo "Public traffic verified on $target; previous slot $active is retained."
