"""Run stage HTTP acceptance scripts against local fake-data development servers."""
import subprocess
import sys

SCRIPTS = [
    'smoke_auth.py', 'smoke_academic.py', 'smoke_questions.py', 'smoke_exams.py',
    'smoke_attempts.py', 'smoke_results.py', 'smoke_essay.py',
    'smoke_access_code.py', 'smoke_imports.py', 'smoke_exports.py', 'smoke_audit.py',
    'smoke_versions.py', 'smoke_packages.py', 'smoke_proctor_analytics.py',
]

for script in SCRIPTS:
    cleared = subprocess.run(['php', 'spark', 'cache:clear'], capture_output=True, text=True)
    if cleared.returncode:
        print('Development cache reset failed:', cleared.stdout, cleared.stderr)
        raise SystemExit(cleared.returncode)
    result = subprocess.run([sys.executable, 'tests/' + script], capture_output=True, text=True)
    print(f'{script}: {"PASS" if result.returncode == 0 else "FAIL"}', flush=True)
    if result.returncode:
        print(result.stdout)
        print(result.stderr)
        raise SystemExit(result.returncode)
print(f'{len(SCRIPTS)} HTTP smoke scripts passed')
