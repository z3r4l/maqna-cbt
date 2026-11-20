#!/usr/bin/env python3
"""Validate editable maqna-cbt plan. Stdlib only. Usage from repo root: python3 plan/scripts/validate_plan.py"""
from pathlib import Path
import json, csv, sys, re
base=Path(__file__).resolve().parent.parent
errors=[]
try:
    control=json.loads((base/'control.json').read_text(encoding='utf-8'))
except Exception as e:
    print('ERROR: Cannot read control.json:',e);sys.exit(1)
try:
    progress=json.loads((base/'PROGRESS.json').read_text(encoding='utf-8'))
except Exception as e:
    print('ERROR: Cannot read PROGRESS.json:',e);sys.exit(1)
with (base/'FEATURE_MATRIX.csv').open(encoding='utf-8',newline='') as f:
    items=list(csv.DictReader(f))
features=[f'FR-{i:02d}' for i in range(1,39)]
stages=[f'S{i}' for i in range(8)]
if len(items)!=38:errors.append('FEATURE_MATRIX.csv must have exactly 38 features')
if sorted(r['feature_id'] for r in items)!=sorted(features):errors.append('Missing/duplicate feature IDs in matrix')
if set(control.get('features',{}))!=set(features):errors.append('control.json feature keys must equal FR-01..FR-38')
if not all(isinstance(v,bool) for v in control.get('features',{}).values()):errors.append('feature flags must be boolean')
enabled=control.get('execution',{}).get('enabled_stages',[])
if not isinstance(enabled,list) or any(s not in stages for s in enabled) or len(enabled)!=len(set(enabled)):
    errors.append('enabled_stages invalid or duplicate')
stop=control.get('execution',{}).get('stop_after_stage')
if stop is not None and stop not in stages:errors.append('stop_after_stage invalid')
if stop is not None and stop not in enabled:errors.append('stop_after_stage must be enabled')
if control.get('configuration',{}).get('result_publication')!='manual_only':errors.append('result_publication must be manual_only per owner decision')
for s in stages:
    if not (base/'stages'/f'{s}.md').is_file():errors.append('Missing stage '+s)
    if s not in progress.get('stages',{}):errors.append('Missing stage progress '+s)
for fr in features:
    if fr not in progress.get('features',{}):errors.append('Missing feature progress '+fr)
for p in [base/'references'/'FRS_SEKOLAH_MADRASAH_v1.1.md',base/'references'/'architecture'/'MAQNA_CBT_ERD_PERMISSION_MODULES_v1.2.md',base/'references'/'design'/'MAQNA_CBT_DESIGN_SYSTEM.md']:
    if not p.is_file():errors.append('Missing reference '+str(p))
for md in [base/'references'/'architecture'/'MAQNA_CBT_ERD_PERMISSION_MODULES_v1.2.md',base/'references'/'design'/'MAQNA_CBT_DESIGN_SYSTEM.md']:
    text=md.read_text(encoding='utf-8')
    for ref in re.findall(r'!\[[^\]]*\]\(([^)]+)\)',text):
        if not ref.startswith(('http://','https://')) and not (md.parent/ref).is_file():errors.append('Missing local image '+str(md.parent/ref))
print(f'Plan: {len(items)} features; {len(enabled)}/{len(stages)} enabled stages; current stage {progress.get("current_stage")}')
if errors:
    for x in errors:print('ERROR:',x)
    sys.exit(1)
print('PASS: plan schema, refs, stage and feature IDs valid (NOT an application test).')
