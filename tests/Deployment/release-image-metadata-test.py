#!/usr/bin/env python3
"""Exercise release metadata without a registry, Docker daemon or secrets."""

import json
from pathlib import Path
import subprocess
import sys
import tempfile


SCRIPT = Path(__file__).resolve().parents[2] / "docker" / "release-image-metadata.py"
REPOSITORY = "Example/Farmer-IC"
IMAGE = "ghcr.io/example/farmer-ic"
REVISION = "a" * 40
DIGEST = "sha256:" + "b" * 64


def call(*args):
    return subprocess.run([sys.executable, str(SCRIPT), *args], capture_output=True, text=True, check=False)


def main():
    with tempfile.TemporaryDirectory(prefix="farmer-release-metadata-") as directory:
        root = Path(directory)
        digests = root / "digests.json"
        metadata = root / "metadata.json"
        digests.write_text(json.dumps([IMAGE + "@" + DIGEST]), encoding="utf-8")
        common = ["--repository", REPOSITORY, "--revision", REVISION]
        created = call("create", *common, "--repo-digests", str(digests), "--output", str(metadata))
        if created.returncode:
            raise SystemExit("Failed to record the tested digest.")
        checked = call("verify", *common, "--metadata", str(metadata))
        if checked.returncode or checked.stdout.strip() != IMAGE + "@" + DIGEST:
            raise SystemExit("Failed to verify the tested digest.")

        if call("verify", "--repository", "other/repository", "--revision", REVISION, "--metadata", str(metadata)).returncode == 0:
            raise SystemExit("Accepted a different repository.")
        if call("verify", "--repository", REPOSITORY, "--revision", "c" * 40, "--metadata", str(metadata)).returncode == 0:
            raise SystemExit("Accepted a different commit.")
        value = json.loads(metadata.read_text(encoding="utf-8"))
        value["digest"] = "sha256:not-a-digest"
        metadata.write_text(json.dumps(value), encoding="utf-8")
        if call("verify", *common, "--metadata", str(metadata)).returncode == 0:
            raise SystemExit("Accepted an invalid digest.")
        value["digest"] = DIGEST
        value["schema"] = True
        metadata.write_text(json.dumps(value), encoding="utf-8")
        if call("verify", *common, "--metadata", str(metadata)).returncode == 0:
            raise SystemExit("Accepted an invalid metadata version.")
        digests.write_text(json.dumps(["ghcr.io/other/repository@" + DIGEST]), encoding="utf-8")
        if call("create", *common, "--repo-digests", str(digests), "--output", str(metadata)).returncode == 0:
            raise SystemExit("Accepted a digest from a different image.")
        digests.write_text(json.dumps([IMAGE + "@" + DIGEST, IMAGE + "@sha256:" + "c" * 64]), encoding="utf-8")
        if call("create", *common, "--repo-digests", str(digests), "--output", str(metadata)).returncode == 0:
            raise SystemExit("Accepted ambiguous image digests.")

    print("Release image metadata checks passed.")


if __name__ == "__main__":
    main()
