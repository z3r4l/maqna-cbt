"""QST-01..03 against the dedicated maqna_cbt_dev database and fake users."""
import http.cookiejar
import io
import json
import struct
import time
import urllib.error
import urllib.request
import zlib

ROOT = "http://127.0.0.1:8081/api/v1"


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(client, method, path, payload=None, raw=None, content_type=None):
    headers = {"Accept": "application/json"}
    if method not in ("GET", "HEAD"):
        csrf = request(client, "GET", "/auth/csrf")[1]["data"]
        headers[csrf["header"]] = csrf["token"]
    if payload is not None:
        raw = json.dumps(payload).encode()
        content_type = "application/json"
    if content_type:
        headers["Content-Type"] = content_type
    call = urllib.request.Request(ROOT + path, data=raw, method=method, headers=headers)
    try:
        result = client.open(call)
    except urllib.error.HTTPError as error:
        result = error
    content = result.read()
    try:
        body = json.loads(content)
    except (json.JSONDecodeError, UnicodeDecodeError):
        body = content
    return result.status, body


def expect(label, client, method, path, status, payload=None, raw=None, content_type=None):
    got, body = request(client, method, path, payload, raw, content_type)
    print(f"{label}: HTTP {got}")
    assert got == status, (label, got, body)
    return body.get("data") if isinstance(body, dict) else body


def png():
    def chunk(name, data):
        return struct.pack(">I", len(data)) + name + data + struct.pack(">I", zlib.crc32(name + data) & 0xFFFFFFFF)
    raw = b"\x00\xff\x00\x00\xff"
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", 1, 1, 8, 6, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw)) + chunk(b"IEND", b"")


def upload(client, question_id, name, content):
    boundary = "maqna" + str(time.time_ns())
    raw = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{name}\"\r\n"
           "Content-Type: image/png\r\n\r\n").encode() + content + f"\r\n--{boundary}--\r\n".encode()
    return request(client, "POST", f"/questions/{question_id}/assets", raw=raw, content_type="multipart/form-data; boundary=" + boundary)


admin, teacher, student = session(), session(), session()
for client, identity in [(admin, "admin_demo"), (teacher, "teacher_demo"), (student, "student_demo")]:
    expect(identity + " login", client, "POST", "/auth/login", 200, {"identity": identity, "password": "FixtureOnly123!"})
expect("student bank denied", student, "GET", "/questions", 403)
subjects = expect("subjects", admin, "GET", "/subjects?perPage=100", 200)
math = next(row for row in subjects if row["code"] == "MAT")
indo = next(row for row in subjects if row["code"] == "BIN")
base = {"subjectId": math["id"], "body": "<script>alert(1)</script>Berapa 1 + 1?", "weight": 2,
        "options": [{"body": "2", "isCorrect": True}, {"body": "3", "isCorrect": False}]}
expect("teacher wrong subject denied", teacher, "POST", "/questions", 422, {**base, "subjectId": indo["id"]})
draft = expect("create draft", teacher, "POST", "/questions", 201, {**base, "options": []})
qid = draft["id"]
assert "<script>" not in draft["body"]
expect("publish fewer than two", teacher, "PATCH", f"/questions/{qid}", 422, {"status": "published", "options": [{"body": "2", "isCorrect": True}]})
expect("publish two correct", teacher, "PATCH", f"/questions/{qid}", 422, {"status": "published", "options": [{"body": "2", "isCorrect": True}, {"body": "3", "isCorrect": True}]})
expect("publish none correct", teacher, "PATCH", f"/questions/{qid}", 422, {"status": "published", "options": [{"body": "2", "isCorrect": False}, {"body": "3", "isCorrect": False}]})
expect("student detail denied", student, "GET", f"/questions/{qid}", 403)
status, body = upload(teacher, qid, "bad.png", b"<script>alert(1)</script>")
assert status == 422, (status, body)
print("fake image denied: HTTP 422")
status, body = upload(teacher, qid, "one.png", png())
assert status == 201, (status, body)
asset_id = body["data"]["id"]
print("valid PNG uploaded: HTTP 201")
expect("student asset denied", student, "GET", f"/question-assets/{asset_id}", 403)
image = expect("teacher asset", teacher, "GET", f"/question-assets/{asset_id}", 200)
assert image.startswith(b"\x89PNG")
published = expect("publish valid", teacher, "PATCH", f"/questions/{qid}", 200, {**base, "status": "published"})
assert len(published["options"]) == 2 and sum(int(row["is_correct"]) for row in published["options"]) == 1
expect("published edit denied", teacher, "PATCH", f"/questions/{qid}", 422, {"body": "change"})
other = expect("admin creates protected question", admin, "POST", "/questions", 201, base)
expect("teacher cannot edit admin question", teacher, "PATCH", f"/questions/{other['id']}", 404, {"body": "unauthorized"})
expect("teacher cannot read admin question", teacher, "GET", f"/questions/{other['id']}", 404)
expect("teacher cannot access admin asset", teacher, "GET", "/question-assets/999999", 404)
expect("teacher list own only", teacher, "GET", "/questions", 200)
print("QST smoke passed")
