"""Focused bank container checks against fake local accounts only."""
import http.cookiejar
import json
import urllib.error
import urllib.request

ROOT = "http://127.0.0.1:8081/api/v1"


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(client, method, path, payload=None):
    headers = {"Accept": "application/json"}
    if method not in ("GET", "HEAD"):
        csrf = request(client, "GET", "/auth/csrf")[1]["data"]
        headers[csrf["header"]] = csrf["token"]
    raw = None if payload is None else json.dumps(payload).encode()
    if raw is not None:
        headers["Content-Type"] = "application/json"
    call = urllib.request.Request(ROOT + path, data=raw, method=method, headers=headers)
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
    expect(identity + " login", client, "POST", "/auth/login", 200,
           {"identity": identity, "password": "FixtureOnly123!"})

expect("student banks denied", student, "GET", "/question-banks", 403)
masters = expect("bank masters", admin, "GET", "/question-bank-masters", 200)
teacher_masters = expect("teacher bank masters", teacher, "GET", "/question-bank-masters", 200)
teacher_subject = next(row for row in teacher_masters["subjects"] if row["name"] == "Matematika")
teacher_class = teacher_masters["classes"][0]
teacher_bank = expect("teacher creates assigned bank", teacher, "POST", "/question-banks", 201,
                      {"name": "Guru " + __import__("uuid").uuid4().hex[:10], "subjectId": teacher_subject["id"],
                       "examTypeId": teacher_masters["examTypes"][0]["id"], "classGroupIds": [teacher_class["id"]]})
expect("teacher sees own bank", teacher, "GET", f"/question-banks/{teacher_bank['id']}", 200)
expect("teacher archives own bank", teacher, "DELETE", f"/question-banks/{teacher_bank['id']}", 200)
subject = next(row for row in masters["subjects"] if row["name"] == "Matematika")
classes = masters["classes"][:2]
exam_type = masters["examTypes"][0]
assert classes and exam_type
base = {"name": "Bank uji " + __import__("uuid").uuid4().hex[:10], "subjectId": subject["id"],
        "examTypeId": exam_type["id"], "classGroupIds": [row["id"] for row in classes]}
expect("empty classes denied", admin, "POST", "/question-banks", 422, {**base, "classGroupIds": []})
expect("unknown class denied", admin, "POST", "/question-banks", 422, {**base, "classGroupIds": [999999]})
expect("explicit invalid bank id denied", admin, "POST", "/questions", 422,
       {"bankId": 0, "subjectId": subject["id"], "body": "Bad bank", "options": []})
bank = expect("create bank", admin, "POST", "/question-banks", 201, base)
bid = bank["id"]
assert bank["name"] == base["name"] and len(bank["classes"]) == len(classes)
expect("exam type remains required", admin, "PATCH", f"/question-banks/{bid}", 422, {"examTypeId": 0})
expect("teacher cannot read admin bank", teacher, "GET", f"/question-banks/{bid}", 404)
expect("teacher cannot add to admin bank", teacher, "POST", "/questions", 422,
       {"bankId": bid, "subjectId": subject["id"], "body": "Wrong owner", "options": []})
question = expect("add bank question", admin, "POST", "/questions", 201,
                  {"bankId": bid, "subjectId": subject["id"], "body": "Bank question?", "options": []})
assert int(question["bank_id"]) == int(bid), (question["bank_id"], bid)
incomplete = expect("save incomplete options as draft", admin, "POST", "/questions", 201,
                    {"bankId": bid, "subjectId": subject["id"], "body": "Incomplete draft?",
                     "options": [{"body": "", "isCorrect": True}, {"body": "", "isCorrect": False}]})
expect("reject incomplete published options", admin, "PATCH", f"/questions/{incomplete['id']}", 422,
       {"status": "published"})
listed = expect("filter bank questions", admin, "GET", f"/questions?bankId={bid}", 200)
assert any(int(row["id"]) == int(question["id"]) for row in listed)
detail = expect("bank detail", admin, "GET", f"/question-banks/{bid}", 200)
assert int(detail["question_count"]) == 2
filtered = expect("filter banks by class", admin, "GET", f"/question-banks?classGroupId={classes[0]['id']}", 200)
assert any(int(row["id"]) == int(bid) for row in filtered)
expect("teacher cannot edit admin bank", teacher, "PATCH", f"/question-banks/{bid}", 404, {"name": "No"})
expect("student cannot see bank question", student, "GET", f"/questions/{question['id']}", 403)
expect("archive bank", admin, "DELETE", f"/question-banks/{bid}", 200)
expect("archived bank hidden", admin, "GET", f"/question-banks/{bid}", 404)
expect("question retained for exam snapshots", admin, "GET", f"/questions/{question['id']}", 200)
print("Bank container smoke passed")
