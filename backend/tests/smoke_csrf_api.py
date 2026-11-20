"""CSRF error envelope and token regeneration smoke against a local CI4 server."""
import http.cookiejar
import json
import urllib.error
import urllib.request

ROOT = "http://127.0.0.1:8081/api/v1"
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(method, path, payload=None, token=None):
    body = None if payload is None else json.dumps(payload).encode()
    headers = {"Accept": "application/json"}
    if body is not None:
        headers["Content-Type"] = "application/json"
    if token is not None:
        headers["X-CSRF-TOKEN"] = token
    request = urllib.request.Request(ROOT + path, data=body, headers=headers, method=method)
    try:
        response = client.open(request)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.loads(response.read().decode())


def csrf():
    status, body = call("GET", "/auth/csrf")
    assert status == 200, (status, body)
    return body["data"]["token"]


first = csrf()
status, body = call("POST", "/auth/login", {}, first)
assert status == 422 and body["error"]["code"] == "VALIDATION", (status, body)

status, body = call("POST", "/auth/login", {}, first)
assert status == 403 and body["error"]["code"] == "CSRF_INVALID", (status, body)

second = csrf()
status, body = call("POST", "/auth/login", {}, second)
assert status == 422 and body["error"]["code"] == "VALIDATION", (status, body)

print("API CSRF token rotation after 422, stale-token CSRF_INVALID envelope, fresh-token recovery: PASS")
