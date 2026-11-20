"""S10 required answers are enforced by the server on manual submit."""
import http.cookiejar
import json
import time
import uuid
import urllib.error
import urllib.request
from datetime import datetime, timedelta, timezone

ROOT = 'http://127.0.0.1:8081/api/v1'
DEVICE = 's10-required-' + str(time.time_ns())


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


teacher, student = client(), client()
for agent, name in ((teacher, 'teacher_demo'), (student, 'student_demo')):
    expect(agent, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
assignment = expect(teacher, 'GET', '/teacher/assignments', 200)[0]
subject_id = int(assignment['subject_id'])
question = expect(teacher, 'POST', '/questions', 201, {'subjectId': subject_id, 'body': 'S10 wajib jawab ' + str(time.time_ns()), 'questionType': 'mcq', 'weight': 1, 'options': [{'body': 'Benar', 'isCorrect': True}, {'body': 'Salah', 'isCorrect': False}], 'status': 'published'})
now = datetime.now(timezone.utc)
exam = expect(teacher, 'POST', '/exams', 201, {'title': 'S10 wajib jawab ' + str(time.time_ns()), 'subjectId': subject_id, 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': [int(question['id'])], 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first', 'requireAllAnswers': True})
expect(teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
attempt = expect(student, 'POST', f"/student/exams/{exam['id']}/start", 200, device=True)
expect(student, 'POST', f"/student/attempts/{attempt['id']}/submit", 409, device=True)
item = attempt['items'][0]
expect(student, 'PATCH', f"/student/attempts/{attempt['id']}/items/{item['id']}", 200, {'expectedRevision': 0, 'selectedOptionId': int(item['options'][0]['id']), 'marked': False, 'mutationId': str(uuid.uuid4())}, device=True)
submitted = expect(student, 'POST', f"/student/attempts/{attempt['id']}/submit", 200, device=True)
assert submitted['status'] == 'submitted' and submitted['unansweredCount'] == 0
print('S10 required answers server enforcement PASS')
