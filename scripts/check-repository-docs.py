#!/usr/bin/env python3
"""Dependency-free repository-wide checks for Atlas Markdown contracts."""
from __future__ import annotations
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP = {".git", "node_modules"}
files = sorted(p for p in ROOT.rglob("*.md") if not SKIP.intersection(p.parts))
errors: list[str] = []
ids: dict[str, Path] = {}
link_re = re.compile(r"\[[^]]*]\(([^) #]+)(?:#[^)]+)?\)")
id_re = re.compile(r"^id:\s*([^\s]+)\s*$", re.MULTILINE)
route_re = re.compile(r"^\| `([a-z][a-z0-9]*(?:[.-][a-z0-9]+)*)` \|", re.MULTILINE)

for path in files:
    text = path.read_text(encoding="utf-8")
    if "\r" in text:
        errors.append(f"{path.relative_to(ROOT)}: CRLF interdit")
    match = id_re.search(text[:1000])
    if match:
        doc_id = match.group(1)
        if doc_id in ids:
            errors.append(f"ID dupliqué {doc_id}: {ids[doc_id].relative_to(ROOT)} et {path.relative_to(ROOT)}")
        ids[doc_id] = path
    for target in link_re.findall(text):
        if re.match(r"^[a-z][a-z0-9+.-]*:", target) or target.startswith("/"):
            continue
        resolved = (path.parent / target).resolve()
        try:
            resolved.relative_to(ROOT)
        except ValueError:
            errors.append(f"{path.relative_to(ROOT)}: lien hors dépôt {target}")
            continue
        if not resolved.exists():
            errors.append(f"{path.relative_to(ROOT)}: lien absent {target}")

registry = ROOT / "evolution/blueprint/route-key-registry.md"
navigation = ROOT / "evolution/blueprint/navigation.md"
if registry.exists() and navigation.exists():
    registered = set(route_re.findall(registry.read_text()))
    blocks = re.findall(r"```text\n(.*?)```", navigation.read_text(), re.DOTALL)
    declared = set(re.findall(r"^([a-z][a-z0-9.-]+)(?:\s|$)", "\n".join(blocks), re.MULTILINE))
    missing = sorted(declared - registered)
    if missing:
        errors.append("RouteKeys de navigation absentes du registre: " + ", ".join(missing))

if errors:
    print("Repository documentation checks failed:", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    sys.exit(1)
print(f"PASS: {len(files)} fichiers Markdown, {len(ids)} IDs, liens locaux et RouteKeys valides")
