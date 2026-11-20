"""EXM-01..04 against the dedicated maqna_cbt_dev database and fake users."""
import http.cookiejar
import json
import subprocess
import time
import urllib.error
import urllib.request
from datetime import datetime, timedelta, timezone

ROOT = "http://127.0.0.1:8081/api/v1"


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(client, method, path, payload=None):
    headers = {"Accept": "application/json"}
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


def expect(label, client, method, path, status, payload=None):
    got, body = request(client, method, path, payload)
    print(f"{label}: HTTP {got}")
    assert got == status, (label, got, body)
    return body.get("data")


admin, teacher, student = session(), session(), session()
for client, identity in [(admin, "admin_demo"), (teacher, "teacher_demo"), (student, "student_demo")]:
    expect(identity + " login", client, "POST", "/auth/login", 200, {"identity": identity, "password": "FixtureOnly123!"})
classes = expect("classes", admin, "GET", "/class-groups", 200)
class_ids = {row["code"]: int(row["id"]) for row in classes}
subject = next(row for row in expect("subjects", admin, "GET", "/subjects?perPage=100", 200) if row["code"] == "MAT")
semester = next(row for row in expect("semesters", admin, "GET", "/semesters", 200) if row["name"] == "Ganjil" and int(row["active"]) == 1)
question = expect("teacher question", teacher, "POST", "/questions", 201, {
    "subjectId": int(subject["id"]), "body": "EXM smoke: 2 + 3 = ?", "options": [
        {"body": "5", "isCorrect": True}, {"body": "6", "isCorrect": False}], "status": "published"})
start = (datetime.now(timezone.utc) + timedelta(days=1)).strftime("%Y-%m-%dT%H:%M:%SZ")
end = (datetime.now(timezone.utc) + timedelta(days=2)).strftime("%Y-%m-%dT%H:%M:%SZ")
base = {"title": "Ujian smoke " + str(time.time_ns()), "subjectId": int(subject["id"]), "semesterId": int(semester["id"]),
        "startAt": start, "endAt": end, "durationMinutes": 60, "passingGrade": 70,
        "maxAttempts": 1, "resultPolicy": "first", "shuffleQuestions": True, "shuffleOptions": True}
question_id = int(question["id"])
wrong = expect("teacher draft wrong class", teacher, "POST", "/exams", 201, {**base, "classIds": [class_ids["VII-B"]], "questionIds": [question_id]})
expect("teacher roster wrong class denied", teacher, "GET", f"/exams/{wrong['id']}/roster", 403)
expect("teacher publish wrong class denied", teacher, "POST", f"/exams/{wrong['id']}/publish", 422)
invalid = expect("teacher incomplete draft", teacher, "POST", "/exams", 201, {**base, "title": "Draf tanpa soal", "classIds": [class_ids["VII-A"]], "questionIds": []})
expect("publish incomplete denied", teacher, "POST", f"/exams/{invalid['id']}/publish", 422)
good = expect("teacher valid draft", teacher, "POST", "/exams", 201, {**base, "classIds": [class_ids["VII-A"]], "questionIds": [question_id]})
roster = expect("teacher roster own class", teacher, "GET", f"/exams/{good['id']}/roster", 200)
assert roster and all(int(row["class_group_id"]) == class_ids["VII-A"] for row in roster)
expect("student cannot list staff exams", student, "GET", "/exams", 403)
expect("student no draft", student, "GET", "/student/exams", 200)
published = expect("teacher publish valid", teacher, "POST", f"/exams/{good['id']}/publish", 200)
assert published["status"] == "published" and published["participantCount"] >= 1
expect("published immutable", teacher, "PATCH", f"/exams/{good['id']}", 422, {"title": "Changed"})
expect("duplicate publish denied", teacher, "POST", f"/exams/{good['id']}/publish", 422)
student_exams = expect("student assigned exams", student, "GET", "/student/exams", 200)
visible = next(row for row in student_exams if int(row["id"]) == int(good["id"]))
serialized = json.dumps(visible).lower()
assert "is_correct" not in serialized and "snapshot" not in serialized and "explanation" not in serialized
snapshot = subprocess.check_output(["mysql", "-u", "root", "-N", "maqna_cbt_dev", "-e", f"SELECT snapshot_json FROM exam_items WHERE exam_id={int(good['id'])}"], text=True)
frozen = json.loads(snapshot.strip())
assert frozen["body"] == question["body"] and sum(int(option["is_correct"]) for option in frozen["options"]) == 1
second = expect("second published question", teacher, "POST", "/questions", 201, {
    "subjectId": int(subject["id"]), "body": "EXM smoke: 4 + 4 = ?", "options": [
        {"body": "8", "isCorrect": True}, {"body": "9", "isCorrect": False}], "status": "published"})
active_base = {**base, "title": "Ujian aktif " + str(time.time_ns()),
               "startAt": (datetime.now(timezone.utc) - timedelta(minutes=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
               "endAt": (datetime.now(timezone.utc) + timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
               "classIds": [class_ids["VII-A"]], "questionIds": [question_id, int(second["id"])]}
active = expect("active exam draft", teacher, "POST", "/exams", 201, active_base)
expect("active exam publish", teacher, "POST", f"/exams/{active['id']}/publish", 200)
first_start = expect("student starts", student, "POST", f"/student/exams/{active['id']}/start", 200)
second_start = expect("student resumes same attempt", student, "POST", f"/student/exams/{active['id']}/start", 200)
detail = expect("student refreshes attempt", student, "GET", f"/student/attempts/{first_start['id']}", 200)
assert {k: v for k, v in first_start.items() if k != "serverNow"} == {k: v for k, v in second_start.items() if k != "serverNow"} == {k: v for k, v in detail.items() if k != "serverNow"}
assert len(first_start["items"]) == 2 and all(len(item["options"]) == 2 for item in first_start["items"])
assert "is_correct" not in json.dumps(first_start).lower() and "explanation" not in json.dumps(first_start).lower()
print("stable persisted shuffle and no key leak: PASS")
expect("archive source", teacher, "DELETE", f"/questions/{question['id']}", 200)
snapshot_after = subprocess.check_output(["mysql", "-u", "root", "-N", "maqna_cbt_dev", "-e", f"SELECT snapshot_json FROM exam_items WHERE exam_id={int(good['id'])}"], text=True)
assert snapshot == snapshot_after
print("immutable snapshot survives source archive: PASS")
print("EXM smoke passed")
