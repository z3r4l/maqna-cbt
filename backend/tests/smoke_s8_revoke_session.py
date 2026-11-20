"""S8 targeted student session revoke with reason, audit, and re-login."""
import http.cookiejar
import json
import subprocess
import urllib.error
import urllib.request

ROOT = 'http://127.0.0.1:8081/api/v1'


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(agent, method, path, payload=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        headers['X-CSRF-TOKEN'] = call(agent, 'GET', '/auth/csrf')[1]['data']['token']
    if payload is not None:
        headers['Content-Type'] = 'application/json'
    request = urllib.request.Request(ROOT + path, data=None if payload is None else json.dumps(payload).encode(), method=method, headers=headers)
    try:
        result = agent.open(request)
    except urllib.error.HTTPError as error:
        result = error
    return result.status, json.load(result)


def expect(agent, method, path, code, payload=None):
    status, body = call(agent, method, path, payload)
    assert status == code, (method, path, status, body.get('error', body.get('data')))
    return body.get('data')


def attempt_count():
    return int(subprocess.check_output(['mysql', '-u', 'root', '-N', 'maqna_cbt_dev', '-e', 'SELECT COUNT(*) FROM attempts'], text=True).strip())


admin, student, teacher = client(), client(), client()
expect(admin, 'POST', '/auth/login', 200, {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})
expect(teacher, 'POST', '/auth/login', 200, {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})
students = expect(admin, 'GET', '/students?search=student_demo', 200)
student_id = next(int(row['id']) for row in students if row['username'] == 'student_demo')
expect(teacher, 'GET', f'/students/{student_id}/sessions', 403)
before_sessions = {int(row['id']) for row in expect(admin, 'GET', f'/students/{student_id}/sessions', 200)}
expect(student, 'POST', '/auth/login', 200, {'identity': 'student_demo', 'password': 'FixtureOnly123!'})
profile = expect(student, 'GET', '/auth/me', 200)
sessions = expect(admin, 'GET', f'/students/{student_id}/sessions', 200)
current = next(row for row in sessions if int(row['id']) not in before_sessions)
session_id = int(current['id'])
before = attempt_count()
expect(admin, 'POST', f'/students/{student_id}/sessions/{session_id}/revoke', 422, {'confirmed': False, 'reason': 'Tes tanpa konfirmasi'})
expect(admin, 'POST', f'/students/{student_id}/sessions/{session_id}/revoke', 200, {'confirmed': True, 'reason': 'Pengujian pencabutan sesi terpilih'})
expect(admin, 'POST', f'/students/{student_id}/sessions/{session_id}/revoke', 409, {'confirmed': True, 'reason': 'Pengujian pencabutan sesi terpilih'})
status, body = call(student, 'GET', '/auth/me')
assert status == 401 and body['error']['code'] == 'SESSION_REVOKED', (status, body)
assert attempt_count() == before
expect(student, 'POST', '/auth/login', 200, {'identity': 'student_demo', 'password': 'FixtureOnly123!'})
expect(student, 'GET', '/auth/me', 200)
assert profile['roles'] == ['student']
print('S8 targeted session revoke, audit path, attempt preservation, re-login PASS')
