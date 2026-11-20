"""RES-01..05 end-to-end against dedicated maqna_cbt_dev and fake accounts."""
import http.cookiejar
import json
import subprocess
import time
import urllib.error
import urllib.request
import uuid
from datetime import datetime, timedelta, timezone

ROOT = "http://127.0.0.1:8081/api/v1"
DEVICE = "result-fixture-device-1234567890"


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


admin, teacher, student, outsider = session(), session(), session(), session()
for client, identity in [(admin, "admin_demo"), (teacher, "teacher_demo"), (student, "student_demo"), (outsider, "noemail_demo")]:
    expect(identity + " login", client, "POST", "/auth/login", 200, {"identity": identity, "password": "FixtureOnly123!"})
other_teacher = session()
other_name = "res.teacher." + uuid.uuid4().hex[:12]
new_teacher = expect("create unrelated teacher", admin, "POST", "/teachers", 201, {
    "username": other_name, "email": other_name + "@example.test", "displayName": "Guru luar penugasan", "employeeNo": "RES-" + uuid.uuid4().hex[:10]})
expect("unrelated teacher login", other_teacher, "POST", "/auth/login", 200,
       {"identity": other_name, "password": new_teacher["temporaryPassword"]})
masters = expect("question masters", teacher, "GET", "/question-masters", 200)
math = next(row for row in masters["subjects"] if row["name"] == "Matematika")
assignment = next(row for row in expect("own teaching", teacher, "GET", "/teacher/assignments", 200) if int(row["subject_id"]) == int(math["id"]))
question_ids = []
for number in [1, 2]:
    row = expect("create question", teacher, "POST", "/questions", 201, {
        "subjectId": int(math["id"]), "body": f"RES smoke item {number}",
        "options": [{"body": "Benar", "isCorrect": True}, {"body": "Salah", "isCorrect": False}], "status": "published"})
    question_ids.append(int(row["id"]))
now = datetime.now(timezone.utc)
exam = expect("teacher exam", teacher, "POST", "/exams", 201, {
    "title": "Result smoke " + str(time.time_ns()), "subjectId": int(math["id"]),
    "semesterId": int(assignment["semester_id"]), "classIds": [int(assignment["class_group_id"])],
    "questionIds": question_ids, "startAt": (now - timedelta(minutes=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "endAt": (now + timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "durationMinutes": 30, "passingGrade": 70, "maxAttempts": 1, "resultPolicy": "first"})
expect("teacher publish", teacher, "POST", f"/exams/{exam['id']}/publish", 200)
expect("unrelated teacher report denied", other_teacher, "GET", f"/reports/exams/{exam['id']}", 403)
expect("unrelated teacher release denied", other_teacher, "POST", f"/exams/{exam['id']}/release", 403)
expect("early release denied", teacher, "POST", f"/exams/{exam['id']}/release", 409)
expect("unrelated student history scope", outsider, "GET", "/student/results", 200)
attempt = expect("student start", student, "POST", f"/student/exams/{exam['id']}/start", 200, device=DEVICE)
expect("active release denied", teacher, "POST", f"/exams/{exam['id']}/release", 409)
before = expect("student active history", student, "GET", "/student/results", 200)
active = next(row for row in before if int(row["examId"]) == int(exam["id"]))
assert active["status"] == "in_progress" and "score" not in active and "passed" not in active
for index, item in enumerate(attempt["items"]):
    selected = item["options"][0 if index == 0 else 1]["id"]
    expect("save result answer", student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 200,
           {"expectedRevision": 0, "selectedOptionId": selected, "marked": False, "mutationId": str(uuid.uuid4())}, DEVICE)
submitted = expect("student submit", student, "POST", f"/student/attempts/{attempt['id']}/submit", 200, device=DEVICE)
assert submitted["status"] == "submitted" and "score" not in submitted
pending = expect("student pending detail", student, "GET", f"/student/results/{attempt['id']}", 200)
assert pending == {"status": "awaiting_release"}
history = expect("student pending history", student, "GET", "/student/results", 200)
pending_row = next(row for row in history if int(row["examId"]) == int(exam["id"]))
assert pending_row["status"] == "awaiting_release" and "score" not in pending_row and "passed" not in pending_row
staff_report = expect("teacher private report", teacher, "GET", f"/reports/exams/{exam['id']}", 200)
assert staff_report["statistics"]["graded"] == 1 and any(row["score"] is not None and float(row["score"]) == 50.0 for row in staff_report["rows"])
expect("student report denied", student, "GET", f"/reports/exams/{exam['id']}", 403)
expect("unrelated student detail denied", outsider, "GET", f"/student/results/{attempt['id']}", 404)
expect("release before end denied", teacher, "POST", f"/exams/{exam['id']}/release", 409)
subprocess.check_call(["mysql", "-u", "root", "maqna_cbt_dev", "-e",
                       f"UPDATE exams SET end_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id={int(exam['id'])}"])
release = expect("teacher manual release", teacher, "POST", f"/exams/{exam['id']}/release", 200)
assert int(release["result_count"]) == 1
assert release == expect("release idempotent", teacher, "POST", f"/exams/{exam['id']}/release", 200)
result = expect("student released result", student, "GET", f"/student/results/{attempt['id']}", 200)
assert result["status"] == "released" and result["score"] == 50.0 and result["passed"] is False
expect("review disabled after release", student, "GET", f"/student/results/{attempt['id']}/review", 403)
history = expect("student released history", student, "GET", "/student/results", 200)
released_row = next(row for row in history if int(row["examId"]) == int(exam["id"]))
assert released_row["score"] == 50.0 and released_row["passed"] is False
filtered = expect("report filters", teacher, "GET", f"/reports/exams?examId={exam['id']}&semesterId={assignment['semester_id']}&subjectId={math['id']}", 200)
assert len(filtered) == 1 and int(filtered[0]["id"]) == int(exam["id"])
audit = subprocess.check_output(["mysql", "-u", "root", "-N", "maqna_cbt_dev", "-e",
                                 f"SELECT COUNT(*) FROM audit_events WHERE action='results.release' AND target_id={int(exam['id'])}"], text=True)
assert int(audit.strip()) == 1
print("RES smoke passed")
