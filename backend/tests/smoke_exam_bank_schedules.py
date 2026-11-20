"""EXB-01 onward: bank based schedule flow against fake local accounts."""
import http.cookiejar
import json
import urllib.error
import urllib.request
from datetime import datetime, timedelta, timezone
import uuid

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
    return body.get("data"), body.get("meta", {})


admin = session()
expect("admin login", admin, "POST", "/auth/login", 200,
       {"identity": "admin_demo", "password": "FixtureOnly123!"})
rows, meta = expect("schedule status tabs", admin, "GET", "/exams?status=active", 200)
assert set(meta.get("counts", {})) >= {"all", "active", "finished", "draft", "paused"}, meta
assert isinstance(rows, list)
print("EXB schedule tab counts: PASS")

masters, _ = expect("schedule masters", admin, "GET", "/exam-masters", 200)
members, membership_meta = expect("student memberships", admin, "GET", "/student-memberships?perPage=100", 200)
for membership_page in range(2, (membership_meta["total"] + 99) // 100 + 1):
    more, _ = expect("student memberships page " + str(membership_page), admin, "GET", f"/student-memberships?page={membership_page}&perPage=100", 200)
    members.extend(more)
student_row = next((row for row in members if row["username"] == "student_demo" and row["active"] == "1" and row["class_name"] == "VII A"), None)
assert student_row, "fixture student_demo needs an active membership"
semester = next(row for row in masters["semesters"] if int(row["academic_year_id"]) == int(student_row["academic_year_id"]))
math = next(row for row in masters["subjects"] if row["name"] == "Matematika")
exam_type = masters["examTypes"][0]
class_id = int(student_row["class_group_id"])
bank, _ = expect("create schedule bank", admin, "POST", "/question-banks", 201,
                 {"name": "EXB " + uuid.uuid4().hex[:10], "subjectId": int(math["id"]),
                  "examTypeId": int(exam_type["id"]), "classGroupIds": [class_id]})
question, _ = expect("create schedule bank question", admin, "POST", "/questions", 201,
                     {"bankId": int(bank["id"]), "subjectId": int(math["id"]), "body": "EXB published question?", "weight": 1,
                      "options": [{"body": "Benar", "isCorrect": True}, {"body": "Salah", "isCorrect": False}]})
question, _ = expect("publish schedule bank question", admin, "PATCH", f"/questions/{question['id']}", 200,
                     {"status": "published", "options": [{"body": "Benar", "isCorrect": True}, {"body": "Salah", "isCorrect": False}]})
future = datetime.now(timezone.utc) + timedelta(minutes=1)
base = {"bankId": int(bank["id"]), "title": "EXB jadwal " + uuid.uuid4().hex[:8], "semesterId": int(semester["id"]),
        "classIds": [class_id], "displayQuestionCount": 1, "startAt": future.strftime("%Y-%m-%dT%H:%M:%SZ"),
        "endAt": (future + timedelta(hours=2)).strftime("%Y-%m-%dT%H:%M:%SZ"), "durationMinutes": 60,
        "accessCode": "EXB" + uuid.uuid4().hex[:8].upper(), "shuffleQuestions": True, "shuffleOptions": True}
draft, _ = expect("create bank schedule draft", admin, "POST", "/exams/from-bank", 201, {**base, "status": "draft"})
expect("bad direct publication rolls back", admin, "PATCH", f"/exams/{draft['id']}/from-bank", 422,
       {**base, "classIds": [], "status": "published"})
draft_after, _ = expect("draft remains after rollback", admin, "GET", f"/exams/{draft['id']}", 200)
assert draft_after["status"] == "draft" and draft_after["classIds"] == [class_id], draft_after
expect("archive test draft", admin, "DELETE", f"/exams/{draft['id']}", 200)

active, _ = expect("direct publish from bank", admin, "POST", "/exams/from-bank", 201,
                   {**base, "title": "EXB terbit " + uuid.uuid4().hex[:8], "status": "published",
                    "startAt": (datetime.now(timezone.utc) - timedelta(minutes=2)).strftime("%Y-%m-%dT%H:%M:%SZ")})
assert active["status"] == "published" and int(active["source_bank_id"]) == int(bank["id"])
assert int(active["display_question_count"]) == 1 and active["participantCount"] >= 1
student = session()
expect("student login", student, "POST", "/auth/login", 200,
       {"identity": "student_demo", "password": "FixtureOnly123!"})
attempt, _ = expect("student starts bank exam", student, "POST", f"/student/exams/{active['id']}/start", 200, {"accessCode": base["accessCode"]})
resumed, _ = expect("student resumes bank exam", student, "POST", f"/student/exams/{active['id']}/start", 200, {"accessCode": base["accessCode"]})
assert attempt["items"] == resumed["items"] and len(attempt["items"]) == 1
assert "is_correct" not in json.dumps(attempt).lower() and "match_choice_id" not in json.dumps(attempt).lower()
print("EXB atomic publish, frozen snapshot, resume and key privacy: PASS")
