#!/usr/bin/env python3
"""Validasi statis paket ekstensi; aman dijalankan tanpa instalasi dependensi."""
from pathlib import Path
import csv, json, sys

BASE = Path(__file__).resolve().parents[1]
PROJECT = BASE.parents[1]
conf = json.loads((BASE / "CONTROL.json").read_text(encoding="utf-8"))
with (BASE / "GAP_MATRIX.csv").open(encoding="utf-8", newline="") as f:
    rows = list(csv.DictReader(f))
ids = [x["id"] for x in rows]
assert len(rows) == 48, f"Expected 48 candidate features, got {len(rows)}"
assert len(ids) == len(set(ids)), "Duplicate IDs"
assert set(ids) == set(conf["features"]), "Control vs matrix mismatch"
for row in rows:
    assert row["enabled_by_default"] == str(conf["features"][row["id"]]).lower(), row["id"]
    assert (BASE / "stages" / (row["stage"] + ".md")).exists(), row["id"]
for s in conf["execution"]["enabled_stages"]:
    assert s in ["S8", "S9", "S10", "S11"]
for p in ["README.md", "GAP_OVERVIEW.md", "BASELINE_AUDIT.md", "PROGRESS.md", "PERMISSIONS_DELTA.md", "DATA_MODEL_DELTA.md", "API_UI_DELTA.md", "ACCEPTANCE_TESTS.md", "reference/AUDIT_RYUEXAM_2026-11-15.md"]:
    assert (BASE / p).exists(), p
assert (PROJECT / "ONE_SHOT_RYUEXAM_EXTENSION_PROMPT.md").exists(), "Master prompt missing"
print(f"OK: {len(rows)} gap candidates, {sum(conf['features'].values())} enabled, {len(rows)-sum(conf['features'].values())} disabled, stages S8-S11")
