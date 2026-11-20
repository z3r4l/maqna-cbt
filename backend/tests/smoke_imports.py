"""EXT-01 CSV/XLSX preview, row errors, duplicate mode and atomic commit."""
import csv
import http.cookiejar
import io
import json
import time
import urllib.error
import urllib.request
import openpyxl

ROOT = 'http://127.0.0.1:8081/api/v1'


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(client, method, path, data=None, content_type=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        csrf = call(client, 'GET', '/auth/csrf')[1]['data']
        headers[csrf['header']] = csrf['token']
    if content_type:
        headers['Content-Type'] = content_type
    request = urllib.request.Request(ROOT + path, data=data, method=method, headers=headers)
    try:
        response = client.open(request)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.loads(response.read())


def expect(label, client, method, path, code, data=None, content_type=None):
    status, result = call(client, method, path, data, content_type)
    print(label, status)
    assert status == code, (label, status, result)
    return result.get('data')


def json_body(value):
    return json.dumps(value).encode(), 'application/json'


def upload(client, kind, mode, name, content, code=201):
    boundary = 'maqna-import-boundary-' + str(time.time_ns())
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="mode"\r\n\r\n{mode}\r\n'
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    return expect('preview ' + kind, client, 'POST', f'/imports/{kind}/preview', code, body, 'multipart/form-data; boundary=' + boundary)


admin, teacher, student = session(), session(), session()
for client, name in [(admin, 'admin_demo'), (teacher, 'teacher_demo'), (student, 'student_demo')]:
    expect(name + ' login', client, 'POST', '/auth/login', 200, *json_body({'identity': name, 'password': 'FixtureOnly123!'}))
for kind, client in [('students', admin), ('questions', teacher)]:
    with client.open(ROOT + f'/imports/{kind}/template') as response:
        assert response.status == 200 and response.read().startswith(b'nis' if kind == 'students' else b'subject_code')
try:
    student.open(ROOT + '/imports/students/template')
    raise AssertionError('Student imported without admin permission')
except urllib.error.HTTPError as error:
    assert error.code == 403

suffix = str(time.time_ns())[-10:]
username = 'imp.' + suffix
student_header = ['nis', 'name', 'username', 'email', 'class_group_code']


def csv_bytes(rows):
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerows(rows)
    return output.getvalue().encode()


invalid = upload(admin, 'students', 'reject', 'students.csv', csv_bytes([student_header, ['NIS-' + suffix, '', username, '', 'VII-A']]))
assert invalid['counts']['error'] == 1 and invalid['rows'][0]['rowNumber'] == 2
expect('invalid commit denied', admin, 'POST', f"/imports/students/{invalid['id']}/commit", 409)
valid_student = upload(admin, 'students', 'reject', 'students.csv', csv_bytes([student_header, ['NIS-' + suffix, '=2+2', username, '', 'VII-A']]))
assert valid_student['counts'] == {'ready': 1, 'skip': 0, 'error': 0}
committed = expect('student commit', admin, 'POST', f"/imports/students/{valid_student['id']}/commit", 200)
assert committed['created'] == 1 and len(committed['credentials']) == 1 and committed['credentials'][0]['temporaryPassword']
assert expect('idempotent commit', admin, 'POST', f"/imports/students/{valid_student['id']}/commit", 200)['credentials'] == []
duplicate = upload(admin, 'students', 'skip', 'students.csv', csv_bytes([student_header, ['NIS-' + suffix, '=2+2', username, '', 'VII-A']]))
assert duplicate['counts']['skip'] == 1
expect('skip commit', admin, 'POST', f"/imports/students/{duplicate['id']}/commit", 200)

question_header = ['subject_code', 'body', 'type', 'weight', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'rubric', 'explanation']
question_row = ['MAT', 'Soal impor XLSX ' + suffix, 'mcq', '1', 'Benar', 'Salah', '', '', 'A', '', 'Uji impor']
book = openpyxl.Workbook()
sheet = book.active
sheet.append(question_header)
sheet.append(question_row)
output = io.BytesIO()
book.save(output)
question_job = upload(teacher, 'questions', 'reject', 'questions.xlsx', output.getvalue())
assert question_job['counts']['ready'] == 1
expect('question commit', teacher, 'POST', f"/imports/questions/{question_job['id']}/commit", 200)
same = upload(teacher, 'questions', 'skip', 'questions.csv', csv_bytes([question_header, question_row]))
assert same['counts']['skip'] == 1
expect('skip question commit', teacher, 'POST', f"/imports/questions/{same['id']}/commit", 200)

rollback_a = 'imp.rollback.a.' + suffix
rollback_b = 'imp.rollback.b.' + suffix
race = upload(admin, 'students', 'reject', 'students.csv', csv_bytes([student_header,
    ['NIS-RA-' + suffix, 'Rollback A', rollback_a, '', 'VII-A'],
    ['NIS-RB-' + suffix, 'Rollback B', rollback_b, '', 'VII-A']]))
assert race['counts']['ready'] == 2
expect('concurrent duplicate account', admin, 'POST', '/students', 201, *json_body({'username': rollback_b, 'displayName': 'Existing B', 'nis': 'OTHER-' + suffix}))
expect('atomic import rollback', admin, 'POST', f"/imports/students/{race['id']}/commit", 409)
people = expect('people after rollback', admin, 'GET', '/students?search=' + rollback_a, 200)
assert all(row['username'] != rollback_a for row in people)
print('EXT-01 import smoke passed')
