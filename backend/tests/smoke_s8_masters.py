"""S8 exam type and room masters against fake local development users."""
import http.cookiejar
import json
import time
import urllib.error
import urllib.request
from datetime import datetime, timedelta, timezone

ROOT = 'http://127.0.0.1:8081/api/v1'


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(agent, method, path, data=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        headers['X-CSRF-TOKEN'] = call(agent, 'GET', '/auth/csrf')[1]['data']['token']
    if data is not None:
        headers['Content-Type'] = 'application/json'
    request = urllib.request.Request(ROOT + path, data=None if data is None else json.dumps(data).encode(), method=method, headers=headers)
    try:
        response = agent.open(request)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.load(response)


def expect(agent, method, path, code, data=None):
    status, body = call(agent, method, path, data)
    assert status == code, (method, path, status, body)
    return body.get('data')


admin, teacher = client(), client()
expect(admin, 'POST', '/auth/login', 200, {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})
expect(teacher, 'POST', '/auth/login', 200, {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})
expect(teacher, 'GET', '/academic/exam-types', 403)
types = expect(admin, 'GET', '/academic/exam-types?perPage=100', 200)
assert {'PH', 'PTS', 'PAS', 'PAT', 'US', 'TO'} <= {row['code'] for row in types}
code = 'T' + str(time.time_ns())
created = expect(admin, 'POST', '/academic/exam-types', 201, {'code': code, 'name': 'Tes tambahan'})
expect(admin, 'POST', '/academic/exam-types', 409, {'code': code, 'name': 'Duplikat'})
expect(admin, 'PATCH', f"/academic/exam-types/{created['id']}", 200, {'active': 0})
years = expect(admin, 'GET', '/academic/academic-years?perPage=100', 200)
year = next(row for row in years if int(row['active']) == 1)
room = expect(admin, 'POST', '/academic/exam-rooms', 201, {'academic_year_id': int(year['id']), 'code': code, 'name': 'Ruang tes', 'capacity': 24})
expect(admin, 'POST', '/academic/exam-rooms', 409, {'academic_year_id': int(year['id']), 'code': code, 'name': 'Duplikat', 'capacity': 24})
assert int(room['academic_year_id']) == int(year['id'])
masters = expect(admin, 'GET', '/exam-masters', 200)
assert any(row['code'] == 'PH' for row in masters['examTypes'])
start = (datetime.now(timezone.utc) + timedelta(days=1)).strftime('%Y-%m-%dT%H:%M:%SZ')
end = (datetime.now(timezone.utc) + timedelta(days=2)).strftime('%Y-%m-%dT%H:%M:%SZ')
draft = expect(admin, 'POST', '/exams', 201, {
    'title': 'Jenis ujian S8 ' + code,
    'subjectId': int(masters['subjects'][0]['id']),
    'semesterId': int(masters['semesters'][0]['id']),
    'examTypeId': int(next(row['id'] for row in types if row['code'] == 'PH')),
    'startAt': start, 'endAt': end, 'durationMinutes': 60, 'passingGrade': 70,
})
assert int(draft['exam_type_id']) > 0
expect(admin, 'PATCH', f"/exams/{draft['id']}", 422, {'examTypeId': int(created['id'])})
print('S8 exam type and room master scope, defaults, duplicate, archive PASS')
