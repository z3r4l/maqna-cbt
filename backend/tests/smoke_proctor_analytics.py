"""ADV-03/04/05: bounded telemetry, scoped proctor roster, private item statistics."""
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
for index in range(5):
    username = 'analytics.' + uuid.uuid4().hex[:12]
    created = expect('create student', admin, 'POST', '/students', 201, {'username': username, 'displayName': 'Analytics Student ' + str(index), 'nis': 'ANA-' + uuid.uuid4().hex[:12]})
    expect('assign student', admin, 'POST', '/student-memberships', 201, {'studentId': int(created['person']['id']), 'classGroupId': int(assignment['class_group_id'])})
    client = session()
    expect('student login', client, 'POST', '/auth/login', 200, {'identity': username, 'password': created['temporaryPassword']})
    students.append(client)
question = expect('question', teacher, 'POST', '/questions', 201, {'subjectId': int(math['id']), 'body': 'Analytics item ' + uuid.uuid4().hex, 'status': 'published', 'options': [{'body': 'Correct', 'isCorrect': True}, {'body': 'Wrong', 'isCorrect': False}]})
now = datetime.now(timezone.utc)
exam = expect('exam', teacher, 'POST', '/exams', 201, {'title': 'Analytics smoke ' + str(time.time_ns()), 'subjectId': int(math['id']), 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': [int(question['id'])], 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first'})
expect('publish', teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
initial = expect('privacy threshold', teacher, 'GET', f"/exams/{exam['id']}/question-analytics", 200)
assert initial['suppressed'] and initial['items'] == []
roster = expect('teacher roster', teacher, 'GET', f"/exams/{exam['id']}/proctor", 200)
assert len(roster['rows']) >= 5 and all(row['status'] == 'not_started' for row in roster['rows'])
expect('student denied roster', students[0], 'GET', f"/exams/{exam['id']}/proctor", 403)
expect('student denied analytics', students[0], 'GET', f"/exams/{exam['id']}/question-analytics", 403)
for index, client in enumerate(students):
    device = 'analytics-device-123456-' + str(index)
    attempt = expect('start', client, 'POST', f"/student/exams/{exam['id']}/start", 200, device=device)
    item = attempt['items'][0]
    if index == 0:
        expect('bad event rejected', client, 'POST', f"/student/attempts/{attempt['id']}/heartbeat", 422, {'event': 'cheating'}, device)
        expect('foreign attempt denied', students[1], 'POST', f"/student/attempts/{attempt['id']}/heartbeat", 404, {}, 'analytics-device-123456-1')
        for number in range(3):
            event = expect('bounded event', client, 'POST', f"/student/attempts/{attempt['id']}/heartbeat", 200, {'event': 'visibility_hidden'}, device)
            assert event['eventRecorded'] is (number < 2)
        live = expect('active roster', teacher, 'GET', f"/exams/{exam['id']}/proctor", 200)
        active = next(row for row in live['rows'] if int(row['attemptId'] or 0) == attempt['id'])
        assert active['status'] == 'active' and int(active['eventCount']) == 2 and active['remainingSeconds'] > 0
    selected = next(option['id'] for option in item['options'] if option['body'] == ('Correct' if index < 3 else 'Wrong'))
    expect('answer', client, 'PATCH', f"/student/attempts/{attempt['id']}/items/{item['id']}", 200, {'expectedRevision': 0, 'selectedOptionId': selected, 'marked': False, 'mutationId': str(uuid.uuid4())}, device)
    expect('submit', client, 'POST', f"/student/attempts/{attempt['id']}/submit", 200, device=device)
    expect('finished heartbeat rejected', client, 'POST', f"/student/attempts/{attempt['id']}/heartbeat", 409, {}, device)
stats = expect('analytics', teacher, 'GET', f"/exams/{exam['id']}/question-analytics", 200)
assert not stats['suppressed'] and stats['sampleSize'] >= 5
item = stats['items'][0]
assert 0 < item['difficulty'] < 1 and len(item['distractors']) == 2
assert item['discrimination'] > 0
assert sum(option['count'] for option in item['distractors']) == stats['sampleSize']
print('ADV-03/04/05 proctor and analytics smoke passed')
