"""S10 bulk exam status preview/commit and scoped answer export."""
import http.cookiejar
import io
import json
import urllib.error
import urllib.request
from openpyxl import load_workbook

ROOT = 'http://127.0.0.1:8081/api/v1'


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(agent, method, path, payload=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        headers['X-CSRF-TOKEN'] = call(agent, 'GET', '/auth/csrf')[1]['data']['token']
    if payload is not None:
        headers['Content-Type'] = 'application/json'
    request = urllib.request.Request(ROOT + path, data=None if payload is None else json.dumps(payload).encode(), headers=headers, method=method)
    try:
        response = agent.open(request)
    except urllib.error.HTTPError as error:
        response = error
    data = response.read()
    return response.status, json.loads(data) if response.headers.get('Content-Type', '').startswith('application/json') else data


def expect(agent, method, path, status, payload=None):
    actual, body = call(agent, method, path, payload)
    assert actual == status, (method, path, actual, body)
    return body.get('data') if isinstance(body, dict) else body


admin, teacher, student = client(), client(), client()
for agent, name in ((admin, 'admin_demo'), (teacher, 'teacher_demo'), (student, 'student_demo')):
    expect(agent, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
published = expect(admin, 'GET', '/exams?perPage=100', 200)
exam = next(row for row in published if row['status'] == 'published')
exam_id = int(exam['id'])
expect(student, 'POST', '/exams/bulk-status/preview', 403, {'ids': [exam_id], 'target': 'paused'})
expect(teacher, 'POST', '/exams/bulk-status/preview', 403, {'ids': [exam_id], 'target': 'paused'})
expect(admin, 'POST', '/exams/bulk-status/preview', 422, {'ids': [exam_id], 'target': 'invalid'})
preview = expect(admin, 'POST', '/exams/bulk-status/preview', 200, {'ids': [exam_id], 'target': 'paused'})
assert int(preview['rows'][0]['id']) == exam_id
if preview['blocked']:
    expect(admin, 'POST', f"/exams/bulk-status/previews/{preview['id']}/commit", 409, {'target': 'paused', 'confirmed': True})
else:
    expect(admin, 'POST', f"/exams/bulk-status/previews/{preview['id']}/commit", 409, {'target': 'paused', 'confirmed': False})
    try:
        committed = expect(admin, 'POST', f"/exams/bulk-status/previews/{preview['id']}/commit", 200, {'target': 'paused', 'confirmed': True})
        assert committed['updated'] == 1
        expect(admin, 'POST', f"/exams/bulk-status/previews/{preview['id']}/commit", 409, {'target': 'paused', 'confirmed': True})
    finally:
        resume = expect(admin, 'POST', '/exams/bulk-status/preview', 200, {'ids': [exam_id], 'target': 'published'})
        expect(admin, 'POST', f"/exams/bulk-status/previews/{resume['id']}/commit", 200, {'target': 'published', 'confirmed': True})
expect(student, 'GET', f'/reports/exams/{exam_id}/answers.xlsx', 403)
book = load_workbook(io.BytesIO(expect(admin, 'GET', f'/reports/exams/{exam_id}/answers.xlsx', 200)), read_only=True)
sheet = book.active
headers = [cell.value for cell in next(sheet.iter_rows(max_row=1))]
assert 'Jawaban siswa' in headers and 'Kunci' not in headers
book.close()
print('S10 bulk preview/guard/commit and scoped answer export PASS')

