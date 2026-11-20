"""S8 scoped token table and one-time copy on explicit rotation."""
import http.cookiejar
import json
import re
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


admin, teacher, student = client(), client(), client()
for agent, name in ((admin, 'admin_demo'), (teacher, 'teacher_demo'), (student, 'student_demo')):
    expect(agent, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
expect(student, 'GET', '/dashboard/exam-tokens', 403)
expect(admin, 'GET', '/dashboard/exam-tokens?status=invalid', 422)
admin_rows = expect(admin, 'GET', '/dashboard/exam-tokens?status=published', 200)
assert admin_rows and all(row['status'] == 'published' for row in admin_rows)
assert 'access_code_hash' not in json.dumps(admin_rows)
subject_rows = expect(admin, 'GET', f"/dashboard/exam-tokens?status=published&subjectId={admin_rows[0]['subject_id']}", 200)
assert subject_rows and all(int(row['subject_id']) == int(admin_rows[0]['subject_id']) for row in subject_rows)
classes = expect(admin, 'GET', '/exam-masters', 200)['classes']
class_rows = expect(admin, 'GET', f"/dashboard/exam-tokens?classGroupId={classes[0]['id']}", 200)
assert class_rows
teacher_rows = expect(teacher, 'GET', '/dashboard/exam-tokens?status=published', 200)
admin_id = int(admin_rows[0]['id'])
expect(teacher, 'POST', '/exams/999999999/access-code/rotate', 404, {'confirmed': True})
expect(admin, 'POST', f'/exams/{admin_id}/access-code/rotate', 422, {'confirmed': False})
rotated = expect(admin, 'POST', f'/exams/{admin_id}/access-code/rotate', 200, {'confirmed': True})
code = rotated['accessCode']
assert re.fullmatch('[A-Z2-9]{8}', code)
assert code not in json.dumps(expect(admin, 'GET', '/dashboard/exam-tokens?status=published', 200))
assert code not in json.dumps(expect(student, 'GET', '/student/exams', 200))
print('S8 token table scope, filters, one-time rotation and no student leak PASS')
