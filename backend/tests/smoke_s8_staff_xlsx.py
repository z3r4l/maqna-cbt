"""S8 valid XLSX preview and commit for student, teacher, and subject."""
import http.cookiejar
import io
import json
import time
import urllib.error
import urllib.request
import openpyxl

ROOT = 'http://127.0.0.1:8081/api/v1'
agent = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(method, path, body=None, content_type=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        headers['X-CSRF-TOKEN'] = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    if content_type:
        headers['Content-Type'] = content_type
    request = urllib.request.Request(ROOT + path, data=body, method=method, headers=headers)
    try:
        response = agent.open(request)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.load(response)


def upload(kind, rows):
    book = openpyxl.Workbook()
    for row in rows:
        book.active.append(row)
    stream = io.BytesIO()
    book.save(stream)
    boundary = 's8-xlsx-' + str(time.time_ns())
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="mode"\r\n\r\nreject\r\n'
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{kind}.xlsx"\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n').encode() + stream.getvalue() + f'\r\n--{boundary}--\r\n'.encode()
    status, response = call('POST', f'/imports/{kind}/preview', body, 'multipart/form-data; boundary=' + boundary)
    assert status == 201 and response['data']['counts']['ready'] == 1, (kind, status, response)
    job_id = response['data']['id']
    status, committed = call('POST', f'/imports/{kind}/{job_id}/commit')
    assert status == 200 and committed['data']['created'] == 1, (kind, status, committed)


assert call('POST', '/auth/login', json.dumps({'identity': 'admin_demo', 'password': 'FixtureOnly123!'}).encode(), 'application/json')[0] == 200
suffix = str(time.time_ns())[-12:]
subject_code = 'X' + suffix
upload('subjects', [['code', 'name'], [subject_code, 'Mapel XLSX S8']])
upload('students', [['nis', 'name', 'username', 'email', 'class_group_code'], ['NIS-X-' + suffix, 'Siswa XLSX S8', 'xlsx.student.' + suffix, '', 'VII-A']])
upload('teachers', [['employee_no', 'name', 'username', 'email', 'subject_code', 'class_group_code', 'semester_name'], ['EMP-X-' + suffix, 'Guru XLSX S8', 'xlsx.teacher.' + suffix, 'xlsx.teacher.' + suffix + '@example.invalid', subject_code, 'VII-A', 'Ganjil']])
print('S8 XLSX student, teacher, subject preview/commit PASS')
