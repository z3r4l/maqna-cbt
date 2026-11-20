"""ATT-01..06 against dedicated maqna_cbt_dev and fake accounts only."""
import concurrent.futures
import http.cookiejar
import json
import struct
import subprocess
import time
import urllib.error
import urllib.request
import uuid
import zlib
from datetime import datetime, timedelta, timezone

ROOT = "http://127.0.0.1:8081/api/v1"
DEVICE = "fixture-device-1234567890"


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(client, method, path, payload=None, device=None):
    headers = {"Accept": "application/json"}
    if device:
        headers["X-Attempt-Device"] = device
    if method != "GET":
        csrf = request(client, "GET", "/auth/csrf")[1]["data"]
        headers[csrf["header"]] = csrf["token"]
    if payload is not None:
        headers["Content-Type"] = "application/json"
    call = urllib.request.Request(ROOT + path, data=None if payload is None else json.dumps(payload).encode(), method=method, headers=headers)
    try:
        result = client.open(call)
    except urllib.error.HTTPError as error:
        result = error
    return result.status, json.loads(result.read())


def expect(label, client, method, path, status, payload=None, device=None):
    got, body = request(client, method, path, payload, device)
    print(f"{label}: HTTP {got}")
    assert got == status, (label, got, body)
    return body.get("data")


def png():
    def chunk(name, data):
        return struct.pack(">I", len(data)) + name + data + struct.pack(">I", zlib.crc32(name + data) & 0xFFFFFFFF)
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", 1, 1, 8, 6, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(b"\x00\xff\x00\x00\xff")) + chunk(b"IEND", b"")


def upload(client, question_id):
    boundary = "maqna" + str(time.time_ns())
    raw = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"one.png\"\r\n"
           "Content-Type: image/png\r\n\r\n").encode() + png() + f"\r\n--{boundary}--\r\n".encode()
    csrf = request(client, "GET", "/auth/csrf")[1]["data"]
    call = urllib.request.Request(ROOT + f"/questions/{question_id}/assets", data=raw, method="POST",
                                  headers={csrf["header"]: csrf["token"], "Content-Type": "multipart/form-data; boundary=" + boundary})
    with client.open(call) as result:
        assert result.status == 201
        return json.loads(result.read())["data"]["id"]


teacher, student, second_session, outsider = session(), session(), session(), session()
for client, identity in [(teacher, "teacher_demo"), (student, "student_demo"), (second_session, "student_demo"), (outsider, "noemail_demo")]:
    expect(identity + " login", client, "POST", "/auth/login", 200, {"identity": identity, "password": "FixtureOnly123!"})
subjects = expect("subjects", teacher, "GET", "/question-masters", 200)["subjects"]
math = next(row for row in subjects if row["name"] == "Matematika")
assignments = expect("own teaching", teacher, "GET", "/teacher/assignments", 200)
assignment = next(row for row in assignments if int(row["subject_id"]) == int(math["id"]))
questions = []
for number in [1, 2]:
    question = expect("published question", teacher, "POST", "/questions", 201, {
        "subjectId": int(math["id"]), "body": f"ATT smoke {number} + 1?",
        "options": [{"body": str(number + 1), "isCorrect": True}, {"body": "0", "isCorrect": False}],
        "status": "draft" if number == 1 else "published"})
    if number == 1:
        asset_id = upload(teacher, int(question["id"]))
        expect("publish image question", teacher, "PATCH", f"/questions/{question['id']}", 200, {"status": "published"})
    questions.append(int(question["id"]))
