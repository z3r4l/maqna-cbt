"""S11 role session policy preserves an active student attempt."""
import http.cookiejar
import json
import time
import urllib.error
import urllib.request
from datetime import datetime, timedelta, timezone

ROOT = 'http://127.0.0.1:8081/api/v1'
DEVICE = 's11-session-fixture-' + str(time.time_ns())


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(agent, method, path, payload=None, device=False):
    headers = {'Accept': 'application/json'}
    if device:
        headers['X-Attempt-Device'] = DEVICE
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


def expect(agent, method, path, status, payload=None, device=False):
    actual, body = call(agent, method, path, payload, device)
    assert actual == status, (method, path, actual, body)
    return body.get('data')


admin, teacher, first, second = client(), client(), client(), client()
for agent, name in ((admin, 'admin_demo'), (teacher, 'teacher_demo')):
    expect(agent, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
expect(first, 'POST', '/auth/login', 200, {'identity': 'student_demo', 'password': 'FixtureOnly123!'})
original = next(row['maxSessions'] for row in expect(admin, 'GET', '/school/session-policies', 200) if row['role'] == 'student')
expect(teacher, 'GET', '/school/session-policies', 403)
expect(first, 'GET', '/school/session-policies', 403)
expect(admin, 'PUT', '/school/session-policies/student', 422, {'maxSessions': 6})
try:
    expect(admin, 'PUT', '/school/session-policies/student', 200, {'maxSessions': 1})
    assignment = expect(teacher, 'GET', '/teacher/assignments', 200)[0]
    question = expect(teacher, 'POST', '/questions', 201, {'subjectId': int(assignment['subject_id']), 'body': 'S11 sesi ' + str(time.time_ns()), 'questionType': 'mcq', 'weight': 1, 'options': [{'body': 'A', 'isCorrect': True}, {'body': 'B', 'isCorrect': False}], 'status': 'published'})
    now = datetime.now(timezone.utc)
    exam = expect(teacher, 'POST', '/exams', 201, {'title': 'S11 sesi ' + str(time.time_ns()), 'subjectId': int(assignment['subject_id']), 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': [int(question['id'])], 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first'})
    expect(teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
    attempt = expect(first, 'POST', f"/student/exams/{exam['id']}/start", 200, device=True)
    expect(second, 'POST', '/auth/login', 409, {'identity': 'student_demo', 'password': 'FixtureOnly123!'})
    assert expect(first, 'GET', f"/student/attempts/{attempt['id']}", 200, device=True)['id'] == attempt['id']
    expect(first, 'POST', f"/student/attempts/{attempt['id']}/submit", 200, device=True)
finally:
    expect(admin, 'PUT', '/school/session-policies/student', 200, {'maxSessions': original})
print('S11 student session limit and active attempt preservation PASS')
