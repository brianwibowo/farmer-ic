#!/bin/sh
# Source from the deployment script. Keep retries bounded and before rollout.
registry_retry() {
    registry_attempt=1
    registry_delay=3
    while :; do
        if "$@"; then
            return 0
        fi
        if [ "$registry_attempt" -ge 3 ]; then
            echo "Registry operation failed after $registry_attempt attempts." >&2
            return 1
        fi
        echo "Registry operation failed (attempt $registry_attempt/3); retrying in ${registry_delay}s." >&2
        sleep "$registry_delay"
        registry_attempt=$((registry_attempt + 1))
        registry_delay=$((registry_delay * 2))
    done
}
