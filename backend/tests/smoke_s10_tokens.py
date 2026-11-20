"""S10 dynamic token authorization, rotation, and student start."""
import http.cookiejar
import json
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
        response = agent.open(request)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.load(response)


def expect(agent, method, path, status, payload=None):
    actual, body = call(agent, method, path, payload)
    assert actual == status, (method, path, actual, body)
    return body.get('data')


admin, student = client(), client()
expect(admin, 'POST', '/auth/login', 200, {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})
expect(student, 'POST', '/auth/login', 200, {'identity': 'student_demo', 'password': 'FixtureOnly123!'})
rows = expect(admin, 'GET', '/dashboard/exam-tokens?status=published', 200)
exam_id = int(rows[0]['id'])
expect(student, 'GET', f'/exams/{exam_id}/access-code/current', 403)
expect(admin, 'PATCH', f'/exams/{exam_id}/access-code/policy', 422, {'mode': 'dynamic'})
try:
    enabled = expect(admin, 'PATCH', f'/exams/{exam_id}/access-code/policy', 200, {'mode': 'dynamic', 'confirmed': True})
    assert enabled['mode'] == 'dynamic' and len(enabled['accessCode']) == 8
    current = expect(admin, 'GET', f'/exams/{exam_id}/access-code/current', 200)
    assert current['accessCode'] == enabled['accessCode'] and current['expiresAt']
    assert 'accessCode' not in json.dumps(expect(admin, 'GET', '/dashboard/exam-tokens?status=published', 200))
    assert 'accessCode' not in json.dumps(expect(student, 'GET', '/student/exams', 200))
    rotated = expect(admin, 'POST', f'/exams/{exam_id}/access-code/rotate', 200, {'confirmed': True})
    assert rotated['accessCode'] != current['accessCode']
finally:
    expect(admin, 'PATCH', f'/exams/{exam_id}/access-code/policy', 200, {'mode': 'none', 'confirmed': True})
print('S10 token policy, scope, current, rotation, no student leak PASS')
