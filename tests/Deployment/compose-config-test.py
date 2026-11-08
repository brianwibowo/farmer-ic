#!/usr/bin/env python3
"""Validate production Compose isolation with disposable, non-secret settings."""

import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


ROOT = Path(__file__).resolve().parents[2]
SETTINGS = {
    "APP_KEY": "ci-only-key",
    "DB_CONNECTION": "pgsql",
    "DB_DATABASE": "isolated_ci_db",
    "POSTGRES_PASSWORD": "ci-only-password",
    "REDIS_PREFIX": "isolated_ci_",
    "QDRANT_COLLECTION": "isolated_ci_knowledge",
}


def main():
    # Prevent developer-shell settings from taking precedence over this fixture.
    prefixes = (
        "APP_", "DB_", "POSTGRES_", "REDIS_", "QDRANT_", "QUEUE_",
        "NOTIFICATION_", "OPENROUTER_", "SESSION_", "CACHE_", "COMPOSE_",
    )
    env = {key: value for key, value in os.environ.items() if not key.upper().startswith(prefixes)}

    with tempfile.TemporaryDirectory(prefix="farmer-compose-test-") as directory:
        path = Path(directory)
        shutil.copyfile(ROOT / "compose.production.yaml", path / "compose.production.yaml")

        def configure(settings):
            (path / ".env.production").write_text(
                "".join(f"{key}={value}\n" for key, value in settings.items()), encoding="utf-8"
            )
            return subprocess.run(
                ["docker", "compose", "--env-file", ".env.production", "-f", "compose.production.yaml",
                 "--profile", "rag", "config", "--format", "json"],
                cwd=path, env=env, capture_output=True, text=True, check=False,
            )

        result = configure(SETTINGS)
        if result.returncode:
            raise SystemExit("Compose rejected the disposable test configuration.")
        config = json.loads(result.stdout)
        volume_name = config["volumes"]["app_release_storage"]["name"]
        if volume_name == f'{config["name"]}_app_storage':
            raise SystemExit("New and legacy application storage must be distinct.")

        for service in ("app", "worker", "notifications", "scheduler"):
            definition = config["services"][service]
            environment = definition["environment"]
            for key in ("DB_CONNECTION", "DB_DATABASE", "REDIS_PREFIX", "QDRANT_COLLECTION"):
                if environment.get(key) != SETTINGS[key]:
                    raise SystemExit(f"{service} does not use the isolated {key} setting.")
            mounts = [mount for mount in definition["volumes"] if mount["target"] == "/var/www/html/storage"]
            if len(mounts) != 1 or mounts[0]["source"] != "app_release_storage":
                raise SystemExit(f"{service} does not use the new application storage volume.")

        image_ref = "ghcr.io/example/farmer-ic@sha256:" + "a" * 64
        digest_config = configure({**SETTINGS, "APP_IMAGE_REF": image_ref, "APP_IMAGE": "old-image", "APP_TAG": "old-tag"})
        if digest_config.returncode:
            raise SystemExit("Compose rejected the immutable image reference.")
        digest_services = json.loads(digest_config.stdout)["services"]
        for service in ("app", "worker", "notifications", "scheduler"):
            if digest_services[service]["image"] != image_ref:
                raise SystemExit(f"{service} does not use the tested image digest.")

        for required in ("REDIS_PREFIX", "QDRANT_COLLECTION"):
            result = configure({key: value for key, value in SETTINGS.items() if key != required})
            if result.returncode == 0 or required not in result.stderr:
                raise SystemExit(f"Compose did not reject a missing {required}.")

    print("Production Compose isolation checks passed.")


if __name__ == "__main__":
    main()
