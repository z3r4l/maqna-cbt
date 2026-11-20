"""S8 login history filter uses successful Shield logins and school scope."""
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
        result = agent.open(request)
    except urllib.error.HTTPError as error:
        result = error
    return result.status, json.load(result)


admin, teacher = client(), client()
assert call(admin, 'POST', '/auth/login', {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})[0] == 200
assert call(teacher, 'POST', '/auth/login', {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})[0] == 200
assert call(teacher, 'GET', '/students?loginStatus=yes')[0] == 403
assert call(admin, 'GET', '/students?loginStatus=invalid')[0] == 422
all_status, all_body = call(admin, 'GET', '/students?perPage=100')
assert all_status == 200
yes_status, yes_body = call(admin, 'GET', '/students?loginStatus=yes&perPage=100')
no_status, no_body = call(admin, 'GET', '/students?loginStatus=no&perPage=100')
assert yes_status == no_status == 200
assert yes_body['meta']['total'] + no_body['meta']['total'] == all_body['meta']['total']
assert all(int(row['has_logged_in']) == 1 for row in yes_body['data'])
assert all(int(row['has_logged_in']) == 0 for row in no_body['data'])
print('S8 student login history scope, validation, filter PASS')
