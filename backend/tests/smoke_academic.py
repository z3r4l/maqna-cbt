"""S1 API smoke against the dedicated maqna_cbt_dev database and fake users."""
import http.cookiejar
import json
import time
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
    if payload is not None:
        headers["Content-Type"] = "application/json"
    raw = None if payload is None else json.dumps(payload).encode()
    call = urllib.request.Request(ROOT + path, data=raw, method=method, headers=headers)
    try:
        result = client.open(call)
    except urllib.error.HTTPError as error:
        result = error
    content = result.read().decode()
    try:
        body = json.loads(content)
    except json.JSONDecodeError:
        body = {"message": content[:160]}
    return result.status, body


def expect(label, client, method, path, status, payload=None):
    got, body = request(client, method, path, payload)
    print(f"{label}: HTTP {got}")
    assert got == status, (label, got, body.get("error", {}).get("message", body.get("message", "unexpected response")))
    return body.get("data")


admin, teacher, student = session(), session(), session()
expect("admin login", admin, "POST", "/auth/login", 200, {"identity": "admin_demo", "password": "FixtureOnly123!"})
expect("teacher login", teacher, "POST", "/auth/login", 200, {"identity": "teacher_demo", "password": "FixtureOnly123!"})
expect("student login", student, "POST", "/auth/login", 200, {"identity": "student_demo", "password": "FixtureOnly123!"})
expect("student cannot list students", student, "GET", "/people/students", 403)
expect("student cannot edit roster", student, "PATCH", "/people/students/1", 403, {"nis": "FORGED"})
expect("teacher cannot list all students", teacher, "GET", "/people/students", 403)
school = expect("own school", admin, "GET", "/school", 200)
assert school["code"] == "MAQNA-DEMO"

classes = expect("class list", admin, "GET", "/academic/class-groups?perPage=100", 200)
class_ids = {row["code"]: int(row["id"]) for row in classes}
grades = expect("grade list", admin, "GET", "/academic/grade-levels", 200)
grade_vii = next(int(row["id"]) for row in grades if row["code"] == "VII")
years = expect("year list", admin, "GET", "/academic/academic-years", 200)
old_year = next(row for row in years if row["label"] == "2026/2027")
semesters = expect("semester list", admin, "GET", "/academic/semesters", 200)
old_semester = next(row for row in semesters if row["name"] == "Ganjil" and int(row["academic_year_id"]) == int(old_year["id"]))
expect("cannot deactivate only active year", admin, "PATCH", f"/academic/academic-years/{old_year['id']}", 422, {"active": 0})
expect("cannot deactivate only active semester", admin, "PATCH", f"/academic/semesters/{old_semester['id']}", 422, {"active": 0})
subjects = expect("subject list", admin, "GET", "/academic/subjects?perPage=100", 200)
math = next(row for row in subjects if row["code"] == "MAT")
teachers = expect("teacher list", admin, "GET", "/people/teachers?search=teacher_demo", 200)
teacher_id = next(int(row["id"]) for row in teachers if row["username"] == "teacher_demo")
students = expect("student list", admin, "GET", "/people/students?search=student_demo", 200)
student_id = next(int(row["id"]) for row in students if row["username"] == "student_demo")
assert next(row for row in students if row["username"] == "student_demo")["class_name"] == "VII A"

own = expect("teacher own assignments", teacher, "GET", "/teacher/assignments", 200)
assert own and all(int(row["teacher_id"]) == teacher_id for row in own)
roster = expect("teacher own roster", teacher, "GET", f"/teacher/classes/{class_ids['VII-A']}/students", 200)
assert any(int(row["student_id"]) == student_id for row in roster)
expect("teacher wrong class denied", teacher, "GET", f"/teacher/classes/{class_ids['VII-B']}/students", 403)
my_class = expect("student own class", student, "GET", "/student/class", 200)
assert int(my_class["class_group_id"]) == class_ids["VII-A"]
expect("cross-year teaching denied", admin, "POST", "/teaching-assignments", 422, {"teacherId": teacher_id, "subjectId": math["id"], "classGroupId": 99999, "semesterId": old_semester["id"]})
expect("cross-school subject denied", admin, "POST", "/question-categories", 422, {"subject_id": 99999, "name": "Lintas Sekolah"})

new_years = [row for row in years if row["label"] == "2027/2028"]
new_year = new_years[0] if new_years else expect("create future year", admin, "POST", "/academic/academic-years", 201, {"label": "2027/2028", "start_on": "2027-07-01", "end_on": "2028-06-30", "active": 0})
try:
    expect("activate new year", admin, "PATCH", f"/academic/academic-years/{new_year['id']}", 200, {"active": 1})
    expect("old year archived", admin, "GET", f"/academic/academic-years/{old_year['id']}", 200)
    new_semesters = [row for row in expect("semesters after rollover", admin, "GET", "/academic/semesters", 200) if int(row["academic_year_id"]) == int(new_year["id"]) and row["name"] == "Ganjil"]
    new_semester = new_semesters[0] if new_semesters else expect("create new semester", admin, "POST", "/academic/semesters", 201, {"academic_year_id": new_year["id"], "name": "Ganjil", "start_on": "2027-07-01", "end_on": "2027-12-31", "active": 1})
    expect("activate new semester", admin, "PATCH", f"/academic/semesters/{new_semester['id']}", 200, {"active": 1})
    future_classes = [row for row in expect("future class list", admin, "GET", "/academic/class-groups", 200) if row["code"] == "VII-C-SMOKE"]
    future_class = future_classes[0] if future_classes else expect("create future class", admin, "POST", "/academic/class-groups", 201, {"academic_year_id": new_year["id"], "grade_level_id": grade_vii, "code": "VII-C-SMOKE", "name": "VII C Uji"})
    expect("move student into future year", admin, "POST", "/student-memberships", 201, {"studentId": student_id, "classGroupId": future_class["id"]})
    history = expect("membership history", admin, "GET", f"/student-memberships?studentId={student_id}", 200)
    assert len(history) >= 2
finally:
    expect("restore active year", admin, "PATCH", f"/academic/academic-years/{old_year['id']}", 200, {"active": 1})
    expect("restore active semester", admin, "PATCH", f"/academic/semesters/{old_semester['id']}", 200, {"active": 1})
    expect("restore student class", admin, "POST", "/student-memberships", 201, {"studentId": student_id, "classGroupId": class_ids["VII-A"]})

all_years = expect("one active year", admin, "GET", "/academic/academic-years", 200)
assert sum(int(row["active"]) for row in all_years) == 1
all_semesters = expect("one active semester", admin, "GET", "/academic/semesters", 200)
assert sum(int(row["active"]) for row in all_semesters) == 1
unique = str(time.time_ns())[-8:]
created = expect("create student without email", admin, "POST", "/people/students", 201, {"username": f"smoke.{unique}", "displayName": "Siswa Uji API", "nis": f"NIS-SMOKE-{unique}"})
assert created["person"]["email"] is None and created["temporaryPassword"]
nis_status, nis_body = request(admin, "GET", f"/people/students?search=NIS-SMOKE-{unique}&page=1&perPage=1")
assert nis_status == 200 and nis_body["meta"]["total"] == 1 and nis_body["meta"]["perPage"] == 1
assert nis_body["data"][0]["id"] == created["person"]["id"] and nis_body["data"][0]["class_name"] is None
expect("deactivate new student", admin, "PATCH", f"/people/students/{created['person']['id']}", 200, {"status": "inactive"})
expect("student cannot read teacher roster", student, "GET", f"/teacher/classes/{class_ids['VII-A']}/students", 403)
print("ACD smoke passed")
