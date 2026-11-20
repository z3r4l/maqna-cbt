"""EXT-02 mixed MCQ/essay review and release gate on the dedicated development DB."""
import http.cookiejar
import json
import subprocess
import time
import urllib.error
import urllib.request
import uuid
from datetime import datetime, timedelta, timezone

ROOT = 'http://127.0.0.1:8081/api/v1'


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(client, method, path, body=None, device=None):
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


def expect(label, client, method, path, code, body=None, device=None):
    status, payload = call(client, method, path, body, device)
    print(label, status)
    assert status == code, (label, status, payload)
    return payload.get('data')


teacher, student = session(), session()
for client, name in [(teacher, 'teacher_demo'), (student, 'student_demo')]:
    expect(name + ' login', client, 'POST', '/auth/login', 200, {'identity': name, 'password': 'FixtureOnly123!'})
subjects = expect('subjects', teacher, 'GET', '/question-masters', 200)['subjects']
math = next(row for row in subjects if row['name'] == 'Matematika')
assignment = next(row for row in expect('assignment', teacher, 'GET', '/teacher/assignments', 200) if int(row['subject_id']) == int(math['id']))
mcq = expect('mixed MCQ', teacher, 'POST', '/questions', 201, {'subjectId': int(math['id']), 'body': 'EXT mixed MCQ ' + uuid.uuid4().hex, 'status': 'published', 'options': [{'body': 'Benar', 'isCorrect': True}, {'body': 'Salah', 'isCorrect': False}]})
essay = expect('mixed essay', teacher, 'POST', '/questions', 201, {'subjectId': int(math['id']), 'questionType': 'essay', 'body': 'Jelaskan jawaban ' + uuid.uuid4().hex, 'rubric': 'Poin penuh untuk alasan yang tepat', 'status': 'published', 'options': []})
now = datetime.now(timezone.utc)
exam = expect('mixed exam', teacher, 'POST', '/exams', 201, {'title': 'Essay smoke ' + str(time.time_ns()), 'subjectId': int(math['id']), 'semesterId': int(assignment['semester_id']), 'classIds': [int(assignment['class_group_id'])], 'questionIds': [int(mcq['id']), int(essay['id'])], 'startAt': (now - timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'endAt': (now + timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'durationMinutes': 30, 'passingGrade': 70, 'maxAttempts': 1, 'resultPolicy': 'first', 'reviewEnabled': True})
expect('publish mixed exam', teacher, 'POST', f"/exams/{exam['id']}/publish", 200)
attempt = expect('student start', student, 'POST', f"/student/exams/{exam['id']}/start", 200, device='essay-test-device')
mcq_item, essay_item = attempt['items']
assert essay_item['type'] == 'essay' and essay_item['options'] == [] and 'rubric' not in essay_item
expect('save MCQ', student, 'PATCH', f"/student/attempts/{attempt['id']}/items/{mcq_item['id']}", 200, {'expectedRevision': 0, 'selectedOptionId': mcq_item['options'][0]['id'], 'marked': False, 'mutationId': str(uuid.uuid4())}, 'essay-test-device')
expect('save essay', student, 'PATCH', f"/student/attempts/{attempt['id']}/items/{essay_item['id']}", 200, {'expectedRevision': 0, 'selectedOptionId': None, 'essayText': 'Karena langkah perhitungan benar', 'marked': False, 'mutationId': str(uuid.uuid4())}, 'essay-test-device')
expect('student submit', student, 'POST', f"/student/attempts/{attempt['id']}/submit", 200, device='essay-test-device')
expect('review before release denied', student, 'GET', f"/student/results/{attempt['id']}/review", 403)
pending = expect('staff essay queue', teacher, 'GET', f"/exams/{exam['id']}/essays", 200)
assert len(pending) == 1 and pending[0]['rubric'] and pending[0]['answer'] == 'Karena langkah perhitungan benar'
subprocess.check_call(['mysql', '-u', 'root', 'maqna_cbt_dev', '-e', f"UPDATE exams SET end_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id={int(exam['id'])}"])
expect('release blocked pending review', teacher, 'POST', f"/exams/{exam['id']}/release", 409)
expect('over max score rejected', teacher, 'PATCH', f"/exams/{exam['id']}/essays/{essay_item['id']}", 422, {'points': 2, 'feedback': 'Tidak valid'})
scored = expect('score essay', teacher, 'PATCH', f"/exams/{exam['id']}/essays/{essay_item['id']}", 200, {'points': 0.5, 'feedback': 'Sebagian benar'})
assert scored['score'] == 75.0 and scored['pendingReview'] is False
expect('release after grading', teacher, 'POST', f"/exams/{exam['id']}/release", 200)
result = expect('student result', student, 'GET', f"/student/results/{attempt['id']}", 200)
assert result['score'] == 75.0 and result['status'] == 'released'
assert result['reviewAvailable'] is True
review = expect('review after release', student, 'GET', f"/student/results/{attempt['id']}/review", 200)
assert len(review) == 2 and review[0]['options'][0]['is_correct'] == '1' and review[1]['essayFeedback'] == 'Sebagian benar'
expect('post-release score rejected', teacher, 'PATCH', f"/exams/{exam['id']}/essays/{essay_item['id']}", 422, {'points': 1, 'feedback': 'Tidak boleh'})
print('EXT-02 essay smoke passed')
