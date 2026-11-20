"""S8 participant number survives repeated scoped PDF card export."""
import http.cookiejar
import io
import json
import subprocess
import tempfile
from pathlib import Path
from PyPDF2 import PdfFileReader
import urllib.error
import urllib.request

ROOT = 'http://127.0.0.1:8081/api/v1'


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def login(agent, username):
    token = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    req = urllib.request.Request(ROOT + '/auth/login', data=json.dumps({'identity': username, 'password': 'FixtureOnly123!'}).encode(), headers={'Content-Type': 'application/json', 'X-CSRF-TOKEN': token}, method='POST')
    assert agent.open(req).status == 200


admin, teacher = client(), client()
login(admin, 'admin_demo')
login(teacher, 'teacher_demo')
exams = json.load(admin.open(ROOT + '/exams?perPage=100'))['data']
for exam in exams:
    if exam['status'] != 'published':
        continue
    exam_id = int(exam['id'])
    roster = json.load(admin.open(ROOT + f'/exams/{exam_id}/assignments'))['data']
    assigned = next((row for row in roster if row['participant_number'] and row['exam_room_id']), None)
    if assigned:
        break
else:
    raise AssertionError('No assigned fixture found')
url = ROOT + f"/exams/{exam_id}/cards.pdf?classGroupId={assigned['class_group_id']}&examRoomId={assigned['exam_room_id']}"
try:
    teacher.open(url)
    raise AssertionError('Teacher exported admin cards')
except urllib.error.HTTPError as error:
    assert error.code == 403
for _ in range(2):
    response = admin.open(url)
    data = response.read()
    assert response.status == 200 and data.startswith(b'%PDF')
    page = PdfFileReader(io.BytesIO(data)).getPage(0)
    assert int(page.mediaBox.getWidth()) == 612 and int(page.mediaBox.getHeight()) == 936
    with tempfile.TemporaryDirectory() as directory:
        pdf_path = Path(directory) / 'cards.pdf'
        text_path = Path(directory) / 'cards.txt'
        pdf_path.write_bytes(data)
        subprocess.check_call(['pdftotext', '-layout', str(pdf_path), str(text_path)])
        text = text_path.read_text(encoding='utf-8')
    assert assigned['participant_number'] in text, (assigned['participant_number'], text[:500])
    assert 'password' not in text.lower()
print('S8 scoped card PDF and stable participant number PASS')
