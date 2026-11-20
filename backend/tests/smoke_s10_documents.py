"""S10 attendance and minutes PDFs remain scoped and contain no secrets."""
import http.cookiejar
import io
import json
import subprocess
import tempfile
from pathlib import Path
import urllib.error
import urllib.request
from PyPDF2 import PdfFileReader

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
exam = next(row for row in exams if row['status'] == 'published')
exam_id = int(exam['id'])
token = json.load(admin.open(ROOT + '/auth/csrf'))['data']['token']
request = urllib.request.Request(ROOT + f'/exams/{exam_id}/minutes', data=json.dumps({'notes': 'Pelaksanaan lancar S10', 'signerOne': 'Pengawas Contoh', 'signerTwo': 'Pengawas Kedua'}).encode(), method='PUT', headers={'Content-Type': 'application/json', 'X-CSRF-TOKEN': token})
assert json.load(admin.open(request))['data']['notes'] == 'Pelaksanaan lancar S10'
for suffix in ('attendance.pdf', 'attendance.pdf?blank=1', 'minutes.pdf', 'minutes.pdf?blank=1'):
    url = ROOT + f'/exams/{exam_id}/' + suffix
    try:
        student.open(url)
        raise AssertionError('Student exported ' + suffix)
    except urllib.error.HTTPError as error:
        assert error.code == 403
    data = admin.open(url).read()
    assert data.startswith(b'%PDF')
    page = PdfFileReader(io.BytesIO(data)).getPage(0)
    assert int(page.mediaBox.getWidth()) == 612 and int(page.mediaBox.getHeight()) == 936
    with tempfile.TemporaryDirectory() as directory:
        pdf_path = Path(directory) / 'document.pdf'
        text_path = Path(directory) / 'document.txt'
        pdf_path.write_bytes(data)
        subprocess.check_call(['pdftotext', '-layout', str(pdf_path), str(text_path)])
        text = text_path.read_text(encoding='utf-8')
    assert exam['title'] in text
    if suffix == 'minutes.pdf':
        assert 'Pelaksanaan lancar S10' in text and 'Pengawas Contoh' in text
    assert not any(secret in text.lower() for secret in ('password', 'accesscode', 'kunci jawaban'))
print('S10 attendance and minutes PDF F4, scope and no secrets PASS')
