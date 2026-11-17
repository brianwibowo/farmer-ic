#!/usr/bin/env python3
"""Exercise the registry retry helper without a registry or credentials."""

from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / "docker" / "registry-retry.sh"


def run_case(failures):
    with tempfile.TemporaryDirectory() as directory:
        counter = Path(directory) / "attempts"
        script = r"""
            . "$1"
            counter=$2
            failures=$3
            sleep() { :; }
            operation() {
                count=0
                if [ -f "$counter" ]; then count=$(cat "$counter"); fi
                count=$((count + 1))
                printf '%s' "$count" > "$counter"
                [ "$count" -gt "$failures" ]
            }
            registry_retry operation
        """
        result = subprocess.run(
            ["sh", "-c", script, "retry-test", str(HELPER), str(counter), str(failures)],
            capture_output=True, text=True, check=False,
        )
        return result.returncode, int(counter.read_text())


def main():
    assert run_case(0) == (0, 1)
    assert run_case(2) == (0, 3)
    status, attempts = run_case(3)
    assert status != 0 and attempts == 3
    print("Registry retry checks passed.")


if __name__ == "__main__":
    main()
