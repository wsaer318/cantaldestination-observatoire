#!/usr/bin/env python3
"""Simple bundler for CantalDestination front assets."""
from __future__ import annotations
from pathlib import Path
from datetime import datetime, timezone

BUNDLES = {
    "static/dist/infographie.bundle.js": [
        "static/js/utils.js",
        "static/js/config.js",
        "static/js/flux-vision-config.js",
        "static/js/filters_loader.js",
        "static/js/infographie.js",
    ],
}

HEADER = "// Auto-generated on {date} -- Do not edit manually\n"


def build_bundle(destination: str, sources: list[str]) -> None:
    dest_path = Path(destination)
    dest_path.parent.mkdir(parents=True, exist_ok=True)

    parts: list[str] = []
    for src in sources:
        src_path = Path(src)
        if not src_path.exists():
            raise FileNotFoundError(f"Source file not found: {src}")
        content = src_path.read_text(encoding="utf-8")
        parts.append(f"// ---- begin {src} ----\n")
        parts.append(content.rstrip())
        parts.append(f"\n// ---- end {src} ----\n")

    banner = HEADER.format(date=datetime.now(timezone.utc).isoformat(timespec="seconds"))
    bundled = banner + "\n".join(parts) + "\n"
    dest_path.write_text(bundled, encoding="utf-8")
    print(f"Built {destination} from {len(sources)} sources (size: {len(bundled)} bytes)")


def main() -> None:
    for dest, sources in BUNDLES.items():
        build_bundle(dest, sources)


if __name__ == "__main__":
    main()

