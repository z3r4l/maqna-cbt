"""S10 school logo upload is scoped and appears in participant cards."""
import http.cookiejar
import io
import json
import struct
import urllib.error
import urllib.request
import zlib
from PyPDF2 import PdfFileReader

ROOT = 'http://127.0.0.1:8081/api/v1'


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def login(agent, name):
    token = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    request = urllib.request.Request(ROOT + '/auth/login', data=json.dumps({'identity': name, 'password': 'FixtureOnly123!'}).encode(), method='POST', headers={'Content-Type': 'application/json', 'X-CSRF-TOKEN': token})
    assert agent.open(request).status == 200


def png():
    def chunk(name, data):
        return struct.pack('>I', len(data)) + name + data + struct.pack('>I', zlib.crc32(name + data) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 6, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(b'\x00\xff\x00\x00\xff')) + chunk(b'IEND', b'')


admin, teacher, student = client(), client(), client()
for agent, name in ((admin, 'admin_demo'), (teacher, 'teacher_demo'), (student, 'student_demo')):
    login(agent, name)
boundary = 'maqnaLogoBoundary'
content = png()
body = (f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="logo.png"\r\nContent-Type: image/png\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
for agent, expected in ((student, 403), (teacher, 403), (admin, 200)):
    token = json.load(agent.open(ROOT + '/auth/csrf'))['data']['token']
    request = urllib.request.Request(ROOT + '/school/logo', data=body, method='POST', headers={'Content-Type': 'multipart/form-data; boundary=' + boundary, 'X-CSRF-TOKEN': token})
    try:
        response = agent.open(request)
    except urllib.error.HTTPError as error:
        response = error
    assert response.status == expected, (expected, response.status, response.read())
assert admin.open(ROOT + '/school/logo').read() == content
exams = json.load(admin.open(ROOT + '/exams?perPage=100'))['data']
exam = next(row for row in exams if row['status'] == 'published')
cards = admin.open(ROOT + f"/exams/{exam['id']}/cards.pdf").read()
page = PdfFileReader(io.BytesIO(cards)).getPage(0)
assert '/XObject' in page['/Resources']
print('S10 logo upload scope and PDF card image PASS')
