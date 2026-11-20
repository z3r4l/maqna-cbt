"""S8 scoped CSV export and spreadsheet formula escaping."""
import csv
import http.cookiejar
import io
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
        headers['X-CSRF-TOKEN'] = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    if payload is not None:
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(ROOT + path, data=None if payload is None else json.dumps(payload).encode(), method=method, headers=headers)
    try:
        result = agent.open(req)
    except urllib.error.HTTPError as error:
        result = error
    return result.status, result.read().decode('utf-8')


admin, teacher = client(), client()
assert call(admin, 'POST', '/auth/login', {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})[0] == 200
assert call(teacher, 'POST', '/auth/login', {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})[0] == 200
assert call(teacher, 'GET', '/exports/students')[0] == 403
assert call(admin, 'GET', '/exports/unknown')[0] == 404
code = 'F' + str(time.time_ns())
status, body = call(admin, 'POST', '/academic/subjects', {'code': code, 'name': '=1+1'})
assert status == 201, (status, body)
for kind in ('students', 'teachers', 'subjects'):
    status, body = call(admin, 'GET', '/exports/' + kind)
    assert status == 200, (kind, status, body[:150])
    rows = list(csv.DictReader(io.StringIO(body)))
    assert rows, kind
    assert 'password' not in body.lower() and '@internal.maqna.invalid' not in body
    if kind == 'subjects':
        assert next(row for row in rows if row['code'] == code)['name'] == "'=1+1"
print('S8 CSV exports scope, no secrets, formula escaping PASS')
