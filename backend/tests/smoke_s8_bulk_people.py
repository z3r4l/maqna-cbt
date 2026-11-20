"""S8 scoped preview and confirmed soft bulk deactivation on fake users."""
import http.cookiejar
import json
import time
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


admin, teacher = client(), client()
for agent, name in ((admin, 'admin_demo'), (teacher, 'teacher_demo')):
    expect(agent, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
suffix = str(time.time_ns())[-12:]
ids = []
for number in (1, 2):
    result = expect(admin, 'POST', '/students', 201, {'username': f'bulk.{number}.{suffix}', 'displayName': f'Bulk Student {number}', 'nis': f'BULK-{number}-{suffix}'})
    ids.append(int(result['person']['id']))
expect(teacher, 'POST', '/people/bulk/students/preview', 403, {'ids': ids})
expect(admin, 'POST', '/people/bulk/students/preview', 422, {'ids': [ids[0], ids[0]]})
preview = expect(admin, 'POST', '/people/bulk/students/preview', 201, {'ids': ids})
assert preview['count'] == 2 and not preview['blocked']
path = f"/people/bulk/students/{preview['id']}/commit"
expect(admin, 'POST', path, 409, {'confirmed': True, 'confirmationText': 'NONAKTIFKAN 1', 'reason': 'Pengujian nonaktif massal'})
expect(admin, 'POST', path, 409, {'confirmed': False, 'confirmationText': 'NONAKTIFKAN 2', 'reason': 'Pengujian nonaktif massal'})
result = expect(admin, 'POST', path, 200, {'confirmed': True, 'confirmationText': 'NONAKTIFKAN 2', 'reason': 'Pengujian nonaktif massal'})
assert result['deactivated'] == 2
expect(admin, 'POST', path, 409, {'confirmed': True, 'confirmationText': 'NONAKTIFKAN 2', 'reason': 'Pengujian nonaktif massal'})
for student_id in ids:
    row = expect(admin, 'GET', f'/students/{student_id}', 200)
    assert row['status'] == 'inactive' and int(row['account_active']) == 0
teachers = expect(admin, 'GET', '/teachers?search=teacher_demo', 200)
teacher_id = next(int(row['id']) for row in teachers if row['username'] == 'teacher_demo')
blocked = expect(admin, 'POST', '/people/bulk/teachers/preview', 201, {'ids': [teacher_id]})
assert blocked['blocked']
expect(admin, 'POST', f"/people/bulk/teachers/{blocked['id']}/commit", 409, {'confirmed': True, 'confirmationText': 'NONAKTIFKAN 1', 'reason': 'Pengujian guru dengan tugas aktif'})
print('S8 bulk preview, double confirmation, soft deactivate, active teacher guard PASS')
