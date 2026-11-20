"""ADV-01 question review versions preserve a published exam snapshot."""
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


def expect(label, client, method, path, status, body=None, device=''):
    got, payload = call(client, method, path, body, device)
    print(label, got)
    assert got == status, (label, got, payload)
    return payload.get('data')


admin, teacher, student = session(), session(), session()
for client, name in [(admin, 'admin_demo'), (teacher, 'teacher_demo'), (student, 'student_demo')]:
    expect(name + ' login', client, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
math = next(row for row in expect('subjects', teacher, 'GET', '/question-masters', 200)['subjects'] if row['name'] == 'Matematika')
assignment = next(row for row in expect('assignment', teacher, 'GET', '/teacher/assignments', 200) if int(row['subject_id']) == int(math['id']))
old_body = 'ADV old item ' + uuid.uuid4().hex
new_body = 'ADV revised item ' + uuid.uuid4().hex
question = expect('publish old question', teacher, 'POST', '/questions', 201, {'subjectId': int(math['id']), 'body': old_body, 'status': 'published', 'options': [{'body': 'Old yes', 'isCorrect': True}, {'body': 'Old no', 'isCorrect': False}]})
now = datetime.now(timezone.utc)
exam = expect('create exam', teacher, 'POST', '/exams', 201, {'title': 'Version smoke ' + str(time.time_ns()), 'subjectId': int(math['id']), 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': [int(question['id'])], 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first'})
expect('publish exam', teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
attempt = expect('student starts old snapshot', student, 'POST', f"/student/exams/{exam['id']}/start", 200, device='version-device-123456789')
assert attempt['items'][0]['body'] == old_body
version = expect('create revision', teacher, 'POST', f"/questions/{question['id']}/revisions", 201)
assert int(version['version_no']) == 2 and version['status'] == 'draft'
expect('duplicate open revision denied', teacher, 'POST', f"/questions/{question['id']}/revisions", 422)
edited = expect('edit revision', teacher, 'PATCH', f"/question-versions/{version['id']}", 200, {'body': new_body, 'type': 'mcq', 'rubric': '', 'explanation': 'New reason', 'weight': 1, 'options': [{'body': 'New yes', 'isCorrect': True}, {'body': 'New no', 'isCorrect': False}]})
assert edited['snapshot']['body'] == new_body
expect('submit review', teacher, 'POST', f"/question-versions/{version['id']}/submit", 200)
expect('teacher cannot self-approve', teacher, 'POST', f"/question-versions/{version['id']}/decision", 403, {'approve': True, 'note': ''})
expect('student cannot view revision', student, 'GET', f"/question-versions/{version['id']}", 403)
approved = expect('admin approves', admin, 'POST', f"/question-versions/{version['id']}/decision", 200, {'approve': True, 'note': 'Layak terbit'})
assert approved['status'] == 'approved'
current = expect('question now revised', teacher, 'GET', f"/questions/{question['id']}", 200)
assert current['body'] == new_body and int(current['version_no']) == 2
old_attempt = expect('old attempt unchanged', student, 'GET', f"/student/attempts/{attempt['id']}", 200, device='version-device-123456789')
assert old_attempt['items'][0]['body'] == old_body and old_attempt['items'][0]['options'][0]['body'] == 'Old yes'
history = expect('history', teacher, 'GET', f"/question-versions?questionId={question['id']}", 200)
assert len(history) == 2 and [int(row['version_no']) for row in history] == [2, 1]
print('ADV-01 version smoke passed')
