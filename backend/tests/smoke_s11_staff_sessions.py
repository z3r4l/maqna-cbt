"""S11 staff role session limits revoke the oldest session without changing accounts."""
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


admin, first, second = client(), client(), client()
expect(admin, 'POST', '/auth/login', 200, {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})
original = next(row['maxSessions'] for row in expect(admin, 'GET', '/school/session-policies', 200) if row['role'] == 'teacher')
try:
    expect(admin, 'PUT', '/school/session-policies/teacher', 200, {'maxSessions': 1})
    expect(first, 'POST', '/auth/login', 200, {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})
    expect(second, 'POST', '/auth/login', 200, {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})
    expect(first, 'GET', '/auth/me', 401)
    expect(second, 'GET', '/auth/me', 200)
finally:
    expect(admin, 'PUT', '/school/session-policies/teacher', 200, {'maxSessions': original})
print('S11 staff session limit and oldest-session revocation PASS')
