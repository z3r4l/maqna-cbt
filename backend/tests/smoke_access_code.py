"""EXT-04 protected exam start and throttle, dedicated fake development database."""
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
        token = call(client, 'GET', '/auth/csrf')[1]['data']
        headers[token['header']] = token['token']
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


def expect(label, client, method, path, code, body=None, device=''):
    status, payload = call(client, method, path, body, device)
    print(label, status)
    assert status == code, (label, status, payload)
    return payload.get('data')


teacher, student = session(), session()
for client, name in [(teacher, 'teacher_demo'), (student, 'student_demo')]:
    expect(name + ' login', client, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
math = next(row for row in expect('subjects', teacher, 'GET', '/question-masters', 200)['subjects'] if row['name'] == 'Matematika')
assignment = next(row for row in expect('assignment', teacher, 'GET', '/teacher/assignments', 200) if int(row['subject_id']) == int(math['id']))
question = expect('question', teacher, 'POST', '/questions', 201, {'subjectId': int(math['id']), 'body': 'Protected item ' + uuid.uuid4().hex, 'status': 'published', 'options': [{'body': 'Benar', 'isCorrect': True}, {'body': 'Salah', 'isCorrect': False}]})
now = datetime.now(timezone.utc)
payload = {'title': 'Access code smoke ' + str(time.time_ns()), 'subjectId': int(math['id']), 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': [int(question['id'])], 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first', 'accessCode': 'kelas-ABC123'}
exam = expect('protected exam', teacher, 'POST', '/exams', 201, payload)
assert exam['requiresAccessCode'] is True and 'access_code_hash' not in exam
expect('publish', teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
listed = expect('student list', student, 'GET', '/student/exams', 200)
assert int(next(row for row in listed if int(row['id']) == int(exam['id']))['requires_access_code']) == 1
expect('missing code denied', student, 'POST', f"/student/exams/{exam['id']}/start", 409, device='access-code-device')
expect('wrong code denied', student, 'POST', f"/student/exams/{exam['id']}/start", 409, {'accessCode': 'wrong-123'}, 'access-code-device')
attempt = expect('correct code starts', student, 'POST', f"/student/exams/{exam['id']}/start", 200, {'accessCode': 'kelas-ABC123'}, 'access-code-device')
assert attempt['status'] == 'active'
assert expect('resume without code', student, 'POST', f"/student/exams/{exam['id']}/start", 200, device='access-code-device')['id'] == attempt['id']
throttled_exam = expect('throttle exam', teacher, 'POST', '/exams', 201, {**payload, 'title': 'Throttle smoke ' + str(time.time_ns())})
expect('publish throttle exam', teacher, 'POST', f"/exams/{throttled_exam['id']}/publish", 200)
for n in range(4):
    expect('wrong code attempt ' + str(n), student, 'POST', f"/student/exams/{throttled_exam['id']}/start", 409, {'accessCode': 'wrong-123'}, 'access-code-device')
expect('fifth guess throttled', student, 'POST', f"/student/exams/{throttled_exam['id']}/start", 429, {'accessCode': 'wrong-123'}, 'access-code-device')
expect('correct code still throttled', student, 'POST', f"/student/exams/{throttled_exam['id']}/start", 429, {'accessCode': 'kelas-ABC123'}, 'access-code-device')
print('EXT-04 access code smoke passed')
