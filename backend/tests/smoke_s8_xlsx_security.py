"""S8 XLSX fallback rejects formula, extra sheets, and decompression bombs."""
import http.cookiejar
import io
import json
import time
import urllib.error
import urllib.request
import zipfile
import openpyxl

ROOT = 'http://127.0.0.1:8081/api/v1'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(method, path, body=None, content_type=None):
    headers = {'Accept': 'application/json'}
    if method != 'GET':
        headers['X-CSRF-TOKEN'] = json.load(client.open(ROOT + '/auth/csrf'))['data']['token']
    if content_type:
        headers['Content-Type'] = content_type
    req = urllib.request.Request(ROOT + path, data=body, headers=headers, method=method)
    try:
        response = client.open(req)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.load(response)


def upload(data):
    boundary = 's8-xlsx-' + str(time.time_ns())
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="mode"\r\n\r\nreject\r\n'
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="subjects.xlsx"\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n').encode() + data + f'\r\n--{boundary}--\r\n'.encode()
    return call('POST', '/imports/subjects/preview', body, 'multipart/form-data; boundary=' + boundary)


def book(value, extra=False):
    workbook = openpyxl.Workbook()
    workbook.active.append(['code', 'name'])
    workbook.active.append(['SEC' + str(time.time_ns())[-10:], value])
    if extra:
        workbook.create_sheet('Extra')
    output = io.BytesIO()
    workbook.save(output)
    return output.getvalue()


assert call('POST', '/auth/login', json.dumps({'identity': 'admin_demo', 'password': 'FixtureOnly123!'}).encode(), 'application/json')[0] == 200
for data in (book('=1+1'), book('Mapel', extra=True)):
    status, body = upload(data)
    assert status == 422, (status, body)
output = io.BytesIO()
with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED) as archive:
    archive.writestr('xl/workbook.xml', '<workbook/>')
    archive.writestr('xl/worksheets/sheet1.xml', '<worksheet/>')
    archive.writestr('bomb.bin', b'A' * (21 * 1024 * 1024))
status, body = upload(output.getvalue())
assert status == 422, (status, body)
print('S8 XLSX formula, multi-sheet, bomb rejection PASS')
