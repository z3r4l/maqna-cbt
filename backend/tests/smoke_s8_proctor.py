"""S8 assigned proctor login filter and targeted student session revoke."""
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


admin, proctor, student = client(), client(), client()
expect(admin, 'POST', '/auth/login', 200, {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})
student_id = next(int(row['id']) for row in expect(admin, 'GET', '/students?search=student_demo', 200) if row['username'] == 'student_demo')
exams = expect(admin, 'GET', '/exams?perPage=100', 200)
exam_id = next(int(exam['id']) for exam in exams if exam['status'] == 'published' and any(int(row['student_id']) == student_id for row in expect(admin, 'GET', f"/exams/{exam['id']}/assignments", 200)))
before_sessions = {int(row['id']) for row in expect(admin, 'GET', f'/students/{student_id}/sessions', 200)}
expect(student, 'POST', '/auth/login', 200, {'identity': 'student_demo', 'password': 'FixtureOnly123!'})
expect(student, 'GET', '/auth/me', 200)
new_session = next(int(row['id']) for row in expect(admin, 'GET', f'/students/{student_id}/sessions', 200) if int(row['id']) not in before_sessions)
username = 'proctor.' + str(time.time_ns())[-12:]
created = expect(admin, 'POST', '/proctors', 201, {'username': username, 'email': username + '@example.invalid', 'displayName': 'Pengawas S8'})
expect(admin, 'POST', f'/exams/{exam_id}/proctors', 200, {'userId': int(created['userId'])})
expect(proctor, 'POST', '/auth/login', 200, {'identity': username, 'password': created['temporaryPassword']})
assigned = expect(proctor, 'GET', '/proctor/exams', 200)
assert any(int(row['id']) == exam_id for row in assigned)
expect(proctor, 'GET', '/reports/exams', 403)
all_rows = expect(proctor, 'GET', f'/exams/{exam_id}/proctor', 200)['rows']
yes_rows = expect(proctor, 'GET', f'/exams/{exam_id}/proctor?loginStatus=yes', 200)['rows']
no_rows = expect(proctor, 'GET', f'/exams/{exam_id}/proctor?loginStatus=no', 200)['rows']
assert len(yes_rows) + len(no_rows) == len(all_rows)
assert all(int(row['has_logged_in']) == 1 for row in yes_rows)
assert any(int(session['id']) == new_session for row in all_rows if int(row['student_id']) == student_id for session in row['sessions'])
expect(proctor, 'POST', f'/exams/{exam_id}/proctor/students/{student_id}/sessions/{new_session}/revoke', 422, {'confirmed': False, 'reason': 'Pengujian pengawas kelas'})
expect(proctor, 'POST', f'/exams/{exam_id}/proctor/students/{student_id}/sessions/{new_session}/revoke', 200, {'confirmed': True, 'reason': 'Pengujian pengawas kelas'})
status, body = call(student, 'GET', '/auth/me')
assert status == 401 and body['error']['code'] == 'SESSION_REVOKED'
print('S8 assigned proctor filter, scope, no grading, targeted revoke PASS')
