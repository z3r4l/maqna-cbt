"""EXT-07 audit scope and secret redaction on fake development records."""
import http.cookiejar
import json
import urllib.error
import urllib.request

ROOT = 'http://127.0.0.1:8081/api/v1'


def session(identity):
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    csrf = json.load(client.open(ROOT + '/auth/csrf'))['data']
    data = json.dumps({'identity': identity, 'password': 'FixtureOnly123!'}).encode()
    client.open(urllib.request.Request(ROOT + '/auth/login', data=data, headers={csrf['header']: csrf['token'], 'Content-Type': 'application/json'})).read()
    return client


admin, teacher, student = session('admin_demo'), session('teacher_demo'), session('student_demo')
admin_rows = json.load(admin.open(ROOT + '/audit-events'))['data']
teacher_rows = json.load(teacher.open(ROOT + '/audit-events'))['data']
assert admin_rows and teacher_rows
assert any(row['action'] == 'imports.commit' for row in admin_rows)
assert any(row['action'] == 'exams.publish' for row in teacher_rows)
assert all(row['username'] == 'teacher_demo' for row in teacher_rows)
for row in admin_rows + teacher_rows:
    serialized = json.dumps(row).lower()
    assert 'temporarypassword' not in serialized and 'token_hash' not in serialized and 'fixtureonly123' not in serialized
try:
    student.open(ROOT + '/audit-events')
    raise AssertionError('Student could read audit events')
except urllib.error.HTTPError as error:
    assert error.code == 403
print('EXT-07 audit smoke passed')
