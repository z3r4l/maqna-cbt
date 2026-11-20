"""S8 room and participant assignment on local fake fixtures."""
import http.cookiejar
import json
import urllib.error
import urllib.request
import time
import subprocess

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
    assert status == code, (method, path, status, body.get('error', body.get('data')))
    return body.get('data')


admin, teacher = client(), client()
expect(admin, 'POST', '/auth/login', 200, {'identity': 'admin_demo', 'password': 'FixtureOnly123!'})
expect(teacher, 'POST', '/auth/login', 200, {'identity': 'teacher_demo', 'password': 'FixtureOnly123!'})
query = "SELECT e.id FROM exams e JOIN exam_assignments ea ON ea.exam_id=e.id LEFT JOIN attempts a ON a.exam_id=e.id AND a.student_id=ea.student_id WHERE e.status='published' AND a.id IS NULL GROUP BY e.id HAVING COUNT(*) >= 2 ORDER BY e.id DESC LIMIT 1"
exam_id = int(subprocess.check_output(['mysql', '-u', 'root', '-N', 'maqna_cbt_dev', '-e', query], text=True).strip())
exam = expect(admin, 'GET', f'/exams/{exam_id}', 200)
roster = expect(admin, 'GET', f'/exams/{exam_id}/assignments', 200)
assert roster
unstarted = {int(row) for row in subprocess.check_output(['mysql', '-u', 'root', '-N', 'maqna_cbt_dev', '-e', f'SELECT ea.student_id FROM exam_assignments ea LEFT JOIN attempts a ON a.exam_id=ea.exam_id AND a.student_id=ea.student_id WHERE ea.exam_id={exam_id} AND a.id IS NULL'], text=True).split()}
eligible = [row for row in roster if int(row['student_id']) in unstarted]
student_id = int(eligible[0]['student_id'])
expect(teacher, 'PATCH', f'/exams/{exam_id}/assignments/{student_id}', 403, {'participantNumber': 'FORBIDDEN'})
semester = expect(admin, 'GET', f"/academic/semesters/{exam['semester_id']}", 200)
room = expect(admin, 'POST', '/academic/exam-rooms', 201, {'academic_year_id': int(semester['academic_year_id']), 'code': 'ROOM' + str(time.time_ns()), 'name': 'Ruang penempatan tes', 'capacity': 24})
number = f'S8-{exam_id}-{student_id}'
saved = expect(admin, 'PATCH', f'/exams/{exam_id}/assignments/{student_id}', 200, {'examRoomId': int(room['id']), 'participantNumber': number})
assert saved['participant_number'] == number and int(saved['exam_room_id']) == int(room['id'])
again = expect(admin, 'GET', f'/exams/{exam_id}/assignments', 200)
assert next(row for row in again if int(row['student_id']) == student_id)['participant_number'] == number
if len(eligible) > 1:
    other = int(eligible[1]['student_id'])
    expect(admin, 'PATCH', f'/exams/{exam_id}/assignments/{other}', 409, {'examRoomId': int(room['id']), 'participantNumber': number})
    code = 'CAP' + str(time.time_ns())
    small = expect(admin, 'POST', '/academic/exam-rooms', 201, {'academic_year_id': int(semester['academic_year_id']), 'code': code, 'name': 'Kapasitas satu', 'capacity': 1})
    expect(admin, 'PATCH', f'/exams/{exam_id}/assignments/{student_id}', 200, {'examRoomId': int(small['id']), 'participantNumber': number})
    expect(admin, 'PATCH', f'/exams/{exam_id}/assignments/{other}', 422, {'examRoomId': int(small['id']), 'participantNumber': f'S8-{exam_id}-{other}'})
print('S8 assignment scope, persistence and duplicate PASS')
