"""S11 answer matrix is school scoped, latest-attempt based, and exportable."""
import http.cookiejar
import io
import json
import urllib.error
import urllib.request
from openpyxl import load_workbook

ROOT = 'http://127.0.0.1:8081/api/v1'


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def login(agent, name):
    token = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    request = urllib.request.Request(ROOT + '/auth/login', data=json.dumps({'identity': name, 'password': 'FixtureOnly123!'}).encode(), method='POST', headers={'Content-Type': 'application/json', 'X-CSRF-TOKEN': token})
    assert agent.open(request).status == 200


admin, student = client(), client()
login(admin, 'admin_demo')
login(student, 'student_demo')
exams = json.load(admin.open(ROOT + '/exams?perPage=100'))['data']
exam_id = int(next(row['id'] for row in exams if row['status'] == 'published'))
for suffix in ('answer-matrix', 'answer-matrix.xlsx'):
    try:
        student.open(ROOT + f'/exams/{exam_id}/' + suffix)
        raise AssertionError('Student accessed answer matrix')
    except urllib.error.HTTPError as error:
        assert error.code == 403
matrix = json.load(admin.open(ROOT + f'/exams/{exam_id}/answer-matrix'))['data']
assert matrix['examId'] == exam_id and len(matrix['questions']) >= 1
assert all(len(row['answers']) == len(matrix['questions']) for row in matrix['rows'])
book = load_workbook(io.BytesIO(admin.open(ROOT + f'/exams/{exam_id}/answer-matrix.xlsx').read()), read_only=True)
headers = [cell.value for cell in next(book.active.iter_rows(max_row=1))]
assert headers[:4] == ['Siswa', 'Rombel', 'Percobaan', 'Nilai'] and len(headers) == 4 + len(matrix['questions'])
book.close()
print('S11 answer matrix and XLSX scope PASS')
