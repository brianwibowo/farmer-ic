#!/usr/bin/env python3
"""Bind a pushed GHCR digest to the repository and commit that CI tested."""

import argparse
import json
from pathlib import Path
import re
import sys


def image_name(repository):
    repository = repository.lower()
    if not re.fullmatch(r"[a-z0-9][a-z0-9._-]*/[a-z0-9][a-z0-9._-]*", repository):
        raise ValueError("Invalid GitHub repository.")
    return "ghcr.io/" + repository


def revision_value(revision):
    if not re.fullmatch(r"[0-9a-f]{40}", revision):
        raise ValueError("Invalid commit SHA.")
    return revision


def digest_value(digest):
    if not isinstance(digest, str) or not re.fullmatch(r"sha256:[0-9a-f]{64}", digest):
        raise ValueError("Invalid image digest.")
    return digest


def main():
    parser = argparse.ArgumentParser()
    subcommands = parser.add_subparsers(dest="command", required=True)
    create = subcommands.add_parser("create")
    create.add_argument("--repo-digests", type=Path, required=True)
    create.add_argument("--output", type=Path, required=True)
    verify = subcommands.add_parser("verify")
    verify.add_argument("--metadata", type=Path, required=True)
    for command in (create, verify):
        command.add_argument("--repository", required=True)
        command.add_argument("--revision", required=True)
    args = parser.parse_args()

    image = image_name(args.repository)
    revision = revision_value(args.revision)
    if args.command == "create":
        references = json.loads(args.repo_digests.read_text(encoding="utf-8"))
        if not isinstance(references, list) or any(not isinstance(ref, str) for ref in references):
            raise ValueError("Image inspect did not return repository digests.")
        matches = {ref[len(image) + 1:] for ref in references if ref.startswith(image + "@")}
        if len(matches) != 1:
            raise ValueError("The pushed image does not have exactly one matching GHCR digest.")
        digest = digest_value(matches.pop())
        args.output.write_text(json.dumps({
            "schema": 1, "image": image, "revision": revision, "digest": digest,
        }) + "\n", encoding="utf-8")
        return

    metadata = json.loads(args.metadata.read_text(encoding="utf-8"))
    if not isinstance(metadata, dict) or set(metadata) != {"schema", "image", "revision", "digest"}:
        raise ValueError("Invalid release metadata.")
    if type(metadata["schema"]) is not int or metadata["schema"] != 1:
        raise ValueError("Unsupported release metadata.")
    if metadata["image"] != image or metadata["revision"] != revision:
        raise ValueError("Release metadata does not match the tested repository and commit.")
    print(image + "@" + digest_value(metadata["digest"]))


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, TypeError, json.JSONDecodeError) as error:
        print(f"Release image metadata check failed: {error}", file=sys.stderr)
        sys.exit(1)
