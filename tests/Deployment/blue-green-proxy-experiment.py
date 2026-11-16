#!/usr/bin/env python3
"""Disposable Nginx proxy experiment, not a production rollout test.

Requires a running Docker daemon and nginx:1.27-alpine. Creates only uniquely
named containers and a network, then removes those exact resources.
"""

import subprocess
import threading
import time
import urllib.request
import uuid
from pathlib import Path


IMAGE = "nginx:1.27-alpine"
PREFIX = "farmer-bg-experiment-" + uuid.uuid4().hex[:12]
NETWORK = PREFIX
BLUE = PREFIX + "-blue"
GREEN = PREFIX + "-green"
PROXY = PREFIX + "-proxy"


def docker(*args, check=True, input=None):
    return subprocess.run(
        ["docker", *args], check=check, input=input, text=True,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    ).stdout.strip()


def switch(target, previous, label):
    conf = (
        f"set $release_backend http://{target}:80;\n"
        f"set $release_previous http://{previous}:80;\n"
        f"set $release_slot {label};\n"
    )
    docker("exec", "-i", PROXY, "sh", "-c",
           "cat > /etc/nginx/release-route/active.conf", input=conf)
    docker("exec", PROXY, "nginx", "-t")
    docker("exec", PROXY, "nginx", "-s", "reload")


def main():
    failures = []
    seen = set()
    stop = threading.Event()
    thread = None
    try:
        docker("network", "create", NETWORK)
        for name, label in ((BLUE, "blue"), (GREEN, "green")):
            docker("run", "-d", "--name", name, "--network", NETWORK, IMAGE,
                   "sh", "-c", f"echo {label} > /usr/share/nginx/html/index.html && exec nginx -g 'daemon off;'")
        docker("exec", BLUE, "sh", "-c",
               "mkdir -p /usr/share/nginx/html/build/assets && echo old-asset > /usr/share/nginx/html/build/assets/old.js")
        docker("run", "-d", "--name", PROXY, "--network", NETWORK,
               "-p", "127.0.0.1::80", IMAGE)
        docker("exec", PROXY, "mkdir", "-p", "/etc/nginx/release-route")
        docker("exec", "-i", PROXY, "sh", "-c",
               "cat > /etc/nginx/conf.d/default.conf",
               input=(Path(__file__).resolve().parents[2] / "docker/blue-green/router.conf").read_text())
        port = docker("port", PROXY, "80/tcp").rsplit(":", 1)[1]
        url = f"http://127.0.0.1:{port}/"
        switch(BLUE, BLUE, "blue")

        def read():
            with urllib.request.urlopen(url, timeout=2) as response:
                return response.read().decode().strip()

        for _ in range(50):
            if read() == "blue":
                break
            time.sleep(0.1)
        else:
            raise RuntimeError("Initial blue slot did not respond")
        # Green readiness must be checked directly, not through the active proxy.
        if docker("exec", GREEN, "wget", "-qO-", "http://127.0.0.1/") != "green":
            raise RuntimeError("Green slot did not pass its direct readiness check")

        def sample():
            while not stop.is_set():
                try:
                    value = read()
                    seen.add(value)
                    if value not in ("blue", "green"):
                        failures.append(f"unexpected response: {value!r}")
                except Exception as exc:
                    failures.append(str(exc))
                time.sleep(0.05)

        thread = threading.Thread(target=sample)
        thread.start()
        time.sleep(1)
        switch(GREEN, BLUE, "green")
        time.sleep(2)
        if read() != "green":
            raise RuntimeError("Green switch failed")
        with urllib.request.urlopen(url + "build/assets/old.js", timeout=2) as response:
            if response.read().decode().strip() != "old-asset":
                raise RuntimeError("Previous release asset fallback failed")
        switch(BLUE, GREEN, "blue")
        time.sleep(2)
        if read() != "blue":
            raise RuntimeError("Blue rollback failed")
        stop.set()
        thread.join()
        if failures or seen != {"blue", "green"}:
            raise RuntimeError(f"Sampling failed: seen={seen}, failures={failures[:5]}")
        print("Mock Nginx switch and rollback passed; no sampled request failed.")
    finally:
        stop.set()
        if thread and thread.is_alive():
            thread.join()
        for name in (PROXY, GREEN, BLUE):
            docker("rm", "-f", name, check=False)
        docker("network", "rm", NETWORK, check=False)


if __name__ == "__main__":
    main()
