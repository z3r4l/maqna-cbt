"""EXT-06 authorized XLSX/PDF export from fake development data."""
import http.cookiejar
import json
import urllib.error
import urllib.request
import zipfile
import io
import openpyxl

ROOT = 'http://127.0.0.1:8081/api/v1'


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def login(client, identity):
    csrf = json.load(client.open(ROOT + '/auth/csrf'))['data']
    data = json.dumps({'identity': identity, 'password': 'FixtureOnly123!'}).encode()
    response = client.open(urllib.request.Request(ROOT + '/auth/login', data=data, headers={csrf['header']: csrf['token'], 'Content-Type': 'application/json'}))
    assert response.status == 200


teacher, student = session(), session()
login(teacher, 'teacher_demo')
login(student, 'student_demo')
reports = json.load(teacher.open(ROOT + '/reports/exams'))['data']
assert reports
exam_id = reports[0]['id']
for fmt, signature in [('pdf', b'%PDF'), ('xlsx', b'PK')]:
    url = f'{ROOT}/reports/exams/{exam_id}/export/{fmt}'
    with teacher.open(url) as response:
        body = response.read()
        assert response.status == 200 and body.startswith(signature) and len(body) > 1000
        assert 'attachment' in response.headers['Content-Disposition']
    if fmt == 'xlsx':
        parsed = openpyxl.load_workbook(io.BytesIO(body), read_only=True, data_only=True)
        assert parsed.active['A1'].value == 'Nama siswa'
        parsed.close()
        with zipfile.ZipFile(io.BytesIO(body)) as workbook:
            xml = workbook.read('xl/worksheets/sheet1.xml')
            assert b'<f>' not in xml
            strings = workbook.read('xl/sharedStrings.xml')
            assert b'=2+2' in strings and (b"'=2+2" in strings or b'&apos;=2+2' in strings), strings[:500]
    try:
        student.open(url)
        raise AssertionError('Student export unexpectedly succeeded')
    except urllib.error.HTTPError as error:
        assert error.code == 403, (fmt, error.code, error.read())
    print(fmt, 'authorized 200, student 403, signature valid')
print('EXT-06 export smoke passed')