now = datetime.now(timezone.utc)
config = {"title": "Attempt smoke " + str(time.time_ns()), "subjectId": int(math["id"]),
          "semesterId": int(assignment["semester_id"]), "classIds": [int(assignment["class_group_id"])],
          "questionIds": questions,
          "startAt": (now - timedelta(minutes=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
          "endAt": (now + timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
          "durationMinutes": 30, "passingGrade": 70, "maxAttempts": 1, "resultPolicy": "first",
          "shuffleQuestions": True, "shuffleOptions": True}
exam = expect("create exam", teacher, "POST", "/exams", 201, config)
expect("publish exam", teacher, "POST", f"/exams/{exam['id']}/publish", 200)
expect("unassigned student denied", outsider, "POST", f"/student/exams/{exam['id']}/start", 404, device=DEVICE)
attempt = expect("start", student, "POST", f"/student/exams/{exam['id']}/start", 200, device=DEVICE)
assert attempt["examTitle"] == config["title"]
assert attempt["subjectName"] == math["name"]
assert all("categoryName" in row for row in attempt["items"])
assert "is_correct" not in json.dumps(attempt).lower() and "explanation" not in json.dumps(attempt).lower()
resumed = expect("resume", second_session, "POST", f"/student/exams/{exam['id']}/start", 200, device=DEVICE)
assert {k: v for k, v in attempt.items() if k != "serverNow"} == {k: v for k, v in resumed.items() if k != "serverNow"} and len(attempt["items"]) == 2
image_item = next(row for row in attempt["items"] if row["assets"])
assert int(image_item["assets"][0]["id"]) == int(asset_id)
with student.open(ROOT + f"/student/attempts/{attempt['id']}/assets/{asset_id}") as result:
    assert result.status == 200 and result.read().startswith(b"\x89PNG")
expect("other student image denied", outsider, "GET", f"/student/attempts/{attempt['id']}/assets/{asset_id}", 404)
print("assigned image available only in own active attempt: PASS")
expect("other device denied", second_session, "POST", f"/student/exams/{exam['id']}/start", 409, device="another-device-1234567890")
expect("other student attempt denied", outsider, "GET", f"/student/attempts/{attempt['id']}", 404, device=DEVICE)
print("attempt metadata, item category fields and answer-key privacy: PASS")
item = attempt["items"][0]
selected = int(item["options"][0]["id"])
mutation = str(uuid.uuid4())
payload = {"expectedRevision": 0, "selectedOptionId": selected, "marked": True, "mutationId": mutation}
saved = expect("save answer", student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 200, payload, DEVICE)
assert saved["revision"] == 1
assert saved == expect("retry same mutation", second_session, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 200, payload, DEVICE)
expect("stale revision rejected", student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 409, {**payload, "mutationId": str(uuid.uuid4())}, DEVICE)
expect("wrong option rejected", student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 422,
       {"expectedRevision": 1, "selectedOptionId": 999999, "marked": False, "mutationId": str(uuid.uuid4())}, DEVICE)
resumed = expect("refresh has answer and mark", student, "GET", f"/student/attempts/{attempt['id']}", 200, device=DEVICE)
assert resumed["items"][0]["selectedOptionId"] == selected and resumed["items"][0]["marked"] is True
assert [row["id"] for row in resumed["items"]] == [row["id"] for row in attempt["items"]]
assert "is_correct" not in json.dumps(resumed).lower() and "explanation" not in json.dumps(resumed).lower()
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    futures = [pool.submit(expect, "concurrent submit", client, "POST", f"/student/attempts/{attempt['id']}/submit", 200, None, DEVICE)
               for client in (student, second_session)]
    submissions = [future.result() for future in futures]
assert submissions[0] == submissions[1] and submissions[0]["status"] == "submitted"
expect("image after submit denied", student, "GET", f"/student/attempts/{attempt['id']}/assets/{asset_id}", 403)
expect("save after submit rejected", student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 409,
       {"expectedRevision": 1, "selectedOptionId": None, "marked": False, "mutationId": str(uuid.uuid4())}, DEVICE)
expect("submit retry same result", student, "POST", f"/student/attempts/{attempt['id']}/submit", 200, device=DEVICE)
print("CAS, retry, device scope, stable resume and double submit: PASS")
expiry_exam = expect("expiry exam draft", teacher, "POST", "/exams", 201, {**config, "title": "Expiry smoke " + str(time.time_ns())})
expect("expiry exam publish", teacher, "POST", f"/exams/{expiry_exam['id']}/publish", 200)
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    futures = [pool.submit(expect, "concurrent start", client, "POST", f"/student/exams/{expiry_exam['id']}/start", 200, None, DEVICE)
               for client in (student, second_session)]
    starts = [future.result() for future in futures]
assert starts[0]["id"] == starts[1]["id"]
expired_id = int(starts[0]["id"])
subprocess.check_call(["mysql", "-u", "root", "maqna_cbt_dev", "-e",
                       f"UPDATE attempts SET deadline_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id={expired_id}"])
output = subprocess.check_output(["php", "spark", "attempts:finalize-expired"], text=True)
assert "Finalized" in output
expired = expect("expired attempt refresh", student, "GET", f"/student/attempts/{expired_id}", 200, device=DEVICE)
assert expired["status"] == "expired"
expect("expired save denied", student, "PATCH", f"/student/attempts/{expired_id}/items/{expired['items'][0]['id']}", 409,
       {"expectedRevision": 0, "selectedOptionId": None, "marked": False, "mutationId": str(uuid.uuid4())}, DEVICE)
result = expect("expired submit idempotent", student, "POST", f"/student/attempts/{expired_id}/submit", 200, device=DEVICE)
assert result["status"] == "expired"
print("concurrent start and offline expiry cleanup: PASS")
