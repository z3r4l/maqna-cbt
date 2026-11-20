"""ADV-02 A/B packages keep equivalent items, stable order and comparable scores."""
import http.cookiejar
import json
import time
import urllib.error
import urllib.request
import uuid
from datetime import datetime, timedelta, timezone

ROOT = 'http://127.0.0.1:8081/api/v1'


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(client, method, path, body=None, device=''):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        csrf = call(client, 'GET', '/auth/csrf')[1]['data']
        headers[csrf['header']] = csrf['token']
    if body is not None:
        headers['Content-Type'] = 'application/json'
    if device:
        headers['X-Attempt-Device'] = device
    request = urllib.request.Request(ROOT + path, data=None if body is None else json.dumps(body).encode(), method=method, headers=headers)
    try:
        response = client.open(request)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.loads(response.read())


def expect(label, client, method, path, status, body=None, device=''):
    got, payload = call(client, method, path, body, device)
    print(label, got)
    assert got == status, (label, got, payload)
    return payload.get('data')


admin, teacher = session(), session()
for client, name in [(admin, 'admin_demo'), (teacher, 'teacher_demo')]:
    expect(name + ' login', client, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
math = next(row for row in expect('subjects', teacher, 'GET', '/question-masters', 200)['subjects'] if row['name'] == 'Matematika')
assignment = next(row for row in expect('assignment', teacher, 'GET', '/teacher/assignments', 200) if int(row['subject_id']) == int(math['id']))
students = []
for index in [1, 2]:
    username = 'pkg.' + uuid.uuid4().hex[:12]
    created = expect('create package student', admin, 'POST', '/students', 201, {'username': username, 'displayName': 'Package Student ' + str(index), 'nis': 'PKG-' + uuid.uuid4().hex[:12]})
    expect('assign package student', admin, 'POST', '/student-memberships', 201, {'studentId': int(created['person']['id']), 'classGroupId': int(assignment['class_group_id'])})
    client = session()
    expect('package student login', client, 'POST', '/auth/login', 200, {'identity': username, 'password': created['temporaryPassword']})
    students.append(client)
questions = []
for index in [1, 2, 3]:
    row = expect('package question', teacher, 'POST', '/questions', 201, {'subjectId': int(math['id']), 'body': 'Package item ' + str(index) + ' ' + uuid.uuid4().hex, 'status': 'published', 'options': [{'body': 'Benar', 'isCorrect': True}, {'body': 'Salah', 'isCorrect': False}]})
    questions.append(int(row['id']))
now = datetime.now(timezone.utc)
exam = expect('package exam', teacher, 'POST', '/exams', 201, {'title': 'Packages smoke ' + str(time.time_ns()), 'subjectId': int(math['id']), 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': questions, 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first', 'packageMode': 'ab'})
expect('publish package exam', teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
attempts = []
for index, client in enumerate(students):
    device = 'package-device-123456-' + str(index)
    attempt = expect('start package', client, 'POST', f"/student/exams/{exam['id']}/start", 200, device=device)
    assert expect('resume stable package', client, 'POST', f"/student/exams/{exam['id']}/start", 200, device=device)['items'] == attempt['items']
    attempts.append(attempt)
    for item in attempt['items']:
        expect('answer package', client, 'PATCH', f"/student/attempts/{attempt['id']}/items/{item['id']}", 200, {'expectedRevision': 0, 'selectedOptionId': next(option['id'] for option in item['options'] if option['body'] == 'Benar'), 'marked': False, 'mutationId': str(uuid.uuid4())}, device)
    expect('submit package', client, 'POST', f"/student/attempts/{attempt['id']}/submit", 200, device=device)
assert {attempt['packageCode'] for attempt in attempts} == {'A', 'B'}
assert {item['body'] for item in attempts[0]['items']} == {item['body'] for item in attempts[1]['items']}
assert [item['body'] for item in attempts[0]['items']] == [item['body'] for item in reversed(attempts[1]['items'])]
report = expect('package report', teacher, 'GET', f"/reports/exams/{exam['id']}", 200)
scores = [float(row['score']) for row in report['rows'] if row['score'] is not None]
assert len(scores) == 2 and scores == [100.0, 100.0]
print('ADV-02 package smoke passed')
