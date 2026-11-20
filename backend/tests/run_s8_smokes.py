"""Run S8 HTTP acceptance scripts sequentially against the local dev fixture DB."""
import subprocess
import sys
from pathlib import Path

root = Path(__file__).parent
scripts = [
    'smoke_s8_masters.py', 'smoke_s8_assignments.py', 'smoke_s8_cards.py',
    'smoke_s8_login_status.py', 'smoke_s8_revoke_session.py', 'smoke_s8_exports.py',
    'smoke_s8_staff_imports.py', 'smoke_s8_staff_xlsx.py', 'smoke_s8_xlsx_security.py',
    'smoke_s8_tokens.py', 'smoke_s8_bulk_people.py', 'smoke_s8_proctor.py',
]
for script in scripts:
    cleared = subprocess.run(['php', 'spark', 'cache:clear'], cwd=root.parent, capture_output=True, text=True)
    if cleared.returncode:
        print('Development cache reset failed:', cleared.stdout, cleared.stderr)
        raise SystemExit(cleared.returncode)
    result = subprocess.run([sys.executable, str(root / script)], cwd=root.parent, capture_output=True, text=True)
    print(f'{script}: {"PASS" if result.returncode == 0 else "FAIL"}', flush=True)
    if result.returncode:
        print(result.stdout)
        print(result.stderr)
        raise SystemExit(result.returncode)
print(f'{len(scripts)} S8 HTTP acceptance scripts passed')
