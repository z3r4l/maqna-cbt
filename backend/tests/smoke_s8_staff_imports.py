"""S8 CSV preview and atomic commit for subjects and teachers."""
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


def call(agent, method, path, data=None, content_type=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        headers['X-CSRF-TOKEN'] = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    if content_type:
        headers['Content-Type'] = content_type
    request = urllib.request.Request(ROOT + path, data=data, method=method, headers=headers)
    try:
        result = agent.open(request)
    except urllib.error.HTTPError as error:
        result = error
    return result.status, json.load(result)


def expect(agent, method, path, code, data=None, content_type=None):
    status, body = call(agent, method, path, data, content_type)
    assert status == code, (method, path, status, body.get('error', body.get('data')))
    return body.get('data')


def upload(agent, kind, mode, rows):
    out = io.StringIO()
    csv.writer(out).writerows(rows)
    boundary = 's8-' + str(time.time_ns())
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="mode"\r\n\r\n{mode}\r\n'
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{kind}.csv"\r\nContent-Type: text/csv\r\n\r\n').encode() + out.getvalue().encode() + f'\r\n--{boundary}--\r\n'.encode()
    return expect(agent, 'POST', f'/imports/{kind}/preview', 201, body, 'multipart/form-data; boundary=' + boundary)


admin, teacher = client(), client()
for agent, name in ((admin, 'admin_demo'), (teacher, 'teacher_demo')):
    expect(agent, 'POST', '/auth/login', 200, json.dumps({'identity': name, 'password': 'FixtureOnly123!'}).encode(), 'application/json')
expect(teacher, 'GET', '/imports/teachers/template', 403)
suffix = str(time.time_ns())[-12:]
subject_code = 'S' + suffix
subject_header = ['code', 'name']
bad = upload(admin, 'subjects', 'reject', [subject_header, [subject_code, '']])
assert bad['counts']['error'] == 1
expect(admin, 'POST', f"/imports/subjects/{bad['id']}/commit", 409)
subject = upload(admin, 'subjects', 'reject', [subject_header, [subject_code, 'Mapel S8 ' + suffix]])
assert subject['counts']['ready'] == 1
expect(admin, 'POST', f"/imports/subjects/{subject['id']}/commit", 200)
duplicate = upload(admin, 'subjects', 'skip', [subject_header, [subject_code, 'Mapel S8 ' + suffix]])
assert duplicate['counts']['skip'] == 1
username = 's8.' + suffix
teacher_header = ['employee_no', 'name', 'username', 'email', 'subject_code', 'class_group_code', 'semester_name']
teacher_row = ['EMP-' + suffix, 'Guru S8', username, username + '@example.invalid', subject_code, 'VII-A', 'Ganjil']
job = upload(admin, 'teachers', 'reject', [teacher_header, teacher_row])
assert job['counts']['ready'] == 1, job
result = expect(admin, 'POST', f"/imports/teachers/{job['id']}/commit", 200)
assert len(result['credentials']) == 1
assignments = expect(admin, 'GET', '/teaching-assignments?perPage=100', 200)
assert any(row['teacher_username'] == username and row['subject_name'] == 'Mapel S8 ' + suffix for row in assignments)
multi_username = 's8.multi.' + suffix
multi_base = ['EMP-M-' + suffix, 'Guru Banyak Kelas', multi_username, multi_username + '@example.invalid', subject_code]
multi = upload(admin, 'teachers', 'reject', [teacher_header,
    multi_base + ['VII-A', 'Ganjil'], multi_base + ['VII-B', 'Ganjil']])
assert multi['counts']['ready'] == 2, multi
multi_result = expect(admin, 'POST', f"/imports/teachers/{multi['id']}/commit", 200)
assert len(multi_result['credentials']) == 1
with admin.open(ROOT + '/exports/teachers') as response:
    assert response.status == 200
    exported = list(csv.DictReader(io.StringIO(response.read().decode('utf-8'))))
assert {row['class_group_code'] for row in exported if row['username'] == multi_username} == {'VII-A', 'VII-B'}
first = 's8.first.' + suffix
second = 's8.second.' + suffix
race = upload(admin, 'teachers', 'reject', [teacher_header,
    ['EMP-A-' + suffix, 'Guru Pertama', first, first + '@example.invalid', subject_code, 'VII-A', 'Ganjil'],
    ['EMP-B-' + suffix, 'Guru Kedua', second, second + '@example.invalid', subject_code, 'VII-A', 'Ganjil']])
assert race['counts']['ready'] == 2
expect(admin, 'POST', '/teachers', 201, json.dumps({'username': second, 'displayName': 'Akun lain', 'employeeNo': 'OTHER-' + suffix, 'email': second + '@example.invalid'}).encode(), 'application/json')
expect(admin, 'POST', f"/imports/teachers/{race['id']}/commit", 409)
first_rows = expect(admin, 'GET', '/teachers?search=' + first, 200)
assert not any(row['username'] == first for row in first_rows)
print('S8 subject/teacher preview, duplicate, commit, assignment PASS')
