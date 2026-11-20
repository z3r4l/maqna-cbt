"""Local S0 HTTP smoke test against fake LocalDemoSeeder users."""
import http.cookiejar
import json
import hashlib
import subprocess
import time
import urllib.error
import urllib.request

ROOT = "http://127.0.0.1:8081/api/v1"
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def dev_sql(statement):
    subprocess.run(["mysql", "-uroot", "maqna_cbt_dev", "-e", statement], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)


def call(method, path, payload=None, csrf=None):
    body = None if payload is None else json.dumps(payload).encode()
    headers = {"Accept": "application/json"}
    if body is not None:
        headers["Content-Type"] = "application/json"
    if csrf is not None:
        headers[csrf["header"]] = csrf["token"]
    request = urllib.request.Request(ROOT + path, data=body, headers=headers, method=method)
    try:
        response = client.open(request)
    except urllib.error.HTTPError as error:
        response = error
    raw = response.read().decode()
    try:
        decoded = json.loads(raw)
    except json.JSONDecodeError:
        decoded = {"raw": raw[:180]}
    return response.status, decoded


def token():
    status, body = call("GET", "/auth/csrf")
    assert status == 200, (status, body)
    return body["data"]


def expect(label, method, path, expected, payload=None, csrf=None):
    status, body = call(method, path, payload, csrf)
    print(f"{label}: HTTP {status}")
    assert status == expected, (label, status, body.get('error', {}).get('message', body.get('message', 'unexpected response')))
    return body


expect("anonymous me", "GET", "/auth/me", 401)
expect("missing csrf", "POST", "/auth/login", 403, {"identity": "student_demo", "password": "FixtureOnly123!"})
expect("wrong password", "POST", "/auth/login", 401, {"identity": "student_demo", "password": "wrong"}, token())
student = expect("username login", "POST", "/auth/login", 200, {"identity": "student_demo", "password": "FixtureOnly123!"}, token())["data"]
expect("authenticated me", "GET", "/auth/me", 200)
expect("profile name update", "PATCH", "/auth/profile", 200, {"displayName": "Siswa Uji"}, token())
expect("student role edit rejected", "PATCH", "/auth/profile", 422, {"displayName": "Siswa Uji", "role": "superadmin"}, token())
expect("profile name restore", "PATCH", "/auth/profile", 200, {"displayName": "Siswa Contoh"}, token())
expect("student admin reset denied", "POST", "/auth/password/admin-reset", 403, {"userId": student["id"]}, token())
expect("logout", "POST", "/auth/logout", 200, {}, token())
expect("after logout", "GET", "/auth/me", 401)
expect("email login", "POST", "/auth/login", 200, {"identity": "student@example.invalid", "password": "FixtureOnly123!"}, token())
expect("logout again", "POST", "/auth/logout", 200, {}, token())
expect("admin login", "POST", "/auth/login", 200, {"identity": "admin_demo", "password": "FixtureOnly123!"}, token())
issued = expect("admin issues token", "POST", "/auth/password/issue-token", 200, {"userId": student["id"]}, token())["data"]["token"]
dev_sql(f"UPDATE user_profiles SET school_id=2 WHERE user_id={student['id']}")
try:
    expect("cross-school reset denied", "POST", "/auth/password/issue-token", 403, {"userId": student["id"]}, token())
finally:
    dev_sql(f"UPDATE user_profiles SET school_id=1 WHERE user_id={student['id']}")
expired = expect("admin issues expiring token", "POST", "/auth/password/issue-token", 200, {"userId": student["id"]}, token())["data"]["token"]
dev_sql(f"UPDATE password_reset_tokens SET expires_at='2000-01-01 00:00:00' WHERE token_hash='{hashlib.sha256(expired.encode()).hexdigest()}'")
expect("admin logout", "POST", "/auth/logout", 200, {}, token())
expect("expired token rejected", "POST", "/auth/password/reset", 422, {"token": expired, "newPassword": "ChangedFixture123!"}, token())
expect("reset token", "POST", "/auth/password/reset", 200, {"token": issued, "newPassword": "ChangedFixture123!"}, token())
expect("single use token", "POST", "/auth/password/reset", 422, {"token": issued, "newPassword": "ChangedFixture123!"}, token())
expect("new password login", "POST", "/auth/login", 200, {"identity": "student_demo", "password": "ChangedFixture123!"}, token())
expect("restore fixture password", "POST", "/auth/password/change", 200, {"currentPassword": "ChangedFixture123!", "newPassword": "FixtureOnly123!"}, token())
expect("final logout", "POST", "/auth/logout", 200, {}, token())
expect("admin login again", "POST", "/auth/login", 200, {"identity": "admin_demo", "password": "FixtureOnly123!"}, token())
temporary = expect("admin temporary password", "POST", "/auth/password/admin-reset", 200, {"username": "student_demo"}, token())["data"]["temporaryPassword"]
expect("admin final logout", "POST", "/auth/logout", 200, {}, token())
temporary_profile = expect("temporary login", "POST", "/auth/login", 200, {"identity": "student_demo", "password": temporary}, token())["data"]
assert temporary_profile["mustChangePassword"] is True
expect("required password change", "POST", "/auth/password/change", 200, {"currentPassword": temporary, "newPassword": "FixtureOnly123!"}, token())
expect("temporary flow logout", "POST", "/auth/logout", 200, {}, token())
noemail = expect("username-only student login", "POST", "/auth/login", 200, {"identity": "noemail_demo", "password": "FixtureOnly123!"}, token())["data"]
assert noemail["email"] is None
expect("username-only student logout", "POST", "/auth/logout", 200, {}, token())
expect("internal alias cannot login", "POST", "/auth/login", 401, {"identity": "noemail_demo@internal.maqna.invalid", "password": "FixtureOnly123!"}, token())
dev_sql("UPDATE user_profiles p JOIN users u ON u.id=p.user_id SET p.active=0 WHERE u.username='teacher_demo'")
try:
    expect("disabled account denied", "POST", "/auth/login", 401, {"identity": "teacher_demo", "password": "FixtureOnly123!"}, token())
finally:
    dev_sql("UPDATE user_profiles p JOIN users u ON u.id=p.user_id SET p.active=1 WHERE u.username='teacher_demo'")
duplicate = subprocess.run(["mysql", "-uroot", "maqna_cbt_dev", "-e", "INSERT INTO users (username,active) VALUES ('STUDENT_DEMO',1)"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
assert duplicate.returncode != 0, "case-insensitive duplicate username was inserted"
print("case-insensitive duplicate rejected")
unknown = f"unknown_rate_limit_{time.time_ns()}"
for attempt in range(5):
    expect(f"rate limit try {attempt + 1}", "POST", "/auth/login", 401, {"identity": unknown, "password": "bad"}, token())
expect("rate limit rejection", "POST", "/auth/login", 429, {"identity": unknown, "password": "bad"}, token())
print("AUTH smoke passed")
